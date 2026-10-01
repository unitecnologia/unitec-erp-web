<?php

namespace Tests\Unit;

use App\Support\Erp\Reports\MonitorPedidosReport;
use ReflectionMethod;
use Tests\TestCase;

class MonitorPedidosOrdenacaoTest extends TestCase
{
    public function test_ordenacao_labels_e_normalize(): void
    {
        $this->assertSame([
            'alfabetica' => 'Alfabética',
            'codigo' => 'Código',
            'quantidade' => 'Quantidade',
        ], MonitorPedidosReport::ordenacaoLabels());

        $this->assertSame('alfabetica', MonitorPedidosReport::normalizeOrdenacao(null));
        $this->assertSame('codigo', MonitorPedidosReport::normalizeOrdenacao('codigo'));
        $this->assertSame('alfabetica', MonitorPedidosReport::normalizeOrdenacao('invalido'));
    }

    public function test_ordenar_itens_alfabetica_codigo_quantidade(): void
    {
        $itens = [
            ['codigo' => 'B2', 'produto' => 'Zebra', 'unidade' => 'UN', 'quantidade' => 1.0, 'valor_unitario' => 1, 'desconto' => 0, 'subtotal' => 1],
            ['codigo' => 'A1', 'produto' => 'Abacate', 'unidade' => 'UN', 'quantidade' => 5.0, 'valor_unitario' => 1, 'desconto' => 0, 'subtotal' => 5],
            ['codigo' => 'C3', 'produto' => 'Maca', 'unidade' => 'UN', 'quantidade' => 2.0, 'valor_unitario' => 1, 'desconto' => 0, 'subtotal' => 2],
        ];

        $method = new ReflectionMethod(MonitorPedidosReport::class, 'ordenarItensImpressao');
        $method->setAccessible(true);

        $alfa = $method->invoke(null, $itens, MonitorPedidosReport::ORD_ALFABETICA);
        $this->assertSame(['Abacate', 'Maca', 'Zebra'], array_column($alfa, 'produto'));

        $codigo = $method->invoke(null, $itens, MonitorPedidosReport::ORD_CODIGO);
        $this->assertSame(['A1', 'B2', 'C3'], array_column($codigo, 'codigo'));

        $qtd = $method->invoke(null, $itens, MonitorPedidosReport::ORD_QUANTIDADE);
        $this->assertSame(['A1', 'C3', 'B2'], array_column($qtd, 'codigo'));
    }
}
