<?php

namespace Tests\Unit;

use App\Models\Orcamento;
use App\Models\Person;
use App\Models\Vendedor;
use App\Support\ForcaVendas\ForcaVendasSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcaVendasHistoricoOrcamentosTest extends TestCase
{
    use RefreshDatabase;

    public function test_pull_traz_orcamentos_do_vendedor_dos_ultimos_30_dias(): void
    {
        $vendedor = Vendedor::query()->create([
            'codigo' => 'V1',
            'nome' => 'VENDEDOR TESTE',
            'ativo' => true,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'C1',
            'pessoa_tipo' => Person::PESSOA_JURIDICA,
            'nome_razao' => 'CLIENTE TESTE',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        Orcamento::query()->create([
            'numero' => '000001',
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'subtotal' => 100,
            'total' => 100,
            'status' => Orcamento::STATUS_ABERTO,
        ]);

        Orcamento::query()->create([
            'numero' => '000002',
            'data' => now()->subDays(45)->toDateString(),
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'subtotal' => 50,
            'total' => 50,
            'status' => Orcamento::STATUS_ABERTO,
        ]);

        $payload = app(ForcaVendasSyncService::class)->buildPull(null, $vendedor->id);

        $this->assertArrayHasKey('historico_orcamentos', $payload);
        $this->assertCount(1, $payload['historico_orcamentos']);
        $this->assertSame('000001', $payload['historico_orcamentos'][0]['numero']);
    }
}
