<?php

namespace Tests\Unit;

use App\Models\Pedido;
use App\Models\Person;
use App\Support\Erp\PedidoNumeroService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Usa DatabaseTransactions (igual VendaNumeroServiceTest).
 * A suíte SQLite :memory: está bloqueada por migration legada alheia a este escopo.
 */
class PedidoNumeroServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_proximo_numero_e_sequencial_e_formatado(): void
    {
        if (DB::getDriverName() === 'sqlite' && DB::getDatabaseName() === ':memory:') {
            $this->markTestSkipped('SQLite :memory: migrate quebrado por migration legada (entregas_devices).');
        }

        DB::table('venda_numero_sequencias')->updateOrInsert(
            ['chave' => PedidoNumeroService::CHAVE],
            ['ultimo_numero' => 150, 'created_at' => now(), 'updated_at' => now()],
        );

        $service = new PedidoNumeroService();

        $this->assertSame('000151', $service->proximo());
        $this->assertSame('000152', $service->proximo());
    }

    public function test_proximo_respeita_maximo_existente_em_pedidos(): void
    {
        if (DB::getDriverName() === 'sqlite' && DB::getDatabaseName() === ':memory:') {
            $this->markTestSkipped('SQLite :memory: migrate quebrado por migration legada (entregas_devices).');
        }

        DB::table('venda_numero_sequencias')->where('chave', PedidoNumeroService::CHAVE)->delete();

        $cliente = Person::query()->create([
            'codigo' => '9101-'.uniqid(),
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE DAV SEQUENCIA',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        // Número artificial alto e único para não colidir com produção no rollback.
        $numeroBase = '988'.substr((string) time(), -3);
        Pedido::query()->create([
            'numero' => $numeroBase,
            'data' => now()->toDateString(),
            'hora' => now()->format('H:i:s'),
            'cliente_id' => $cliente->id,
            'subtotal' => 10,
            'percentual_desconto' => 0,
            'desconto_valor' => 0,
            'total' => 10,
            'status' => Pedido::STATUS_ABERTO,
            'plataforma' => Pedido::PLATAFORMA_ERP,
            'validade_dias' => 0,
        ]);

        $service = new PedidoNumeroService();
        $esperado = str_pad((string) (((int) $numeroBase) + 1), 6, '0', STR_PAD_LEFT);

        $this->assertSame($esperado, $service->proximo());
    }

    public function test_pedido_next_numero_delega_ao_service(): void
    {
        if (DB::getDriverName() === 'sqlite' && DB::getDatabaseName() === ':memory:') {
            $this->markTestSkipped('SQLite :memory: migrate quebrado por migration legada (entregas_devices).');
        }

        DB::table('venda_numero_sequencias')->updateOrInsert(
            ['chave' => PedidoNumeroService::CHAVE],
            ['ultimo_numero' => 7, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->assertSame('000008', Pedido::nextNumero());
    }
}
