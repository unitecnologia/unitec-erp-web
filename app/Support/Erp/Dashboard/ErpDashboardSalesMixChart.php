<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\ErpSchema;
use App\Models\OrdemServico;
use App\Models\PdvVenda;
use App\Models\Venda;
use App\Support\Erp\ErpEmpresaScopeFilter;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;
use Throwable;

final class ErpDashboardSalesMixChart
{
    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{labels: list<string>, values: list<float>, colors: list<string>}
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
     * @return array{labels: list<string>, values: list<float>, colors: list<string>}|null
     */
    private static function fromDatabase(int|array|null $empresaScope = null): ?array
    {
        try {
            $hoje = ErpFinanceiroMetricas::hoje();
            $inicio = $hoje->copy()->startOfMonth();
            $fim = $hoje;

            $pdv = static::sumPdv($inicio, $fim, $empresaScope);
            $pedidos = static::sumPedidos($inicio, $fim, $empresaScope);
            $os = static::sumOs($inicio, $fim, $empresaScope);

            // Só segmentos com movimento — legenda e pizza sem categorias zeradas.
            $segments = [];
            if ($pdv > 0) {
                $segments[] = ['label' => 'PDV', 'value' => round($pdv, 2), 'color' => '#1e5a9e'];
            }
            if ($pedidos > 0) {
                $segments[] = ['label' => 'Pedidos', 'value' => round($pedidos, 2), 'color' => '#0d9488'];
            }
            if ($os > 0) {
                $segments[] = ['label' => 'OS', 'value' => round($os, 2), 'color' => '#c2410c'];
            }

            if ($segments === []) {
                return null;
            }

            return [
                'labels' => array_column($segments, 'label'),
                'values' => array_column($segments, 'value'),
                'colors' => array_column($segments, 'color'),
                'unit' => 'money',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function sumPdv(Carbon $from, Carbon $to, int|array|null $empresaScope = null): float
    {
        if (! ErpSchema::hasTable((new PdvVenda)->getTable())) {
            return 0.0;
        }

        $q = PdvVenda::query()
            ->comercial()
            ->where('situacao', '!=', 'C');

        ErpDashboardDateFilter::wherePdvPeriodo($q, $from, $to);
        ErpEmpresaScopeFilter::applyPdvSessao($q, $empresaScope);

        return (float) $q->sum('total');
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function sumPedidos(Carbon $from, Carbon $to, int|array|null $empresaScope = null): float
    {
        if (! ErpSchema::hasTable((new Venda)->getTable())) {
            return 0.0;
        }

        $q = Venda::query()
            ->whereNotIn('status', [Venda::STATUS_CANCELADO])
            ->where('tipo', '!=', Venda::TIPO_CUPOM)
            ->where(function ($query): void {
                // Exclui PDV e espelho de OS (plataforma=os) — OS entra no segmento próprio.
                $query->whereNull('plataforma')
                    ->orWhereNotIn('plataforma', [Venda::PLATAFORMA_PDV, Venda::PLATAFORMA_OS]);
            });

        ErpDashboardDateFilter::whereDateColumnBetween($q, 'data', $from, $to);
        ErpEmpresaScopeFilter::applyColumn($q, (new Venda)->getTable(), $empresaScope);

        return (float) $q->sum('total');
    }

    /**
     * OS realmente faturadas no período (finalizada/entregue + evidência de faturamento).
     * Usa total_geral da OS — venda espelho (plataforma=os) fica fora de Pedidos.
     *
     * @param  int|list<int>|null  $empresaScope
     */
    private static function sumOs(Carbon $from, Carbon $to, int|array|null $empresaScope = null): float
    {
        $table = (new OrdemServico)->getTable();

        if (! ErpSchema::hasTable($table)) {
            return 0.0;
        }

        $q = OrdemServico::query()
            ->whereIn('situacao', [
                OrdemServico::SITUACAO_FINALIZADA,
                OrdemServico::SITUACAO_ENTREGUE,
            ])
            ->whereNotNull('data_termino');

        // Só faturamento real (espelho / pagamentos) — exclui "finalizar" sem caixa/financeiro.
        $temVendaId = ErpSchema::hasColumn($table, 'venda_id');
        $temFatPgto = ErpSchema::hasColumn($table, 'faturamento_pagamentos');

        if ($temVendaId && $temFatPgto) {
            $q->where(function ($query): void {
                $query->whereNotNull('venda_id')
                    ->orWhereNotNull('faturamento_pagamentos');
            });
        } elseif ($temVendaId) {
            $q->whereNotNull('venda_id');
        } elseif ($temFatPgto) {
            $q->whereNotNull('faturamento_pagamentos');
        }

        ErpDashboardDateFilter::whereDateColumnBetween($q, 'data_termino', $from, $to);
        ErpEmpresaScopeFilter::applyColumn($q, $table, $empresaScope);

        return (float) $q->sum('total_geral');
    }
}
