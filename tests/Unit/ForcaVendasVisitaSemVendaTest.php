<?php

namespace Tests\Unit;

use App\Models\ForcaVendasVisitaSemVenda;
use App\Models\Person;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\ForcaVendas\ForcaVendasSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcaVendasVisitaSemVendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejeita_motivo_curto(): void
    {
        $vendedor = Vendedor::query()->create([
            'codigo' => 'V1',
            'nome' => 'VENDEDOR',
            'ativo' => true,
        ]);

        $user = User::factory()->create(['vendedor_id' => $vendedor->id]);

        $cliente = Person::query()->create([
            'codigo' => 'C1',
            'pessoa_tipo' => Person::PESSOA_JURIDICA,
            'nome_razao' => 'CLIENTE',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $results = app(ForcaVendasSyncService::class)->applyVisitasPush([
            [
                'uuid' => 'visita-teste-1',
                'cliente_id' => $cliente->id,
                'motivo' => 'curto',
            ],
        ], $user);

        $this->assertSame('erro', $results[0]['status']);
        $this->assertSame(1, ForcaVendasVisitaSemVenda::query()->where('status', 'erro')->count());
    }

    public function test_registra_visita_com_motivo_valido(): void
    {
        $vendedor = Vendedor::query()->create([
            'codigo' => 'V2',
            'nome' => 'VENDEDOR 2',
            'ativo' => true,
        ]);

        $user = User::factory()->create(['vendedor_id' => $vendedor->id]);

        $cliente = Person::query()->create([
            'codigo' => 'C2',
            'pessoa_tipo' => Person::PESSOA_JURIDICA,
            'nome_razao' => 'CLIENTE 2',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $results = app(ForcaVendasSyncService::class)->applyVisitasPush([
            [
                'uuid' => 'visita-teste-2',
                'cliente_id' => $cliente->id,
                'motivo' => 'Cliente sem verba para compra neste mês.',
            ],
        ], $user);

        $this->assertSame('importado', $results[0]['status']);
        $this->assertDatabaseHas('forca_vendas_visitas_sem_venda', [
            'uuid' => 'visita-teste-2',
            'cliente_id' => $cliente->id,
            'status' => 'importado',
        ]);
    }
}
