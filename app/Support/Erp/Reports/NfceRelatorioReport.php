<?php

namespace App\Support\Erp\Reports;

use App\Models\PdvVendaNfce;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Collection;

class NfceRelatorioReport
{
    public const TIPO_RESUMIDO = 'resumido';

    public const TIPO_DETALHADO = 'detalhado';

    public const TIPO_TRIBUTACAO = 'tributacao';

    /**
     * @return array<string, string>
     */
    public static function tipoLabels(): array
    {
        return [
            self::TIPO_RESUMIDO => 'Resumido',
            self::TIPO_DETALHADO => 'Detalhado',
            self::TIPO_TRIBUTACAO => 'Tipo de tributação',
        ];
    }

    public static function normalizeTipo(string $tipo): string
    {
        return array_key_exists($tipo, self::tipoLabels()) ? $tipo : self::TIPO_DETALHADO;
    }

    public static function reportTitle(string $tipo): string
    {
        $label = mb_strtoupper(self::tipoLabels()[self::normalizeTipo($tipo)], 'UTF-8');

        return 'RELATÓRIO DE NFC-e | '.$label;
    }

    public static function statusLabel(string $tab): string
    {
        $labels = PdvVendaNfce::tabLabels();
        $label = $labels[PdvVendaNfce::normalizeTabFilter($tab)] ?? 'Transmitidos';

        return match (PdvVendaNfce::normalizeTabFilter($tab)) {
            PdvVendaNfce::TAB_TRANSMITIDOS => 'TRANSMITIDO',
            PdvVendaNfce::TAB_CANCELADOS => 'CANCELADO',
            PdvVendaNfce::TAB_CONTINGENCIA => 'CONTINGÊNCIA',
            PdvVendaNfce::TAB_DENEGADO => 'DENEGADO',
            default => mb_strtoupper($label, 'UTF-8'),
        };
    }

    public static function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public static function formatDate(mixed $value): string
    {
        if (! filled($value)) {
            return '';
        }

        return ErpTimezone::toLocal($value)->format('d/m/Y');
    }

    public static function formatNumero(mixed $numero): string
    {
        if ($numero === null || $numero === '') {
            return '';
        }

        return (string) (int) $numero;
    }

    public static function formatChaveAcesso(?string $chave): string
    {
        $digits = preg_replace('/\D/', '', (string) $chave) ?? '';

        if ($digits === '') {
            return '—';
        }

        return trim(chunk_split($digits, 4, ' '));
    }

    public static function formatProtocolo(?string $protocolo): string
    {
        $digits = preg_replace('/\D/', '', (string) $protocolo) ?? '';

        return $digits !== '' ? $digits : '—';
    }

    public static function nfceTotal(PdvVendaNfce $nfce): float
    {
        return (float) ($nfce->pdvVenda?->total ?? 0);
    }

    public static function nfceDataEmissao(PdvVendaNfce $nfce): string
    {
        $data = $nfce->autorizada_em ?? $nfce->pdvVenda?->fechado_em;

        return self::formatDate($data);
    }

