<?php

namespace App\Support\Erp\Dashboard;

use App\Models\Orcamento;
use App\Models\PdvVenda;
use App\Models\Venda;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ErpDashboardSalesMixChart
{
    /**
     * @return array{labels: list<string>, values: list<float>, colors: list<string>}
     */
    public static function data(): array
    {
        $real = static::fromDatabase();

        if ($real !== null) {
            return $real;
        }

        return ErpDashboardDemoData::salesMixChart();
    }

    /**
     * @return array{labels: list<string>, values: list<float>, colors: list<string>}|null
     */
    private static function fromDatabase(): ?array
    {
        try {
            $inicio = Carbon::today()->startOfMonth();
            $fim = Carbon::today()->endOfMonth();

            $pdv = static::sumPdv($inicio, $fim);
            $pedidos = static::sumPedidos($inicio, $fim);
            $orcamentos = static::sumOrcamentos($inicio, $fim);

            if ($pdv <= 0 && $pedidos <= 0 && $orcamentos <= 0) {
                return null;
            }

            return [
                'labels' => ['PDV', 'Pedidos', 'Orçamentos'],
                'values' => [
                    round($pdv, 2),
                    round($pedidos, 2),
                    round($orcamentos, 2),
                ],
                'colors' => ['#1e5a9e', '#0d9488', '#d97706'],
                'unit' => 'money',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private static function sumPdv(Carbon $from, Carbon $to): float
    {
        if (! Schema::hasTable((new PdvVenda)->getTable())) {
            return 0.0;
        }

        return (float) PdvVenda::query()
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
            })
            ->sum('total');
    }

    private static function sumPedidos(Carbon $from, Carbon $to): float
    {
        if (! Schema::hasTable((new Venda)->getTable())) {
            return 0.0;
        }

        return (float) Venda::query()
            ->whereNotIn('status', [Venda::STATUS_CANCELADO])
            ->where('tipo', '!=', Venda::TIPO_CUPOM)
            ->whereDate('data', '>=', $from->toDateString())
            ->whereDate('data', '<=', $to->toDateString())
            ->sum('total');
    }

    private static function sumOrcamentos(Carbon $from, Carbon $to): float
    {
        if (! Schema::hasTable((new Orcamento)->getTable())) {
            return 0.0;
        }

        return (float) Orcamento::query()
            ->whereDate('created_at', '>=', $from->toDateString())
            ->whereDate('created_at', '<=', $to->toDateString())
            ->sum('total');
    }
}
