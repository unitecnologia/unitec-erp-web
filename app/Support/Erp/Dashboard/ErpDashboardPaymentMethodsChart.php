<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\ErpSchema;
use App\Models\Venda;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use App\Support\Erp\Reports\VendasPorFormaPagamentoAggregator;
use Throwable;

/**
 * Meios de pagamento do mês — mesma base do relatório Vendas por Forma de Pagamento.
 */
final class ErpDashboardPaymentMethodsChart
{
    /** @var array<string, string> */
    private const COLORS = [
        'DINHEIRO' => '#0f766e',
        'PIX' => '#1d4ed8',
        'POS DEBITO' => '#6366f1',
        'POS DÉBITO' => '#6366f1',
        'POS CREDITO' => '#7c3aed',
        'POS CRÉDITO' => '#7c3aed',
        'CARTAO DEBITO' => '#6366f1',
        'CARTÃO DÉBITO' => '#6366f1',
        'CARTAO CREDITO' => '#7c3aed',
        'CARTÃO CRÉDITO' => '#7c3aed',
        'BOLETO' => '#d97706',
        'CHEQUE' => '#ca8a04',
        'CREDIARIO' => '#db2777',
        'CREDIÁRIO' => '#db2777',
        'DEPOSITO' => '#0284c7',
        'DEPÓSITO' => '#0284c7',
        'TEF' => '#4f46e5',
        'TROCA' => '#64748b',
        'NÃO INFORMADA' => '#94a3b8',
    ];

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string}
     */
    public static function data(int|array|null $empresaScope = null): array
    {
        return static::fromDatabase($empresaScope) ?? [
            'labels' => [],
            'values' => [],
            'colors' => [],
            'unit' => 'money',
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string}|null
     */
    private static function fromDatabase(int|array|null $empresaScope = null): ?array
    {
        try {
            if (! ErpSchema::hasTable((new Venda)->getTable())) {
                return null;
            }

            $hoje = ErpFinanceiroMetricas::hoje();
            $inicio = $hoje->copy()->startOfMonth();
            $fim = $hoje;

            $rows = VendasPorFormaPagamentoAggregator::aggregate(
                $inicio,
                $fim,
                $empresaScope,
            );

            /** @var array<string, float> $totais */
            $totais = [];

            foreach ($rows as $row) {
                $label = (string) ($row['forma'] ?? '');
                $valor = round((float) ($row['total'] ?? 0), 2);

                if ($label === '' || $valor <= 0.009) {
                    continue;
                }

                $totais[$label] = round((float) ($totais[$label] ?? 0) + $valor, 2);
            }

            if ($totais === []) {
                return null;
            }

            arsort($totais);

            $labels = array_keys($totais);
            $values = array_map(static fn (float $v): float => round($v, 2), array_values($totais));
            $colors = array_map(
                static fn (string $label): string => self::COLORS[mb_strtoupper($label, 'UTF-8')] ?? '#64748b',
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
}
