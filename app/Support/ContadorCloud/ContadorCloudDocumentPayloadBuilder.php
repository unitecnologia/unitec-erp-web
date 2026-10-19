<?php

namespace App\Support\ContadorCloud;

use App\Models\Compra;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\Nfse;
use App\Models\NotaFornecedor;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Nfce\NfceConsumidorIdentificado;

final class ContadorCloudDocumentPayloadBuilder
{
    public const EVENTO_AUTORIZADO = 'autorizado';

    public const EVENTO_CANCELADO = 'cancelado';

    public const EVENTO_CONTINGENCIA = 'contingencia';

    public const TIPO_NFE_SAIDA = 'nfe_saida';

    public const TIPO_NFE_ENTRADA = 'nfe_entrada';

    public const TIPO_NFCE_SAIDA = 'nfce_saida';

    public const TIPO_NFSE = 'nfse';

    public const TIPO_COMPRA_ENTRADA = 'compra_entrada';

    public const TIPO_NOTA_FORNECEDOR = 'nota_fornecedor_entrada';

    public const PORTAL_TIPO_NF_EMITIDA = 'NF_EMITIDA';

    public const PORTAL_TIPO_NF_CANCELADA = 'NF_CANCELADA';

    public const PORTAL_TIPO_NF_CONTINGENCIA = 'NF_CONTINGENCIA';

    public const PORTAL_TIPO_NFSE_EMITIDA = 'NFSE_EMITIDA';

    public const PORTAL_TIPO_NFSE_CANCELADA = 'NFSE_CANCELADA';

    public const PORTAL_TIPO_XML_COMPRA = 'XML_COMPRA';

