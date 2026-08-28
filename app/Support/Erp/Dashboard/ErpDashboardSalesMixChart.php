<?php

namespace App\Support\Erp\Dashboard;

use App\Models\PdvVenda;
use App\Models\Venda;
use App\Support\Erp\ErpEmpresaScopeFilter;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
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

            if ($pdv <= 0 && $pedidos <= 0) {
                return null;
            }

            return [
                'labels' => ['PDV', 'Pedidos'],
                'values' => [
                    round($pdv, 2),
                    round($pedidos, 2),
                ],
                'colors' => ['#1e5a9e', '#0d9488'],
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
        if (! Schema::hasTable((new PdvVenda)->getTable())) {
            return 0.0;
        }

        $q = PdvVenda::query()
            ->where('situacao', '!=', 'C')
            ->where(function ($query) use ($from, $to): void {
                $query->where(function ($fechamento) use ($from, $to): void {
                    $fechamento->whereNotNull('fechado_em')
                        ->whereDate('fechado_em', '>=', $from->toDateString())
                        ->whereDate('fechado_em', '<=', $to->toDateString());
                })->orWhere(function ($fallback) use ($from, $to): void {
                    $fallback->whereNull('fechado_em')
                        ->whereDate('created_at', '>=', $from->toDateString())
                        ->whereDate('created_at', '<=', $to->toDateString());
                });
            });

        ErpEmpresaScopeFilter::applyPdvSessao($q, $empresaScope);

        return (float) $q->sum('total');
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function sumPedidos(Carbon $from, Carbon $to, int|array|null $empresaScope = null): float
    {
        if (! Schema::hasTable((new Venda)->getTable())) {
            return 0.0;
        }

        $q = Venda::query()
            ->whereNotIn('status', [Venda::STATUS_CANCELADO])
            ->where('tipo', '!=', Venda::TIPO_CUPOM)
            ->where(function ($query): void {
                $query->whereNull('plataforma')
                    ->orWhere('plataforma', '!=', Venda::PLATAFORMA_PDV);
            })
            ->whereDate('data', '>=', $from->toDateString())
            ->whereDate('data', '<=', $to->toDateString());

        ErpEmpresaScopeFilter::applyColumn($q, (new Venda)->getTable(), $empresaScope);

        return (float) $q->sum('total');
    }
}
