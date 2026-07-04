<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Models\Venda;
use App\Support\Erp\Queries\VendaListQueryBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VendaListQueryBuilderPlataformaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_filtra_vendas_por_plataforma(): void
    {
        $cliente = Person::query()->create([
            'codigo' => 'PLAT01',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE PLATAFORMA',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $mobile = Venda::query()->create([
            'numero' => '000901',
            'data' => now()->toDateString(),
            'hora' => '10:00:00',
            'cliente_id' => $cliente->id,
            'total' => 100,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_MOBILE,
        ]);

        Venda::query()->create([
            'numero' => '000902',
            'data' => now()->toDateString(),
            'hora' => '11:00:00',
            'cliente_id' => $cliente->id,
            'total' => 120,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_ERP,
        ]);

        Venda::query()->create([
            'numero' => '000903',
            'data' => now()->toDateString(),
            'hora' => '12:00:00',
            'cliente_id' => $cliente->id,
            'total' => 80,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_PDV,
        ]);

        $ids = (new VendaListQueryBuilder(
            searchColumn: 'plataforma',
            localSearch: Venda::PLATAFORMA_MOBILE,
        ))->build()->pluck('id')->all();

        $this->assertSame([$mobile->id], $ids);
    }
}
