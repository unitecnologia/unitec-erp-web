<?php

namespace Tests\Unit;

use App\Models\ForcaVendasOrder;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Support\ForcaVendas\ForcaVendasMonitorTotaisAggregator;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class ForcaVendasMonitorTotaisAggregatorTest extends TestCase
{
    public function test_soma_desconto_acrescimo_bruto_e_liquido_com_mesmas_regras_das_celulas(): void
    {
        $pedidoA = new Pedido(['subtotal' => 100, 'desconto_valor' => 0]);
        $pedidoA->setRelation('itens', new Collection([
            new PedidoItem(['desconto' => 10]),
            new PedidoItem(['desconto' => 5]),
        ]));

        $orderA = new ForcaVendasOrder([
            'total' => 90,
            'payload' => ['acrescimo_valor' => 5],
        ]);
        $orderA->setRelation('pedido', $pedidoA);

        // Bruto = subtotal - acrescimo + desconto = 100 - 5 + 15 = 110
        // Desc. = 15 (soma itens), Acre. = 5, Líquido = 90

        $pedidoB = new Pedido(['subtotal' => 50, 'desconto_valor' => 2]);
        $pedidoB->setRelation('itens', new Collection);
        $orderB = new ForcaVendasOrder([
            'total' => 48,
            'payload' => [],
        ]);
        $orderB->setRelation('pedido', $pedidoB);

        // Desc. = 2 (cabeçalho), Acre. = 0, Bruto = 50 - 0 + 2 = 52, Líquido = 48

        $totais = ForcaVendasMonitorTotaisAggregator::sumOrders([$orderA, $orderB]);

        $this->assertSame(17.0, $totais['desconto']);
        $this->assertSame(5.0, $totais['acrescimo']);
        $this->assertSame(162.0, $totais['bruto']);
        $this->assertSame(138.0, $totais['liquido']);
        $this->assertSame(2, $totais['qtd']);
    }

    public function test_pedido_cancelado_entra_no_somatorio_como_exibido_na_grade(): void
    {
        $pedido = new Pedido(['subtotal' => 40, 'desconto_valor' => 0]);
        $pedido->setRelation('itens', new Collection);
        $order = new ForcaVendasOrder([
            'total' => 40,
            'situacao' => ForcaVendasOrder::SITUACAO_CANCELADO,
            'payload' => [],
        ]);
        $order->setRelation('pedido', $pedido);

        $totais = ForcaVendasMonitorTotaisAggregator::sumOrders([$order]);

        $this->assertSame(0.0, $totais['desconto']);
        $this->assertSame(0.0, $totais['acrescimo']);
        $this->assertSame(40.0, $totais['bruto']);
        $this->assertSame(40.0, $totais['liquido']);
        $this->assertSame(1, $totais['qtd']);
    }
}
