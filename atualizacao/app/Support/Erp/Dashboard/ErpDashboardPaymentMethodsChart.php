<?php

namespace App\Support\Erp\Dashboard;

use App\Models\ContaReceber;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\Venda;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ErpDashboardPaymentMethodsChart
{
    /** @var array<string, string> */
    private const COLORS = [
        'Dinheiro' => '#0f766e',
        'PIX' => '#1d4ed8',
        'Débito' => '#6366f1',
        'Crédito' => '#7c3aed',
        'Cartão' => '#8b5cf6',
        'Boleto' => '#d97706',
        'Cheque' => '#ca8a04',
        'Crediário' => '#db2777',
        'Depósito' => '#0284c7',
        'TEF' => '#4f46e5',
        'Troca' => '#64748b',
        'Outros' => '#94a3b8',
    ];

    /**
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string}
     */
    public static function data(): array
    {
        return static::fromDatabase() ?? [
            'labels' => [],
            'values' => [],
            'colors' => [],
            'unit' => 'money',
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string}|null
     */
    private static function fromDatabase(): ?array
    {
        try {
            $hoje = ErpFinanceiroMetricas::hoje();
            $inicio = $hoje->copy()->startOfMonth();
            $fim = $hoje;
            /** @var array<string, float> $totais */
            $totais = [];

            static::addPdvPagamentos($totais, $inicio, $fim);
            static::addVendasRetaguarda($totais, $inicio, $fim);
            static::addContasReceberBaixas($totais, $inicio, $fim);

            $totais = array_filter($totais, fn (float $v): bool => $v > 0.009);

            if ($totais === []) {
                return null;
            }

            arsort($totais);

            if (count($totais) > 6) {
                $top = array_slice($totais, 0, 5, true);
                $outros = array_sum(array_slice($totais, 5, null, true));
                $totais = $top;
                if ($outros > 0) {
                    $totais['Outros'] = round((float) ($totais['Outros'] ?? 0) + $outros, 2);
                }
            }

            $labels = array_keys($totais);
            $values = array_map(static fn (float $v): float => round($v, 2), array_values($totais));
            $colors = array_map(
                static fn (string $label): string => self::COLORS[$label] ?? '#64748b',
                $labels,
            );

            return [
                'labels' => $labels,
                'values' => $values,
                'colors' => $colors,
                'unit' => 'money',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, float>  $totais
     */
    private static function addPdvPagamentos(array &$totais, Carbon $inicio, Carbon $fim): void
    {
        if (! Schema::hasTable((new PdvVendaPagamento)->getTable())
            || ! Schema::hasTable((new PdvVenda)->getTable())) {
            return;
        }

        $rows = PdvVendaPagamento::query()
            ->selectRaw('forma, SUM(valor) as total')
            ->whereHas('venda', function ($query) use ($inicio, $fim): void {
                $query->where('situacao', '!=', 'C')
                    ->where(function ($periodo) use ($inicio, $fim): void {
                        $periodo->where(function ($fechamento) use ($inicio, $fim): void {
                            $fechamento->whereNotNull('fechado_em')
                                ->whereDate('fechado_em', '>=', $inicio->toDateString())
                                ->whereDate('fechado_em', '<=', $fim->toDateString());
                        })->orWhere(function ($fallback) use ($inicio, $fim): void {
                            $fallback->whereNull('fechado_em')
                                ->whereDate('created_at', '>=', $inicio->toDateString())
                                ->whereDate('created_at', '<=', $fim->toDateString());
                        });
                    });
            })
            ->groupBy('forma')
            ->get();

        foreach ($rows as $row) {
            $label = static::normalizeForma((string) $row->forma);
            $totais[$label] = round((float) ($totais[$label] ?? 0) + (float) $row->total, 2);
        }
    }

    /**
     * @param  array<string, float>  $totais
     */
    private static function addVendasRetaguarda(array &$totais, Carbon $inicio, Carbon $fim): void
    {
        if (! Schema::hasTable((new Venda)->getTable())) {
            return;
        }

        $rows = Venda::query()
            ->selectRaw('forma_pagamento as forma, SUM(total) as total')
            ->whereNotIn('status', [Venda::STATUS_CANCELADO])
            ->where(function ($query): void {
                $query->whereNull('plataforma')
                    ->orWhere('plataforma', '!=', Venda::PLATAFORMA_PDV);
            })
            ->whereNotNull('forma_pagamento')
            ->where('forma_pagamento', '!=', '')
            ->whereDate('data', '>=', $inicio->toDateString())
            ->whereDate('data', '<=', $fim->toDateString())
            ->groupBy('forma_pagamento')
            ->get();

        foreach ($rows as $row) {
            $label = static::normalizeForma((string) $row->forma);
            $totais[$label] = round((float) ($totais[$label] ?? 0) + (float) $row->total, 2);
        }
    }

    /**
     * Baixas de Contas a Receber no mês (meio usado no recebimento).
     *
     * @param  array<string, float>  $totais
     */
    private static function addContasReceberBaixas(array &$totais, Carbon $inicio, Carbon $fim): void
    {
        if (! Schema::hasTable((new ContaReceber)->getTable())) {
            return;
        }

        $rows = ContaReceber::query()
            ->selectRaw('forma, SUM(valor_recebido) as total')
            ->whereNotNull('recebido_em')
            ->where('valor_recebido', '>', 0)
            ->whereDate('recebido_em', '>=', $inicio->toDateString())
            ->whereDate('recebido_em', '<=', $fim->toDateString())
            ->groupBy('forma')
            ->get();

        foreach ($rows as $row) {
            $label = static::normalizeForma((string) ($row->forma ?? ''));
            $totais[$label] = round((float) ($totais[$label] ?? 0) + (float) $row->total, 2);
        }
    }

    private static function normalizeForma(string $forma): string
    {
        $raw = trim($forma);
        if ($raw === '') {
            return 'Outros';
        }

        $upper = mb_strtoupper($raw, 'UTF-8');

        return match (true) {
            str_contains($upper, 'PIX') => 'PIX',
            str_contains($upper, 'DINHEIRO') || $upper === 'DH' || $upper === 'ESPÉCIE' || $upper === 'ESPECIE' => 'Dinheiro',
            str_contains($upper, 'BOLETO') => 'Boleto',
            str_contains($upper, 'CHEQUE') => 'Cheque',
            str_contains($upper, 'CREDI') || str_contains($upper, 'PRAZO') || str_contains($upper, 'CARTEIRA') => 'Crediário',
            str_contains($upper, 'DEBIT') || str_contains($upper, 'DÉBIT') => 'Débito',
            str_contains($upper, 'CREDIT') || str_contains($upper, 'CRÉDIT') => 'Crédito',
            str_contains($upper, 'TEF') => 'TEF',
            str_contains($upper, 'CART') || str_contains($upper, 'POS') => 'Cartão',
            str_contains($upper, 'DEPOSIT') => 'Depósito',
            str_contains($upper, 'TROCA') => 'Troca',
            default => mb_convert_case(mb_strtolower($raw, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
        };
    }
}
