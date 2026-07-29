<?php

namespace App\Support\ContadorCloud;

use App\Models\Compra;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NotaFornecedor;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Nfce\NfceConsumidorIdentificado;

final class ContadorCloudDocumentPayloadBuilder
{
    public const EVENTO_AUTORIZADO = 'autorizado';

    public const EVENTO_CANCELADO = 'cancelado';

    public const TIPO_NFE_SAIDA = 'nfe_saida';

    public const TIPO_NFE_ENTRADA = 'nfe_entrada';

    public const TIPO_NFCE_SAIDA = 'nfce_saida';

    public const TIPO_COMPRA_ENTRADA = 'compra_entrada';

    public const TIPO_NOTA_FORNECEDOR = 'nota_fornecedor_entrada';

    public const PORTAL_TIPO_NF_EMITIDA = 'NF_EMITIDA';

    public const PORTAL_TIPO_NF_CANCELADA = 'NF_CANCELADA';

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
        $chave = $this->onlyDigits((string) ($documento['chave'] ?? ''));
        $numero = (string) ($documento['numero'] ?? '');
        $xml = $this->resolveXmlContent($documento);

        $payload = [
            'cnpj' => $this->formatCnpj($this->resolveCnpjEmpresa($tipoInterno, $documento)),
            'tipo' => $this->resolvePortalTipo($tipoInterno, $evento),
            'numero' => $numero,
            'dataEmissao' => $dataEmissao !== '' ? $dataEmissao : now()->format('Y-m-d'),
            'competencia' => $this->competenciaFromDate($dataEmissao),
        ];

        if ($chave !== '') {
            $payload['chaveAcesso'] = $chave;
        }

        if ($xml !== '') {
            $payload['xmlContent'] = $xml;
            $payload['nomeArquivo'] = $this->buildNomeArquivo($chave, $numero);
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
        $xml = $evento === self::EVENTO_CANCELADO
            ? (string) ($nfe->xml_cancelamento ?? '')
            : (string) ($nfe->xml ?? '');

        return $this->documentoBase(
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
            status: $evento === self::EVENTO_CANCELADO ? 'cancelada' : 'autorizada',
            protocolo: (string) ($nfe->protocolo ?? ''),
            protocoloCancelamento: (string) ($nfe->protocolo_cancelamento ?? ''),
            xml: $xml,
            referenciaType: 'nfe',
            referenciaId: (int) $nfe->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function fromNfce(PdvVendaNfce $nfce, Empresa $empresa, string $evento): array
    {
        $xml = $evento === self::EVENTO_CANCELADO
            ? (string) ($nfce->xml_cancelamento ?? '')
            : (string) ($nfce->xml ?? '');

        $nfce->loadMissing('pdvVenda.person');
        $venda = $nfce->pdvVenda;
        $consumidor = $venda ? NfceConsumidorIdentificado::resolvePerson($venda) : null;
        $nomeParceiro = NfceConsumidorIdentificado::nome($consumidor) ?: 'CONSUMIDOR';

        return $this->documentoBase(
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
            status: $evento === self::EVENTO_CANCELADO ? 'cancelada' : 'autorizada',
            protocolo: (string) ($nfce->protocolo ?? ''),
            protocoloCancelamento: (string) ($nfce->protocolo_cancelamento ?? ''),
            xml: $xml,
            referenciaType: 'pdv_venda_nfce',
            referenciaId: (int) $nfce->id,
        );
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
        if ($evento === self::EVENTO_CANCELADO) {
            return self::PORTAL_TIPO_NF_CANCELADA;
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
    ): array {
        return [
            'tipo' => $tipo,
            'evento' => $evento,
            'chave' => $this->onlyDigits($chave),
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

    private function serieFromChave(string $chave): string
    {
        $digits = $this->onlyDigits($chave);

        if (strlen($digits) !== 44) {
            return '';
        }

        return ltrim(substr($digits, 22, 3), '0');
    }
}
