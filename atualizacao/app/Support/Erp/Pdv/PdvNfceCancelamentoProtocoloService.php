<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Compra\CompraDanfeReportService;
use Illuminate\Support\Carbon;

final class PdvNfceCancelamentoProtocoloService
{
    public function __construct(
        private readonly CompraDanfeReportService $danfe = new CompraDanfeReportService(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(
        PdvVenda $venda,
        ?Empresa $empresa,
        string $usuario,
        bool $autoPrint = false,
        ?Carbon $printedAt = null,
    ): array {
        $venda->loadMissing('nfce');
        $documento = $venda->nfce;
        $printedAt ??= now();
        $canceladaEm = $documento?->cancelada_em ?? $printedAt;
        $protocolo = (string) ($documento?->protocolo_cancelamento ?? '');
        $chave = (string) ($documento?->chave ?? '');

        return [
            'venda' => $venda,
            'empresa' => $empresa,
            'usuario' => $usuario,
            'emitente' => $this->buildEmitente($empresa),
            'chaveFormatada' => $chave !== '' ? $this->danfe->formatChave($chave) : '—',
            'protocolo' => $protocolo,
            'protocoloFormatado' => $this->formatarProtocolo($protocolo),
            'numeroNf' => str_pad((string) ($documento?->numero ?? $venda->numero), 9, '0', STR_PAD_LEFT),
            'serie' => str_pad((string) ($documento?->serie ?? '1'), 3, '0', STR_PAD_LEFT),
            'dataCancelamento' => $canceladaEm->format('d/m/Y'),
            'horaCancelamento' => $canceladaEm->format('H:i:s'),
            'motivoEstorno' => trim((string) ($venda->motivo_estorno ?? '')),
            'autoPrint' => $autoPrint,
            'printedAt' => $printedAt,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildEmitente(?Empresa $empresa): array
    {
        if ($empresa === null) {
            return [
                'nome' => 'UNITEC',
                'fantasia' => 'UNITEC',
                'cnpj' => '',
                'ie' => '',
                'endereco' => '',
                'municipio' => '',
                'uf' => '',
            ];
        }

        $endereco = trim(implode(', ', array_filter([
            trim((string) ($empresa->endereco ?? '')),
            filled($empresa->numero) ? 'nº ' . $empresa->numero : null,
            trim((string) ($empresa->bairro ?? '')),
        ])));

        return [
            'nome' => mb_strtoupper((string) ($empresa->razao_social ?: $empresa->nome ?: $empresa->fantasia), 'UTF-8'),
            'fantasia' => mb_strtoupper((string) ($empresa->fantasia ?: $empresa->nome), 'UTF-8'),
            'cnpj' => $this->formatCnpj((string) $empresa->cnpj),
            'ie' => (string) ($empresa->ie ?? ''),
            'endereco' => mb_strtoupper($endereco, 'UTF-8'),
            'municipio' => mb_strtoupper((string) ($empresa->cidade ?? ''), 'UTF-8'),
            'uf' => (string) ($empresa->uf ?? ''),
        ];
    }

    private function formatCnpj(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 14) {
            return $value;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
    }

    private function formatarProtocolo(string $protocolo): string
    {
        $digits = preg_replace('/\D/', '', $protocolo) ?? '';

        if (strlen($digits) < 15) {
            return $protocolo;
        }

        return substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3) . ' ' . substr($digits, 9);
    }
}