    /**
     * Monta o JSON esperado pela API do portal.
     *
     * @return array<string, mixed>
     */
    public function buildEnvelope(ContadorCloudConfig $config, array $documento): array
    {
        $tipoInterno = (string) ($documento['tipo'] ?? '');
        $evento = (string) ($documento['evento'] ?? self::EVENTO_AUTORIZADO);
        $dataEmissao = (string) ($documento['data_emissao'] ?? '');
        $chave = $this->normalizeChaveAcesso((string) ($documento['chave'] ?? ''), $tipoInterno);
        $numero = (string) ($documento['numero'] ?? '');
        $xml = $this->resolveXmlContent($documento);
        $modelo = (string) ($documento['modelo'] ?? '');
        $tpEmis = (int) ($documento['tp_emis'] ?? 1);
        $contingencia = (bool) ($documento['contingencia'] ?? false)
            || $evento === self::EVENTO_CONTINGENCIA
            || $tpEmis === 9;

        $portalTipo = $this->resolvePortalTipo($tipoInterno, $evento);

        $payload = [
            'cnpj' => $this->formatCnpj($this->resolveCnpjEmpresa($tipoInterno, $documento)),
            'tipo' => $portalTipo,
            'numero' => $numero,
            'dataEmissao' => $dataEmissao !== '' ? $dataEmissao : now()->format('Y-m-d'),
            'competencia' => $this->resolveCompetencia($documento, $dataEmissao),
            // Portal (Zod) aceita só "55"|"65" em modelo; NFS-e se identifica por tipo=NFSE_*.
            'modelo' => match ($tipoInterno) {
                self::TIPO_NFCE_SAIDA => '65',
                self::TIPO_NFSE => '55',
                default => $modelo !== '' ? $modelo : '55',
            },
            'contingencia' => $contingencia,
            'tpEmis' => $tpEmis > 0 ? $tpEmis : 1,
            'status' => (string) ($documento['status'] ?? match ($evento) {
                self::EVENTO_CANCELADO => 'cancelada',
                self::EVENTO_CONTINGENCIA => 'contingencia',
                default => 'autorizada',
            }),
        ];

        if ($chave !== '') {
            $payload['chaveAcesso'] = $chave;
        }

        if (filled($documento['motivo_contingencia'] ?? null)) {
            $payload['motivoContingencia'] = trim((string) $documento['motivo_contingencia']);
        }

        // Card "Canceladas NF-e/NFC-e" no portal: enviar valor explícito (aliases)
        // porque o XML de evento de cancelamento não contém <vNF>.
        if (array_key_exists('valor_total', $documento) && is_numeric($documento['valor_total'])) {
            $valor = round((float) $documento['valor_total'], 2);
            $payload['valorTotal'] = $valor;
            $payload['valor'] = $valor;
            $payload['valorNota'] = $valor;
        }

        if ($xml !== '') {
            $payload['xmlContent'] = $xml;
            $payload['nomeArquivo'] = $this->buildNomeArquivo($chave, $numero);
        }

        // Se houver XML do evento de cancelamento separado do XML autorizado, envia também.
        if (filled($documento['xml_cancelamento_base64'] ?? null)) {
            $canc = base64_decode((string) $documento['xml_cancelamento_base64'], true);
            if (is_string($canc) && $canc !== '') {
                $payload['xmlCancelamento'] = $canc;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function fromNfe(Nfe $nfe, Empresa $empresa, string $evento): array
    {
        $nfe->loadMissing('cliente');

        $isEntrada = (string) ($nfe->movimento ?? '1') === '0';
        $tipo = $isEntrada ? self::TIPO_NFE_ENTRADA : self::TIPO_NFE_SAIDA;
        $tpEmis = (int) ltrim((string) ($nfe->tipo_emissao ?: '1'), '0') ?: 1;
        $emContingencia = $evento === self::EVENTO_CONTINGENCIA
            || ($evento !== self::EVENTO_CANCELADO && $nfe->status === Nfe::STATUS_CONTINGENCIA);
        // Cancelada: XML autorizado no xmlContent (tem <vNF> para o total do portal).
        // Evento de cancelamento vai em xml_cancelamento_base64 (campo xmlCancelamento).
        $xmlAutorizado = (string) ($nfe->xml ?? '');
        $xmlCancelamento = (string) ($nfe->xml_cancelamento ?? '');
        $xml = $evento === self::EVENTO_CANCELADO
            ? ($xmlAutorizado !== '' ? $xmlAutorizado : $xmlCancelamento)
            : $xmlAutorizado;

        $status = match (true) {
            $evento === self::EVENTO_CANCELADO => 'cancelada',
            $emContingencia => 'contingencia',
            default => 'autorizada',
        };

        $doc = $this->documentoBase(
            tipo: $tipo,
            evento: $evento,
            chave: (string) $nfe->chave,
            numero: (string) $nfe->numero,
            serie: (string) $nfe->serie,
            modelo: (string) ($nfe->modelo ?: '55'),
            dataEmissao: optional($nfe->data_emissao)?->format('Y-m-d'),
            dataEntrada: optional($nfe->data_saida)?->format('Y-m-d'),
            cnpjEmitente: $this->onlyDigits((string) $empresa->cnpj),
            cnpjParceiro: $this->onlyDigits((string) ($nfe->cliente?->cpf_cnpj ?? '')),
            nomeParceiro: (string) ($nfe->cliente?->nome_razao ?? ''),
            valorTotal: (float) $nfe->total,
            status: $status,
            protocolo: (string) ($nfe->protocolo ?? ''),
            protocoloCancelamento: (string) ($nfe->protocolo_cancelamento ?? ''),
            xml: $xml,
            referenciaType: 'nfe',
            referenciaId: (int) $nfe->id,
            tpEmis: $emContingencia ? 9 : $tpEmis,
            contingencia: $emContingencia,
            motivoContingencia: $emContingencia ? (string) ($nfe->motivo_contingencia ?? '') : '',
        );

        if ($evento === self::EVENTO_CANCELADO && $xmlCancelamento !== '' && $xmlAutorizado !== '') {
            $doc['xml_cancelamento_base64'] = base64_encode($xmlCancelamento);
        }

        return $doc;
    }

    /**
     * @return array<string, mixed>
     */
    public function fromNfce(PdvVendaNfce $nfce, Empresa $empresa, string $evento): array
    {
        $xmlAutorizado = (string) ($nfce->xml ?? '');
        $xmlCancelamento = (string) ($nfce->xml_cancelamento ?? '');
        $xml = $evento === self::EVENTO_CANCELADO
            ? ($xmlAutorizado !== '' ? $xmlAutorizado : $xmlCancelamento)
            : $xmlAutorizado;

        $nfce->loadMissing('pdvVenda.person');
        $venda = $nfce->pdvVenda;
        $consumidor = $venda ? NfceConsumidorIdentificado::resolvePerson($venda) : null;
        $nomeParceiro = ($venda ? NfceConsumidorIdentificado::nomeDaVenda($venda, $consumidor) : null) ?: 'CONSUMIDOR';
        $tpEmis = (int) ltrim((string) ($nfce->tipo_emissao ?: '1'), '0') ?: 1;
        // Contingência "aberta" só enquanto status/evento forem de contingência.
        // Após autorizar (mesmo com tpEmis=9 no XML), o portal deve receber emitida sem flag de contingência.
        $emContingencia = $evento === self::EVENTO_CONTINGENCIA
            || ($evento !== self::EVENTO_CANCELADO
                && $nfce->status === PdvVendaNfce::STATUS_CONTINGENCIA);

        $status = match (true) {
            $evento === self::EVENTO_CANCELADO => 'cancelada',
            $emContingencia => 'contingencia',
            default => 'autorizada',
        };

        $doc = $this->documentoBase(
            tipo: self::TIPO_NFCE_SAIDA,
            evento: $evento,
            chave: (string) $nfce->chave,
            numero: (string) $nfce->numero,
            serie: (string) $nfce->serie,
            modelo: (string) ($nfce->modelo ?: '65'),
            dataEmissao: optional($nfce->autorizada_em)?->format('Y-m-d'),
            dataEntrada: null,
            cnpjEmitente: $this->onlyDigits((string) $empresa->cnpj),
            cnpjParceiro: $venda ? NfceConsumidorIdentificado::cpfDigits($venda->cpf_nota) : '',
            nomeParceiro: $nomeParceiro,
            valorTotal: (float) ($venda?->total ?? 0),
            status: $status,
            protocolo: (string) ($nfce->protocolo ?? ''),
            protocoloCancelamento: (string) ($nfce->protocolo_cancelamento ?? ''),
            xml: $xml,
            referenciaType: 'pdv_venda_nfce',
            referenciaId: (int) $nfce->id,
            tpEmis: $emContingencia ? 9 : $tpEmis,
            contingencia: $emContingencia,
            motivoContingencia: $emContingencia ? (string) ($nfce->motivo_contingencia ?? '') : '',
        );

        if ($evento === self::EVENTO_CANCELADO && $xmlCancelamento !== '' && $xmlAutorizado !== '') {
            $doc['xml_cancelamento_base64'] = base64_encode($xmlCancelamento);
        }

        return $doc;
    }

    /**
     * @return array<string, mixed>
     */
    public function fromNotaFornecedor(NotaFornecedor $nota, Empresa $empresa, ?string $xml = null): array
    {
        return $this->documentoBase(
            tipo: self::TIPO_NOTA_FORNECEDOR,
            evento: self::EVENTO_AUTORIZADO,
            chave: (string) $nota->chave,
            numero: (string) $nota->numero,
            serie: $this->serieFromChave((string) $nota->chave),
            modelo: '55',
            dataEmissao: optional($nota->data_emissao)?->format('Y-m-d'),
            dataEntrada: optional($nota->data_entrada)?->format('Y-m-d'),
            cnpjEmitente: $this->onlyDigits((string) ($nota->cnpj ?? '')),
            cnpjParceiro: $this->onlyDigits((string) $empresa->cnpj),
            nomeParceiro: (string) $nota->nome,
            valorTotal: (float) $nota->total,
            status: (string) $nota->status,
            protocolo: '',
            protocoloCancelamento: '',
            xml: (string) ($xml ?? ''),
            referenciaType: 'nota_fornecedor',
            referenciaId: (int) $nota->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function fromCompra(Compra $compra, Empresa $empresa): array
    {
        $compra->loadMissing('fornecedor');

        return $this->documentoBase(
            tipo: self::TIPO_COMPRA_ENTRADA,
            evento: self::EVENTO_AUTORIZADO,
            chave: (string) $compra->chave_nfe,
            numero: (string) ($compra->numero_nota ?: $compra->numero),
            serie: $this->serieFromChave((string) $compra->chave_nfe),
            modelo: '55',
            dataEmissao: optional($compra->data_emissao)?->format('Y-m-d'),
            dataEntrada: optional($compra->data_entrada)?->format('Y-m-d'),
            cnpjEmitente: $this->onlyDigits((string) ($compra->fornecedor?->cpf_cnpj ?? '')),
            cnpjParceiro: $this->onlyDigits((string) $empresa->cnpj),
            nomeParceiro: (string) ($compra->fornecedor?->nome_razao ?? ''),
            valorTotal: (float) $compra->total,
            status: (string) $compra->status,
            protocolo: '',
            protocoloCancelamento: '',
            xml: '',
            referenciaType: 'compra',
            referenciaId: (int) $compra->id,
        );
    }

    /**
     * NFS-e Nacional (SEFIN) e IPM — XML em xml_nfse; cancelamento em xml_cancelamento.
     *
     * @return array<string, mixed>
     */
    public function fromNfse(Nfse $nfse, Empresa $empresa, string $evento): array
    {
        $xmlAutorizado = (string) ($nfse->xml_nfse ?? '');
        $xmlCancelamento = (string) ($nfse->xml_cancelamento ?? '');
        $xml = $evento === self::EVENTO_CANCELADO
            ? ($xmlAutorizado !== '' ? $xmlAutorizado : $xmlCancelamento)
            : $xmlAutorizado;

        // Nacional: chave numérica; IPM: código de verificação (pode ter letras).
        $chave = $this->normalizeChaveAcesso(
            (string) ($nfse->chave_acesso ?: $nfse->chave ?: ''),
            self::TIPO_NFSE,
        );
        $numero = (string) ($nfse->numero_nfse ?: $nfse->numero_dps ?: '');
        $dataEmissao = optional($nfse->data_emissao)?->format('Y-m-d')
            ?: $this->competenciaToDate((string) ($nfse->competencia ?? ''));

        $status = $evento === self::EVENTO_CANCELADO ? 'cancelada' : 'autorizada';

        $doc = $this->documentoBase(
            tipo: self::TIPO_NFSE,
            evento: $evento,
            chave: $chave,
            numero: $numero,
            serie: (string) ($nfse->serie_dps ?: ''),
            modelo: '55',
            dataEmissao: $dataEmissao,
            dataEntrada: null,
            cnpjEmitente: $this->onlyDigits((string) $empresa->cnpj),
            cnpjParceiro: $this->onlyDigits((string) ($nfse->tomador_cpf_cnpj ?? '')),
            nomeParceiro: (string) ($nfse->tomador_nome ?? ''),
            valorTotal: (float) ($nfse->total ?? 0),
            status: $status,
            protocolo: (string) ($nfse->protocolo ?? ''),
            protocoloCancelamento: '',
            xml: $xml,
            referenciaType: 'nfse',
            referenciaId: (int) $nfse->id,
        );

        $competencia = trim((string) ($nfse->competencia ?? ''));
        if ($competencia !== '') {
            $doc['competencia'] = $this->normalizeCompetencia($competencia) ?? $competencia;
        }

        if ($evento === self::EVENTO_CANCELADO && $xmlCancelamento !== '' && $xmlAutorizado !== '') {
            $doc['xml_cancelamento_base64'] = base64_encode($xmlCancelamento);
        }

        return $doc;
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function resolveCnpjEmpresa(string $tipoInterno, array $documento): string
    {
        return match ($tipoInterno) {
            self::TIPO_NOTA_FORNECEDOR,
            self::TIPO_COMPRA_ENTRADA,
            self::TIPO_NFE_ENTRADA => (string) ($documento['cnpj_destinatario'] ?? ''),
            default => (string) ($documento['cnpj_emitente'] ?? ''),
        };
    }

    private function resolvePortalTipo(string $tipoInterno, string $evento): string
    {
        if ($tipoInterno === self::TIPO_NFSE) {
            return $evento === self::EVENTO_CANCELADO
                ? self::PORTAL_TIPO_NFSE_CANCELADA
                : self::PORTAL_TIPO_NFSE_EMITIDA;
        }

        if ($evento === self::EVENTO_CANCELADO) {
            return self::PORTAL_TIPO_NF_CANCELADA;
        }

        if ($evento === self::EVENTO_CONTINGENCIA) {
            return self::PORTAL_TIPO_NF_CONTINGENCIA;
        }

        return match ($tipoInterno) {
            self::TIPO_NOTA_FORNECEDOR,
            self::TIPO_COMPRA_ENTRADA,
            self::TIPO_NFE_ENTRADA => self::PORTAL_TIPO_XML_COMPRA,
            default => self::PORTAL_TIPO_NF_EMITIDA,
        };
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function resolveXmlContent(array $documento): string
    {
        if (! filled($documento['xml_base64'] ?? null)) {
            return '';
        }

        $decoded = base64_decode((string) $documento['xml_base64'], true);

        return $decoded !== false ? $decoded : '';
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function resolveCompetencia(array $documento, string $dataEmissao): string
    {
        $fromDoc = $this->normalizeCompetencia((string) ($documento['competencia'] ?? ''));
        if ($fromDoc !== null) {
            return $fromDoc;
        }

        return $this->competenciaFromDate($dataEmissao);
    }

    private function normalizeCompetencia(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^(\d{2})\/(\d{4})$/', $value, $matches)) {
            return $matches[2].'-'.$matches[1];
        }

        return null;
    }

    private function competenciaToDate(string $competencia): ?string
    {
        $norm = $this->normalizeCompetencia($competencia);

        return $norm !== null ? $norm.'-01' : null;
    }

    private function competenciaFromDate(?string $date): string
    {
        if ($date !== null && $date !== '' && preg_match('/^(\d{4}-\d{2})/', $date, $matches)) {
            return $matches[1];
        }

        return now()->format('Y-m');
    }

    private function buildNomeArquivo(string $chave, string $numero): string
    {
        $prefix = strlen($chave) >= 5 ? substr($chave, 0, 5) : 'doc';
        $numeroLimpo = preg_replace('/\D/', '', $numero) ?: '0';

        return $prefix.'_NF'.$numeroLimpo.'.xml';
    }

    /**
     * @return array<string, mixed>
     */
    private function documentoBase(
        string $tipo,
        string $evento,
        string $chave,
        string $numero,
        string $serie,
        string $modelo,
        ?string $dataEmissao,
        ?string $dataEntrada,
        string $cnpjEmitente,
        string $cnpjParceiro,
        string $nomeParceiro,
        float $valorTotal,
        string $status,
        string $protocolo,
        string $protocoloCancelamento,
        string $xml,
        string $referenciaType,
        int $referenciaId,
        int $tpEmis = 1,
        bool $contingencia = false,
        string $motivoContingencia = '',
    ): array {
        return [
            'tipo' => $tipo,
            'evento' => $evento,
            'chave' => $this->normalizeChaveAcesso($chave, $tipo),
            'numero' => $numero,
            'serie' => $serie,
            'modelo' => $modelo,
            'data_emissao' => $dataEmissao,
            'data_entrada' => $dataEntrada,
            'cnpj_emitente' => $cnpjEmitente,
            'cnpj_destinatario' => $cnpjParceiro,
            'nome_parceiro' => mb_strtoupper(trim($nomeParceiro), 'UTF-8'),
            'valor_total' => round($valorTotal, 2),
            'status' => $status,
            'protocolo' => $protocolo,
            'protocolo_cancelamento' => $protocoloCancelamento,
            'xml_base64' => $xml !== '' ? base64_encode($xml) : null,
            'tp_emis' => $tpEmis,
            'contingencia' => $contingencia,
            'motivo_contingencia' => $motivoContingencia,
            'referencia' => [
                'tipo' => $referenciaType,
                'id' => $referenciaId,
            ],
        ];
    }

    private function formatCnpj(string $value): string
    {
        $digits = $this->onlyDigits($value);

        if (strlen($digits) !== 14) {
            return $value;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    /**
     * NF-e/NFC-e: só dígitos (44). NFS-e: preserva alfanumérico (IPM) e chave nacional.
     */
    private function normalizeChaveAcesso(string $value, string $tipoInterno): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if ($tipoInterno === self::TIPO_NFSE) {
            return preg_replace('/\s+/', '', $value) ?? $value;
        }

        return $this->onlyDigits($value);
    }

    private function serieFromChave(string $chave): string
    {
        $digits = $this->onlyDigits($chave);

        if (strlen($digits) !== 44) {
            return '';
        }

        return ltrim(substr($digits, 22, 3), '0');
    }
}
