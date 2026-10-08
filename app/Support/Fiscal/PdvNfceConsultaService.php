<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\ConsultarNfceRequest;
use Unitec\FiscalEngine\Dto\ConsultarNfceResponse;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;

final class PdvNfceConsultaService
{
    /** Teto seguro para motivo da SEFAZ (coluna TEXT). */
    private const MOTIVO_REJEICAO_MAX = 2000;

    /** cStat da consulta: chave não consta na base da SEFAZ (nota não recebida). */
    public const CSTAT_NAO_CONSTA = '217';

    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly FiscalEngine $engine = new FiscalEngine(),
    ) {}

    public function recuperar(PdvVendaNfce $nfce, Empresa $empresa): PdvVendaNfce
    {
        if ($nfce->simulada) {
            throw new FiscalEngineException('Consulta SEFAZ não se aplica a NFC-e simulada.');
        }

        if (blank($nfce->chave)) {
            throw new FiscalEngineException('NFC-e sem chave de acesso para consulta.');
        }

        $parametros = VendasParametro::forEmpresa((int) $empresa->id);

        if (! $this->payloadBuilder->podeOperarReal($parametros, $empresa)) {
            throw new FiscalEngineException('Consulta real de NFC-e não está configurada para esta empresa/UF.');
        }

        $response = $this->consultarChave((string) $nfce->chave, $empresa, $parametros);

        return $this->aplicarResultado($nfce, $empresa, $response);
    }

    public function consultarChave(
        string $chave,
        Empresa $empresa,
        VendasParametro $parametros,
        ?Certificate $certificate = null,
    ): ConsultarNfceResponse {
        return $this->engine->consultarNfce(new ConsultarNfceRequest(
            certificate: $certificate ?? NfceFiscalCertificateResolver::resolve($empresa, $parametros),
            chave: $chave,
            tpAmb: NfceFiscalCertificateResolver::tpAmb($parametros),
        ));
    }

    /**
     * Aplica a situação da SEFAZ sem perder o documento: o XML gravado só é promovido a nfeProc
     * quando o protNFe confere (chave + digVal); protocolos existentes não são apagados.
     * "Não consta" (217) e cStat inconclusivo não alteram o status local.
     */
    public function aplicarResultado(PdvVendaNfce $nfce, Empresa $empresa, ConsultarNfceResponse $response): PdvVendaNfce
    {
        $statusAnterior = (string) $nfce->status;
        $protNFe = NfceXmlProtocolo::protNFe($response->xml);

        if ($response->autorizada || $response->cancelada) {
            $updates = $this->dadosDoProtocolo($nfce, $protNFe, $response->protocolo);

            if ($response->autorizada) {
                $updates['status'] = PdvVendaNfce::STATUS_AUTORIZADA;
                $updates['autorizada_em'] = $nfce->autorizada_em ?? now();
            } else {
                $updates['status'] = PdvVendaNfce::STATUS_CANCELADA;
                $updates['cancelada_em'] = $nfce->cancelada_em ?? now();
                $protCancelamento = NfceXmlProtocolo::protocoloCancelamento($response->xml);
                if (blank($nfce->protocolo_cancelamento) && $protCancelamento !== '') {
                    $updates['protocolo_cancelamento'] = $protCancelamento;
                }
            }

            $nfce->update($updates);
            $nfce = $nfce->fresh() ?? $nfce;

            if ($response->autorizada) {
                (new ContadorCloudPortalHookService())->onNfceAutorizada($nfce, $empresa);
            } elseif ($statusAnterior !== PdvVendaNfce::STATUS_CANCELADA) {
                (new ContadorCloudPortalHookService())->onNfceCancelada($nfce, $empresa);
            }

            return $nfce;
        }

        if ($response->denegada) {
            $nfce->update([
                'status' => PdvVendaNfce::STATUS_DENEGADA,
                'motivo_rejeicao' => $this->normalizarMotivoRejeicao(
                    $this->motivoConsulta($response, 'Uso denegado pela SEFAZ'),
                ),
            ]);

            return $nfce->fresh() ?? $nfce;
        }

        $definitivo = in_array($statusAnterior, [PdvVendaNfce::STATUS_AUTORIZADA, PdvVendaNfce::STATUS_CANCELADA], true);

        if ($definitivo) {
            throw new FiscalEngineException(
                'SEFAZ não confirmou a situação desta NFC-e ('.$this->motivoConsulta($response, 'sem retorno').'). '
                .'Status local mantido; confira o ambiente (produção/homologação) e a chave.'
            );
        }

        $motivo = $response->statusCodigo === self::CSTAT_NAO_CONSTA
            ? match ($statusAnterior) {
                PdvVendaNfce::STATUS_CONTINGENCIA => 'Ainda não consta na SEFAZ (cStat 217). Transmita a contingência (F5).',
                default => 'Não consta na SEFAZ (cStat 217): a nota não foi recebida. Transmita (F5).',
            }
            : 'Consulta SEFAZ: '.$this->motivoConsulta($response, 'retorno inconclusivo').'. Status local mantido.';

        $nfce->update(['motivo_rejeicao' => $this->normalizarMotivoRejeicao($motivo)]);

        return $nfce->fresh() ?? $nfce;
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosDoProtocolo(PdvVendaNfce $nfce, ?string $protNFe, string $protocoloConsulta): array
    {
        $updates = ['motivo_rejeicao' => null];

        if (blank($nfce->protocolo)) {
            $protocolo = $protocoloConsulta !== ''
                ? $protocoloConsulta
                : ($protNFe !== null ? NfceXmlProtocolo::protocoloDoProtocolo($protNFe) : '');

            if ($protocolo !== '') {
                $updates['protocolo'] = $protocolo;
            }
        }

        if (NfceXmlProtocolo::temProtocolo($nfce->xml) || $protNFe === null) {
            return $updates;
        }

        $nfe = NfceXmlProtocolo::nfe($nfce->xml);

        if ($nfe === null) {
            return $updates;
        }

        $chaveConfere = NfceXmlProtocolo::chaveDoProtocolo($protNFe) === (string) $nfce->chave;

        if ($chaveConfere && NfceXmlProtocolo::digestConfere($nfe, $protNFe)) {
            $updates['xml'] = NfceXmlProtocolo::montarNfeProc($nfe, $protNFe);
        } else {
            $updates['motivo_rejeicao'] = 'Autorizada na SEFAZ, mas o XML gravado difere do autorizado (digVal). '
                .'Baixe o XML autorizado no portal da SEFAZ.';
        }

        return $updates;
    }

    private function motivoConsulta(ConsultarNfceResponse $response, string $padrao): string
    {
        $motivo = trim($response->statusMotivo);
        $codigo = trim($response->statusCodigo);

        if ($motivo === '' && $codigo === '') {
            return $padrao;
        }

        return trim($motivo.($codigo !== '' ? ' [cStat '.$codigo.']' : ''));
    }

    private function normalizarMotivoRejeicao(?string $motivo): ?string
    {
        $motivo = trim((string) $motivo);
        if ($motivo === '') {
            return null;
        }

        return mb_substr($motivo, 0, self::MOTIVO_REJEICAO_MAX, 'UTF-8');
    }
}
