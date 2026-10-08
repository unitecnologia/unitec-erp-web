<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use Unitec\FiscalEngine\Dto\ConsultarNfceResponse;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class PdvNfceTransmissaoService
{
    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly PdvNfceEmissionService $emissionService = new PdvNfceEmissionService(),
        private readonly PdvNfceConsultaService $consultaService = new PdvNfceConsultaService(),
    ) {}

    /**
     * @param  (callable(int, string): void)|null  $onProgress
     */
    public function transmitir(PdvVendaNfce $nfce, Empresa $empresa, ?callable $onProgress = null): PdvVendaNfce
    {
        $vendaId = (int) (PdvVenda::query()->whereKey($nfce->pdv_venda_id)->value('venda_id') ?? 0);

        if ($vendaId <= 0) {
            return $this->transmitirSemTrava($nfce, $empresa, $onProgress);
        }

        return VendaFiscalLock::executar($vendaId, function () use ($nfce, $empresa, $onProgress, $vendaId): PdvVendaNfce {
            $nfce->refresh();

            $temNfe = Nfe::query()
                ->where('venda_id', $vendaId)
                ->whereIn('status', [Nfe::STATUS_TRANSMITIDA, Nfe::STATUS_CONTINGENCIA])
                ->exists();

            if ($temNfe) {
                throw new FiscalEngineException('A venda vinculada já possui NF-e transmitida. Não é possível transmitir a NFC-e.');
            }

            return $this->transmitirSemTrava($nfce, $empresa, $onProgress);
        });
    }

    /**
     * @param  (callable(int, string): void)|null  $onProgress
     */
    private function transmitirSemTrava(PdvVendaNfce $nfce, Empresa $empresa, ?callable $onProgress = null): PdvVendaNfce
    {
        if ($nfce->simulada) {
            throw new FiscalEngineException('NFC-e simulada não pode ser transmitida à SEFAZ.');
        }

        $parametros = VendasParametro::forEmpresa((int) $empresa->id);

        if (! $this->payloadBuilder->podeOperarReal($parametros, $empresa)) {
            throw new FiscalEngineException('Transmissão real de NFC-e não está configurada para esta empresa/UF.');
        }

        return match ($nfce->status) {
            PdvVendaNfce::STATUS_CONTINGENCIA => $this->transmitirContingencia($nfce, $empresa, $parametros, $onProgress),
            PdvVendaNfce::STATUS_PENDENTE,
            PdvVendaNfce::STATUS_REJEITADA,
            PdvVendaNfce::STATUS_DUPLICIDADE => $this->emitirPendente($nfce, $empresa, $parametros, $onProgress),
            PdvVendaNfce::STATUS_DENEGADA => throw new FiscalEngineException(
                'NFC-e nº '.$nfce->numero.' com uso denegado pela SEFAZ: o número fica consumido e não pode ser retransmitido nem inutilizado. Regularize o cadastro e emita outro documento.'
            ),
            default => throw new FiscalEngineException('Somente NFC-e em contingência, gravada, rejeitada ou em duplicidade pode ser transmitida.'),
        };
    }

    /**
     * @param  (callable(int, string): void)|null  $onProgress
     */
    private function transmitirContingencia(
        PdvVendaNfce $nfce,
        Empresa $empresa,
        VendasParametro $parametros,
        ?callable $onProgress = null,
    ): PdvVendaNfce {
        $nfce->loadMissing('pdvVenda');
        $venda = $nfce->pdvVenda;

        if (! $venda instanceof PdvVenda) {
            throw new FiscalEngineException('NFC-e em contingência sem venda vinculada para transmissão.');
        }

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_VALIDAR, 'nfce');
        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_XML, 'nfce');
        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_ASSINAR, 'nfce');
        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_SEFAZ, 'nfce');

        try {
            $response = $this->emissionService->autorizarContingencia($nfce, $venda, $empresa, $parametros);
        } catch (FiscalEngineException $exception) {
            // 204: a contingência já foi recebida (reenvio após timeout) — recupera o protocolo.
            if ($exception->sefazCodigo === '204') {
                $consulta = $this->emissionService->consultarSemFalhar($nfce->chave, $empresa, $parametros);

                if ($consulta !== null && $consulta->autorizada) {
                    $nfce = $this->consultaService->aplicarResultado($nfce, $empresa, $consulta);

                    if ($nfce->status === PdvVendaNfce::STATUS_AUTORIZADA) {
                        return $nfce;
                    }
                }
            }

            // 539: o número pode ter sido autorizado pela tentativa normal anterior (antes da contingência).
            if ($exception->sefazCodigo === '539') {
                $conflitante = NfceXmlProtocolo::chaveConflitante(
                    $exception->getMessage().' '.$exception->sefazMotivo,
                    (string) $nfce->chave,
                );
                $avaliacao = $this->emissionService->avaliarConflito539($nfce, $conflitante, $empresa, $parametros);

                if ($avaliacao['nfce'] !== null) {
                    return $avaliacao['nfce'];
                }

                $nfce->update([
                    'motivo_rejeicao' => mb_substr(
                        $this->emissionService->motivo539('cStat 539: '.trim((string) $exception->sefazMotivo), $conflitante, $avaliacao),
                        0,
                        2000,
                        'UTF-8',
                    ),
                ]);
            }

            throw $exception;
        }

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_AUTORIZACAO, 'nfce');

        if ($response->chave !== (string) $nfce->chave) {
            throw new FiscalEngineException(
                'Autorização devolveu chave '.$response->chave.' diferente da contingência '.$nfce->chave.'. Registro não alterado; use F4.'
            );
        }

        $nfce->update([
            'status' => PdvVendaNfce::STATUS_AUTORIZADA,
            'protocolo' => $response->protocolo,
            'xml' => $response->xml,
            'qr_code_conteudo' => $response->qrCodeUrl,
            'tipo_emissao' => '9',
            'motivo_rejeicao' => null,
            'motivo_contingencia' => NfceContingenciaJustificativa::normalize((string) $nfce->motivo_contingencia),
            'autorizada_em' => $nfce->autorizada_em ?? now(),
        ]);

        $nfce = $nfce->fresh() ?? $nfce;

        (new ContadorCloudPortalHookService())->onNfceAutorizada($nfce, $empresa);

        return $nfce;
    }

    /**
     * Antes de reenviar, confirma na SEFAZ a situação da chave já gravada. Nota pendente reenvia o
     * mesmo XML assinado; rejeitada é remontada com o mesmo número/cNF; após 539, novo número só
     * com conflito confirmado (a tentativa anterior é arquivada antes).
     *
     * @param  (callable(int, string): void)|null  $onProgress
     */
    private function emitirPendente(
        PdvVendaNfce $nfce,
        Empresa $empresa,
        VendasParametro $parametros,
        ?callable $onProgress = null,
    ): PdvVendaNfce {
        $nfce->loadMissing('pdvVenda');

        if (! $nfce->pdvVenda instanceof PdvVenda) {
            throw new FiscalEngineException('NFC-e sem venda vinculada para transmissão.');
        }

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_VALIDAR, 'nfce');

        $novoNumero = $this->decidirNumeracao($nfce, $empresa, $parametros);

        if ($novoNumero instanceof PdvVendaNfce) {
            return $novoNumero;
        }

        if (! $novoNumero && filled($nfce->chave)) {
            $consulta = $this->confirmarSituacao($nfce, $empresa, $parametros);

            if ($consulta->autorizada || $consulta->cancelada || $consulta->denegada) {
                $nfce = $this->consultaService->aplicarResultado($nfce, $empresa, $consulta);

                if ($nfce->status === PdvVendaNfce::STATUS_AUTORIZADA) {
                    return $nfce;
                }

                throw new FiscalEngineException('NFC-e consta na SEFAZ como '.$nfce->status.'; não foi reenviada.');
            }
        }

        if (! $novoNumero
            && $nfce->status === PdvVendaNfce::STATUS_PENDENTE
            && filled($nfce->chave)
            && NfceXmlProtocolo::tpEmis(NfceXmlProtocolo::nfe($nfce->xml)) === 1) {
            return $this->emissionService->enviarRegistro($nfce, $empresa, $parametros, $onProgress);
        }

        return $this->emissionService->reemitirRegistro($nfce, $empresa, $parametros, $novoNumero, $onProgress);
    }

    /**
     * Só libera reenvio com resposta conclusiva: autorizada/cancelada/denegada ou "não consta" (217).
     */
    private function confirmarSituacao(PdvVendaNfce $nfce, Empresa $empresa, VendasParametro $parametros): ConsultarNfceResponse
    {
        try {
            $consulta = $this->consultaService->consultarChave((string) $nfce->chave, $empresa, $parametros);
        } catch (FiscalEngineException $exception) {
            throw new FiscalEngineException(
                'Não foi possível confirmar na SEFAZ a situação da chave '.$nfce->chave
                .' ('.trim($exception->getMessage()).'). Nada foi reenviado; tente novamente.'
            );
        }

        if ($consulta->autorizada || $consulta->cancelada || $consulta->denegada
            || $consulta->statusCodigo === PdvNfceConsultaService::CSTAT_NAO_CONSTA) {
            return $consulta;
        }

        throw new FiscalEngineException(
            'Consulta SEFAZ inconclusiva para a chave '.$nfce->chave
            .' ('.trim($consulta->statusMotivo.' cStat '.$consulta->statusCodigo).'). Nada foi reenviado.'
        );
    }

    /**
     * true = novo número (conflito confirmado); false = mesmo número; PdvVendaNfce = a chave citada na
     * 539 era a tentativa original desta venda e foi restaurada. Após 539, o conflito é verificado
     * de novo agora: sem confirmação, nada é reenviado e a tentativa permanece preservada.
     */
    private function decidirNumeracao(PdvVendaNfce $nfce, Empresa $empresa, VendasParametro $parametros): bool|PdvVendaNfce
    {
        if (blank($nfce->numero) || $this->numeroUsadoNoErp($nfce)) {
            return true;
        }

        $motivo = (string) $nfce->motivo_rejeicao;

        if (! in_array((string) $nfce->status, [PdvVendaNfce::STATUS_REJEITADA, PdvVendaNfce::STATUS_DUPLICIDADE], true)
            || ! str_contains($motivo, 'cStat 539')) {
            return false;
        }

        $conflitante = NfceXmlProtocolo::chaveConflitante($motivo, (string) $nfce->chave);
        $avaliacao = $this->emissionService->avaliarConflito539($nfce, $conflitante, $empresa, $parametros);

        if ($avaliacao['nfce'] !== null) {
            return $avaliacao['nfce'];
        }

        $nfce->update([
            'motivo_rejeicao' => mb_substr($this->emissionService->motivo539($motivo, $conflitante, $avaliacao), 0, 2000, 'UTF-8'),
        ]);

        if ($avaliacao['confirmado'] === null) {
            throw new FiscalEngineException(
                'Rejeição 539 com conflito de numeração não confirmado: '.$avaliacao['detalhe']
                .'. A tentativa foi preservada e nada foi reenviado; transmita novamente (F5) quando a SEFAZ responder.'
            );
        }

        return $avaliacao['confirmado'];
    }

    /** Mesmo número/série/ambiente em outra NFC-e do ERP que ocupa a numeração na SEFAZ. */
    private function numeroUsadoNoErp(PdvVendaNfce $nfce): bool
    {
        return PdvVendaNfce::query()
            ->whereKeyNot($nfce->id)
            ->where('empresa_id', $nfce->empresa_id)
            ->where('modelo', $nfce->modelo ?: '65')
            ->where('serie', $nfce->serie)
            ->where('numero', $nfce->numero)
            ->where('ambiente', $nfce->ambiente)
            ->where('simulada', false)
            ->whereNotIn('status', [
                PdvVendaNfce::STATUS_REJEITADA,
                PdvVendaNfce::STATUS_SIMULADA,
                PdvVendaNfce::STATUS_DUPLICIDADE,
                'inutilizada',
            ])
            ->exists();
    }
}
