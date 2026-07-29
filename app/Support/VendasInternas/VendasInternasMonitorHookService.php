<?php

namespace App\Support\VendasInternas;

use App\Models\ForcaVendasOrder;
use App\Models\VendasInternasOrder;

/**
 * Mantém vendas_internas_orders sincronizado quando o pedido VI é faturado
 * ou cancelado pelo Monitor de Vendas.
 */
final class VendasInternasMonitorHookService
{
    public function onForcaVendasOrderFaturado(ForcaVendasOrder $order): void
    {
        if ($order->orcamento_id === null) {
            return;
        }

        VendasInternasOrder::query()
            ->where('tipo', VendasInternasOrder::TIPO_PEDIDO)
            ->where(function ($q) use ($order): void {
                $q->where('forca_vendas_order_id', $order->id)
                    ->orWhere('orcamento_id', $order->orcamento_id);
            })
            ->update([
                'venda_id' => $order->venda_id,
                'situacao' => VendasInternasOrder::SITUACAO_FATURADO,
                'pago_at' => now(),
            ]);
    }

    public function onForcaVendasOrderCancelado(ForcaVendasOrder $order): void
    {
        if ($order->orcamento_id === null) {
            return;
        }

        VendasInternasOrder::query()
            ->where('tipo', VendasInternasOrder::TIPO_PEDIDO)
            ->where(function ($q) use ($order): void {
                $q->where('forca_vendas_order_id', $order->id)
                    ->orWhere('orcamento_id', $order->orcamento_id);
            })
            ->update(['situacao' => VendasInternasOrder::SITUACAO_CANCELADO]);
    }
}
