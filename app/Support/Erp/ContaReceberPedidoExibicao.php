<?php

namespace App\Support\Erp;

use App\Models\ContaReceber;
use App\Models\ForcaVendasOrder;
use App\Models\Venda;
use Illuminate\Support\Collection;

/**
 * Número da coluna Pedido no Contas a Receber, igual ao Nº Pedido do Monitor:
 * vendas.numero do pedido faturado (sem zeros à esquerda).
 */
final class ContaReceberPedidoExibicao
{
    /**
     * @param  iterable<ContaReceber>  $records
     * @return array<int, string> id do forca_vendas_orders => número do Monitor
     */
    public static function mapa(iterable $records): array
    {
        $ids = [];

        foreach ($records as $record) {
            $orderId = self::orderId((string) ($record->documento ?? ''));
            if ($orderId !== null) {
                $ids[$orderId] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $mapa = [];

        ForcaVendasOrder::query()
            ->whereIn('id', array_keys($ids))
            ->with('venda:id,numero')
            ->get(['id', 'venda_id'])
            ->each(function (ForcaVendasOrder $order) use (&$mapa): void {
                $numero = self::formatarNumeroVenda($order->venda?->numero);
                if ($numero !== null) {
                    $mapa[(int) $order->id] = $numero;
                }
            });

        return $mapa;
    }

    /**
     * @param  array<int, string>  $mapa
     */
    public static function texto(?string $documento, array $mapa): string
    {
        $documento = trim((string) $documento);
        if ($documento === '') {
            return '—';
        }

        $orderId = self::orderId($documento);
        if ($orderId !== null && isset($mapa[$orderId])) {
            return $mapa[$orderId];
        }

        return $documento;
    }

    /**
     * Ids de forca_vendas_orders cujo Nº Pedido do Monitor é este número.
     *
     * @return Collection<int, int>
     */
    public static function orderIdsDoNumero(int $numero): Collection
    {
        if ($numero <= 0) {
            return collect();
        }

        $padded = str_pad((string) $numero, 6, '0', STR_PAD_LEFT);

        $vendaIds = Venda::query()
            ->where(function ($query) use ($numero, $padded): void {
                $query->where('numero', $padded);
                if ($padded !== (string) $numero) {
                    $query->orWhere('numero', (string) $numero);
                }
            })
            ->pluck('id');

        if ($vendaIds->isEmpty()) {
            return collect();
        }

        return ForcaVendasOrder::query()
            ->whereIn('venda_id', $vendaIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    public static function orderId(string $documento): ?int
    {
        if (preg_match('/^FV-(\d+)/', trim($documento), $matches) !== 1) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }

    public static function formatarNumeroVenda(mixed $numero): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $numero);
        if ($digits === null || $digits === '') {
            return null;
        }

        return (string) (int) $digits;
    }
}
