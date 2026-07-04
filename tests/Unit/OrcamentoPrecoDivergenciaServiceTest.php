<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\Erp\Orcamento\OrcamentoPrecoDivergenciaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrcamentoPrecoDivergenciaServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_detecta_item_com_preco_diferente_do_cadastro(): void
    {
        $product = Product::query()->create([
            'codigo' => 'PDIV1',
            'descricao' => 'PRODUTO DIVERGENCIA',
            'preco_venda' => 100,
            'ativo' => true,
        ]);

        $itens = [[
            'key' => '1',
            'product_id' => $product->id,
            'product_codigo' => $product->codigo,
            'descricao' => $product->descricao,
            'quantidade' => '1,000',
            'preco_unitario' => '80,00',
        ]];

        $divergencias = app(OrcamentoPrecoDivergenciaService::class)->detectar($itens);

        $this->assertCount(1, $divergencias);
        $this->assertSame(0, $divergencias[0]['index']);
        $this->assertSame(80.0, $divergencias[0]['preco_orcamento']);
        $this->assertSame(100.0, $divergencias[0]['preco_atual']);
    }

    public function test_ignora_item_sem_divergencia(): void
    {
        $product = Product::query()->create([
            'codigo' => 'PDIV2',
            'descricao' => 'PRODUTO IGUAL',
            'preco_venda' => 50,
            'ativo' => true,
        ]);

        $itens = [[
            'key' => '1',
            'product_id' => $product->id,
            'product_codigo' => $product->codigo,
            'descricao' => $product->descricao,
            'quantidade' => '2,000',
            'preco_unitario' => '50,00',
        ]];

        $divergencias = app(OrcamentoPrecoDivergenciaService::class)->detectar($itens);

        $this->assertSame([], $divergencias);
    }

    public function test_ignora_item_sem_produto_vinculado(): void
    {
        $itens = [[
            'key' => '1',
            'product_id' => 0,
            'descricao' => 'ITEM AVULSO',
            'quantidade' => '1,000',
            'preco_unitario' => '10,00',
        ]];

        $divergencias = app(OrcamentoPrecoDivergenciaService::class)->detectar($itens);

        $this->assertSame([], $divergencias);
    }

    public function test_ignora_produto_com_preco_variavel(): void
    {
        $product = Product::query()->create([
            'codigo' => 'PDIV3',
            'descricao' => 'PRODUTO PRECO VARIAVEL',
            'preco_venda' => 100,
            'preco_variavel' => true,
            'ativo' => true,
        ]);

        $itens = [[
            'key' => '1',
            'product_id' => $product->id,
            'product_codigo' => $product->codigo,
            'descricao' => $product->descricao,
            'quantidade' => '1,000',
            'preco_unitario' => '80,00',
        ]];

        $divergencias = app(OrcamentoPrecoDivergenciaService::class)->detectar($itens);

        $this->assertSame([], $divergencias);
    }
}
