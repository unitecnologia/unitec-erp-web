<?php

namespace Tests\Unit;

use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Models\ForcaVendasOrder;
use App\Models\Pedido;
use App\Models\PedidoItem;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class ForcaVendasMonitorTotalDescontoWithSumTest extends TestCase
{
    public function test_usa_itens_sum_desconto_quando_with_sum_esta_presente(): void
    {
        $pedido = new Pedido(['subtotal' => 100, 'desconto_valor' => 1]);
        $pedido->forceFill(['itens_sum_desconto' => 15.5]);
        $pedido->syncOriginal();
        // Sem relation itens — withSum deve bastar.
        $pedido->unsetRelation('itens');

        $order = new ForcaVendasOrder(['total' => 90, 'payload' => []]);
        $order->setRelation('pedido', $pedido);

        $this->assertSame(15.5, ForcaVendasMonitorResource::totalDesconto($order));
    }

    public function test_cai_para_soma_dos_itens_quando_relation_carregada(): void
    {
        $pedido = new Pedido(['subtotal' => 100, 'desconto_valor' => 0]);
        $pedido->setRelation('itens', new Collection([
            new PedidoItem(['desconto' => 4]),
            new PedidoItem(['desconto' => 6]),
        ]));

        $order = new ForcaVendasOrder(['total' => 90, 'payload' => []]);
        $order->setRelation('pedido', $pedido);

        $this->assertSame(10.0, ForcaVendasMonitorResource::totalDesconto($order));
    }

    public function test_cai_para_cabecalho_quando_soma_itens_zero(): void
    {
        $pedido = new Pedido(['subtotal' => 50, 'desconto_valor' => 3.25]);
        $pedido->forceFill(['itens_sum_desconto' => 0]);
        $pedido->syncOriginal();

        $order = new ForcaVendasOrder(['total' => 46.75, 'payload' => []]);
        $order->setRelation('pedido', $pedido);

        $this->assertSame(3.25, ForcaVendasMonitorResource::totalDesconto($order));
    }
}