    /**
     * @param  Collection<int, PdvVendaNfce>  $nfces
     * @return list<array{data: string, total: string, total_raw: float}>
     */
    public static function buildResumido(Collection $nfces): array
    {
        $grupos = [];

        foreach ($nfces as $nfce) {
            $data = self::nfceDataEmissao($nfce);

            if ($data === '') {
                continue;
            }

            $grupos[$data] = ($grupos[$data] ?? 0.0) + self::nfceTotal($nfce);
        }

        ksort($grupos);

        $rows = [];

        foreach ($grupos as $data => $total) {
            $rows[] = [
                'data' => $data,
                'total' => self::formatMoney($total),
                'total_raw' => $total,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, PdvVendaNfce>  $nfces
     * @return list<array{data: string, total: string, total_raw: float, itens: list<array<string, string>>}>
     */
    public static function buildDetalhado(Collection $nfces): array
    {
        $grupos = [];

        foreach ($nfces as $nfce) {
            $data = self::nfceDataEmissao($nfce);

            if ($data === '') {
                $data = '—';
            }

            $grupos[$data] ??= ['total_raw' => 0.0, 'itens' => []];
            $grupos[$data]['total_raw'] += self::nfceTotal($nfce);
            $grupos[$data]['itens'][] = [
                'numero' => self::formatNumero($nfce->numero),
                'emissao' => $data,
                'chave' => self::formatChaveAcesso((string) ($nfce->chave ?? '')),
                'protocolo' => self::formatProtocolo((string) ($nfce->protocolo ?? '')),
                'total' => self::formatMoney(self::nfceTotal($nfce)),
            ];
        }

        ksort($grupos);

        $sections = [];

        foreach ($grupos as $data => $grupo) {
            $sections[] = [
                'data' => $data,
                'total' => self::formatMoney($grupo['total_raw']),
                'total_raw' => $grupo['total_raw'],
                'itens' => $grupo['itens'],
            ];
        }

        return $sections;
    }

    /**
     * @param  Collection<int, PdvVendaNfce>  $nfces
     * @return list<array{cst: string, csosn: string, total: string, total_raw: float}>
     */
    public static function buildTributacao(Collection $nfces): array
    {
        $grupos = [];

        foreach ($nfces as $nfce) {
            foreach (self::extractTributacaoLinhas($nfce) as $linha) {
                $chave = $linha['cst'].'|'.$linha['csosn'];
                $grupos[$chave] = ($grupos[$chave] ?? 0.0) + $linha['total_raw'];
            }
        }

        ksort($grupos);

        $rows = [];

        foreach ($grupos as $chave => $total) {
            [$cst, $csosn] = array_pad(explode('|', $chave, 2), 2, '');
            $rows[] = [
                'cst' => $cst,
                'csosn' => $csosn,
                'total' => self::formatMoney($total),
                'total_raw' => $total,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{cst: string, csosn: string, total_raw: float}>
     */
    protected static function extractTributacaoLinhas(PdvVendaNfce $nfce): array
    {
        $xml = trim((string) ($nfce->xml ?? ''));

        if ($xml !== '') {
            $fromXml = self::extractTributacaoFromXml($xml);

            if ($fromXml !== []) {
                return $fromXml;
            }
        }

        $nfce->loadMissing('pdvVenda.itens.product');
        $linhas = [];

        foreach ($nfce->pdvVenda?->itens ?? [] as $item) {
            $cst = self::padTributo((string) ($item->product?->cst_icms ?? $item->product?->cst_saida ?? ''));
            $csosn = self::padTributo((string) ($item->product?->csosn ?? $item->product?->csosn_externo ?? ''));
            $linhas[] = [
                'cst' => $cst,
                'csosn' => $csosn,
                'total_raw' => (float) $item->total,
            ];
        }

        if ($linhas !== []) {
            return $linhas;
        }

        return [[
            'cst' => '',
            'csosn' => '',
            'total_raw' => self::nfceTotal($nfce),
        ]];
    }

    /**
     * @return list<array{cst: string, csosn: string, total_raw: float}>
     */
    protected static function extractTributacaoFromXml(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false) {
            return [];
        }

        $linhas = [];

        foreach ($document->xpath('//*[local-name()="det"]') ?: [] as $det) {
            $total = (float) ($det->xpath('.//*[local-name()="vProd"]')[0] ?? 0);
            $cst = '';
            $csosn = '';

            foreach ($det->xpath('.//*[local-name()="CST"]') ?: [] as $node) {
                $cst = self::padTributo((string) $node);
            }

            foreach ($det->xpath('.//*[local-name()="CSOSN"]') ?: [] as $node) {
                $csosn = self::padTributo((string) $node);
            }

            $linhas[] = [
                'cst' => $cst,
                'csosn' => $csosn,
                'total_raw' => $total,
            ];
        }

        return $linhas;
    }

    protected static function padTributo(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        return str_pad($digits, strlen($digits) >= 3 ? 3 : 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  Collection<int, PdvVendaNfce>  $nfces
     */
    public static function grandTotal(Collection $nfces): float
    {
        return (float) $nfces->sum(fn (PdvVendaNfce $nfce): float => self::nfceTotal($nfce));
    }

    public static function periodSummary(?string $de, ?string $ate): ?string
    {
        $parts = array_filter([
            filled($de) ? 'de '.self::formatDate($de) : null,
            filled($ate) ? 'até '.self::formatDate($ate) : null,
        ]);

        return $parts !== [] ? 'PERÍODO: '.implode(' ', $parts) : null;
    }
}
