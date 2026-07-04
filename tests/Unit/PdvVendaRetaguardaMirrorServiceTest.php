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
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Pdv\PdvVendaRetaguardaMirrorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PdvVendaRetaguardaMirrorServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_espelha_venda_pdv_na_tabela_vendas(): void
    {
        $user = User::factory()->create();
        $cliente = Person::query()->create([
            'codigo' => '9001',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE PDV TESTE',
            'is_cliente' => true,
            'ativo' => true,
        ]);
        $product = Product::query()->create([
            'codigo' => 'P9001',
            'descricao' => 'PRODUTO PDV TESTE',
            'preco_venda' => 10,
            'ativo' => true,
        ]);

        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);

        $fechamentoUtc = now()->setTime(18, 45, 0);
        $pdvVenda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'person_id' => $cliente->id,
            'numero' => 1,
            'subtotal' => 20,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 20,
            'forma_pagamento' => 'DINHEIRO',
            'situacao' => 'F',
            'fechado_em' => $fechamentoUtc,
            'created_at' => $fechamentoUtc,
            'updated_at' => $fechamentoUtc,
        ]);

        PdvVendaItem::query()->create([
            'pdv_venda_id' => $pdvVenda->id,
            'product_id' => $product->id,
            'descricao' => $product->descricao,
            'quantidade' => 2,
            'preco_unitario' => 10,
            'total' => 20,
        ]);

        $service = new PdvVendaRetaguardaMirrorService();
        $venda = $service->espelhar($pdvVenda->fresh('itens'));

        $fechamento = ErpTimezone::toLocal($fechamentoUtc);
        $this->assertSame($fechamento->toDateString(), $venda->data?->toDateString());
        $this->assertSame('15:45:00', (string) $venda->getRawOriginal('hora'));

        $this->assertNotNull($venda->id);
        $this->assertSame(Venda::TIPO_CUPOM, $venda->tipo);
        $this->assertSame(Venda::PLATAFORMA_PDV, $venda->plataforma);
        $this->assertSame(Venda::STATUS_FECHADO, $venda->status);
        $this->assertSame($cliente->id, $venda->cliente_id);
        $this->assertSame('20.00', $venda->total);

        $pdvVenda->refresh();
        $this->assertSame($venda->id, $pdvVenda->venda_id);
        $this->assertCount(1, VendaItem::query()->where('venda_id', $venda->id)->get());
    }

    public function test_espelha_consumidor_final_quando_sem_cliente(): void
    {
        $user = User::factory()->create();
        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);

        $pdvVenda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'numero' => 2,
            'subtotal' => 15,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 15,
            'forma_pagamento' => 'DINHEIRO',
            'situacao' => 'F',
        ]);

        $service = new PdvVendaRetaguardaMirrorService();
        $venda = $service->espelhar($pdvVenda->fresh('itens'));

        $this->assertSame(
            PdvVendaRetaguardaMirrorService::CONSUMIDOR_FINAL_CODIGO,
            Person::query()->find($venda->cliente_id)?->codigo,
        );
    }

    public function test_estorno_cancela_venda_espelhada(): void
    {
        $user = User::factory()->create();
        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);

        $pdvVenda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'numero' => 3,
            'subtotal' => 9,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 9,
            'forma_pagamento' => 'DINHEIRO',
            'situacao' => 'F',
        ]);

        $service = new PdvVendaRetaguardaMirrorService();
        $venda = $service->espelhar($pdvVenda->fresh('itens'));

        $service->estornar($pdvVenda->fresh());

        $this->assertSame(Venda::STATUS_CANCELADO, $venda->fresh()->status);
    }
}
