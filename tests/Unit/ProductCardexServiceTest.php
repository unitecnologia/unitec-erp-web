<?php

namespace Tests\Unit;

use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\Pdv\PdvVendaRetaguardaMirrorService;
use App\Support\Erp\ProductCardexService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductCardexServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_vendas_lista_apenas_espelho_da_retaguarda_sem_duplicar_pdv(): void
    {
        $user = User::factory()->create();
        $cliente = Person::query()->create([
            'codigo' => '9100',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE CARDEX TESTE',
            'is_cliente' => true,
            'ativo' => true,
        ]);
        $product = Product::query()->create([
            'codigo' => 'PCDX1',
            'descricao' => 'PRODUTO CARDEX TESTE',
            'preco_venda' => 2,
            'ativo' => true,
        ]);

        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);

        $pdvVenda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'person_id' => $cliente->id,
            'numero' => 1,
            'subtotal' => 2,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 2,
            'forma_pagamento' => 'DINHEIRO',
            'situacao' => 'F',
        ]);

        PdvVendaItem::query()->create([
            'pdv_venda_id' => $pdvVenda->id,
            'product_id' => $product->id,
            'descricao' => $product->descricao,
            'quantidade' => 1,
            'preco_unitario' => 2,
            'total' => 2,
        ]);

        (new PdvVendaRetaguardaMirrorService())->espelhar($pdvVenda->fresh('itens'));

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertCount(1, $cardex['vendas']);
        $this->assertSame('CLIENTE CARDEX TESTE', $cardex['vendas'][0]['cliente']);
        $this->assertSame('R$ 2,00', $cardex['vendas'][0]['total']);
        $this->assertSame('R$ 2,00', $cardex['totais']['vendas']);
    }

    public function test_vendas_ignora_venda_cancelada(): void
    {
        $product = Product::query()->create([
            'codigo' => 'PCDX2',
            'descricao' => 'PRODUTO CARDEX CANCELADO',
            'preco_venda' => 5,
            'ativo' => true,
        ]);

        $venda = Venda::query()->create([
            'numero' => '000999',
            'data' => now()->toDateString(),
            'hora' => '10:00:00',
            'cliente_id' => null,
            'vendedor_id' => null,
            'total' => 5,
            'status' => Venda::STATUS_CANCELADO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        VendaItem::query()->create([
            'venda_id' => $venda->id,
            'product_id' => $product->id,
            'quantidade' => 1,
            'valor_item' => 5,
            'total' => 5,
        ]);

        $cardex = (new ProductCardexService())->forProduct($product);

        $this->assertSame([], $cardex['vendas']);
        $this->assertSame('R$ 0,00', $cardex['totais']['vendas']);
    }
}
