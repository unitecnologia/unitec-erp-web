<?php

namespace App\Support\Erp\Dashboard;

use App\Models\PdvVenda;
use App\Models\Venda;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ErpDashboardRecentSales
{
    /**
     * @return list<array<string, string>>
     */
    public static function list(int $limit = 6): array
    {
        try {
            $rows = static::rows($limit);

            return $rows->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private static function rows(int $limit): Collection
    {
        $items = collect();

        if (Schema::hasTable((new PdvVenda)->getTable())) {
            $pdv = PdvVenda::query()
                ->with('person:id,nome_razao')
                ->where('situacao', '!=', 'C')
                ->orderByDesc('fechado_em')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            foreach ($pdv as $venda) {
                $momento = ErpTimezone::toLocal($venda->fechado_em ?? $venda->created_at);

                $items->push([
                    'id' => (string) $venda->numero,
                    'cliente' => $venda->person?->nome_razao ?? 'Consumidor',
                    'valor' => 'R$ ' . ErpMoney::formatBr($venda->total),
                    'data' => $momento->format('d/m H:i'),
                    'status' => 'PDV',
                    'sort_at' => $momento->timestamp,
                ]);
            }
        }

        if (Schema::hasTable((new Venda)->getTable())) {
            $vendas = Venda::query()
                ->with('cliente:id,nome_razao')
                ->whereNotIn('status', [Venda::STATUS_CANCELADO])
                ->where('tipo', '!=', Venda::TIPO_CUPOM)
                ->orderByDesc('data')
                ->orderByDesc('hora')
                ->limit($limit)
                ->get();

            foreach ($vendas as $venda) {
                $data = $venda->data ? Carbon::parse($venda->data) : Carbon::today();
                $hora = filled($venda->hora) ? (string) $venda->hora : '00:00:00';
                $momento = ErpTimezone::toLocal($data->format('Y-m-d') . ' ' . $hora);

                $items->push([
                    'id' => (string) $venda->numero,
                    'cliente' => $venda->cliente?->nome_razao ?? 'Cliente',
                    'valor' => 'R$ ' . ErpMoney::formatBr($venda->total),
                    'data' => $momento->format('d/m H:i'),
                    'status' => Venda::statusLabels()[$venda->status] ?? ucfirst((string) $venda->status),
                    'sort_at' => $momento->timestamp,
                ]);
            }
        }

        return $items
            ->sortByDesc('sort_at')
            ->take($limit)
            ->map(fn (array $row): array => [
                'id' => $row['id'],
                'cliente' => $row['cliente'],
                'valor' => $row['valor'],
                'data' => $row['data'],
                'status' => $row['status'],
            ])
            ->values();
    }
}
