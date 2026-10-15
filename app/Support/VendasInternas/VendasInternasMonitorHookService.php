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
        $query = $this->queryPedidosVinculados($order);

        if (! $query->exists()) {
            return;
        }

        $query->update([
            'venda_id' => $order->venda_id,
            'situacao' => VendasInternasOrder::SITUACAO_FATURADO,
            'pago_at' => now(),
        ]);
    }

    public function onForcaVendasOrderCancelado(ForcaVendasOrder $order): void
    {
        $query = $this->queryPedidosVinculados($order);

        if (! $query->exists()) {
            return;
        }

        $query->update(['situacao' => VendasInternasOrder::SITUACAO_CANCELADO]);
    }

    /**
     * Reabertura no Monitor: volta o espelho VI para pendente (mesmo FV order).
     */
    public function onForcaVendasOrderReaberto(ForcaVendasOrder $order): void
    {
        $query = $this->queryPedidosVinculados($order);

        if (! $query->exists()) {
            return;
        }

        $query->update([
            // Mantém o vínculo com a mesma venda quando a reabertura a preserva (status aberto).
            'venda_id' => $order->venda_id,
            'situacao' => VendasInternasOrder::SITUACAO_PENDENTE,
            'pago_at' => null,
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<VendasInternasOrder>
     */
    private function queryPedidosVinculados(ForcaVendasOrder $order): \Illuminate\Database\Eloquent\Builder
    {
        return VendasInternasOrder::query()
            ->where('tipo', VendasInternasOrder::TIPO_PEDIDO)
            ->where(function ($q) use ($order): void {
                $q->where('forca_vendas_order_id', $order->id);

                if ($order->orcamento_id !== null) {
                    $q->orWhere('orcamento_id', $order->orcamento_id);
                }
            });
    }
}
