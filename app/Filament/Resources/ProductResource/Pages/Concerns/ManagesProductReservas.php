<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Support\Erp\EstoqueReservaService;

trait ManagesProductReservas
{
    /** @var array<int, array<string, mixed>> */
    public array $productReservasAtivas = [];

    public string $productEstoqueReservadoLabel = '0';

    public string $productEstoqueDisponivelLabel = '0';

    protected function loadProductReservas(?Product $product): void
    {
        if ($product === null || ! $product->exists) {
            $this->productReservasAtivas = [];
            $this->productEstoqueReservadoLabel = '0';
            $this->productEstoqueDisponivelLabel = $this->formatBrDecimal((float) ($this->data['estoque'] ?? 0), 3);

            return;
        }

        $serv = new EstoqueReservaService();
        $reservado = $serv->reservadoAtivo($product->id);
        $fisico = (float) $product->estoque;
        $disponivel = $fisico - $reservado;

        $this->productEstoqueReservadoLabel = $this->formatBrDecimal($reservado, 3);
        $this->productEstoqueDisponivelLabel = $this->formatBrDecimal($disponivel, 3);

        $this->productReservasAtivas = $serv->reservasAtivasDoProduto($product->id)
            ->map(fn ($r) => [
                'pedido' => $r->pedido_numero ?? ('FV-'.$r->forca_vendas_order_id),
                'cliente' => $r->cliente_nome ?? '—',
                'vendedor' => $r->vendedor_nome ?? '—',
                'plataforma' => strtoupper($r->plataforma),
                'quantidade' => $this->formatBrDecimal((float) $r->quantidade, 3),
                'data' => optional($r->created_at)->format('d/m/Y H:i') ?? '—',
            ])
            ->all();
    }
}
