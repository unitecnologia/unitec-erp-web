<?php

namespace Tests\Unit;

use App\Models\Entrega;
use App\Models\EntregaItem;
use App\Models\Product;
use App\Support\Erp\Reports\ExpedicaoRetiradaReport;
use App\Support\Logistica\ExpedicaoService;
use PHPUnit\Framework\TestCase;

class ExpedicaoConfirmacaoTest extends TestCase
{
    public function test_calcular_peso_soma_itens_com_peso_cadastrado(): void
    {
        $entrega = new Entrega(['numero' => '10']);
        $entrega->setRelation('venda', null);

        $productA = new Product(['peso_kg' => '1.500']);
        $productB = new Product(['peso_kg' => '0.250']);

        $itemA = new EntregaItem([
            'quantidade_expedida' => 2,
            'product_id' => 1,
        ]);
        $itemA->setRelation('product', $productA);

        $itemB = new EntregaItem([
            'quantidade_expedida' => 4,
            'product_id' => 2,
        ]);
        $itemB->setRelation('product', $productB);

        $entrega->setRelation('itens', collect([$itemA, $itemB]));

        $result = (new ExpedicaoService())->calcularPesoExpedicao($entrega);

        $this->assertSame(4.0, $result['peso_kg']);
        $this->assertSame(0, $result['itens_sem_peso']);
    }

    public function test_calcular_peso_avisa_itens_sem_peso(): void
    {
        $entrega = new Entrega(['numero' => '11']);
        $entrega->setRelation('venda', null);

        $productComPeso = new Product(['peso_kg' => '2.000']);
        $productSemPeso = new Product(['peso_kg' => null]);

        $itemComPeso = new EntregaItem(['quantidade_expedida' => 1]);
        $itemComPeso->setRelation('product', $productComPeso);

        $itemSemPeso = new EntregaItem(['quantidade_expedida' => 3]);
        $itemSemPeso->setRelation('product', $productSemPeso);

        $entrega->setRelation('itens', collect([$itemComPeso, $itemSemPeso]));

        $result = (new ExpedicaoService())->calcularPesoExpedicao($entrega);

        $this->assertSame(2.0, $result['peso_kg']);
        $this->assertSame(1, $result['itens_sem_peso']);
    }

    public function test_romaneio_retirada_lista_apenas_itens_expedidos(): void
    {
        $entrega = new Entrega(['numero' => '12', 'cliente_nome' => 'Cliente']);
        $entrega->setRelation('venda', null);

        $itemExpedido = new EntregaItem([
            'codigo' => '100',
            'descricao' => 'Produto A',
            'quantidade_expedida' => 2,
        ]);
        $itemZerado = new EntregaItem([
            'codigo' => '200',
            'descricao' => 'Produto B',
            'quantidade_expedida' => 0,
        ]);

        $entrega->setRelation('itens', collect([$itemExpedido, $itemZerado]));

        $linhas = ExpedicaoRetiradaReport::buildLinhas($entrega);

        $this->assertCount(1, $linhas);
        $this->assertSame('100', $linhas[0]['codigo']);
        $this->assertSame(2.0, $linhas[0]['quantidade']);
    }
}
