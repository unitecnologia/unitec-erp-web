<?php

namespace Tests\Unit;

use App\Models\ForcaVendasOrder;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\ErpTimezone;
use App\Support\ForcaVendas\ForcaVendasSyncService;
use Carbon\Carbon;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class ForcaVendasOrderClientCreatedAtTimezoneTest extends TestCase
{
    use MigratesSqliteMemory;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_persiste_created_at_utc_z_como_horario_local_do_erp(): void
    {
        $order = $this->pushOrcamento([
            'uuid' => 'fv-tz-utc-z',
            'created_at' => '2026-09-29T02:00:47Z',
        ]);

        $this->assertSame('2026-09-28 23:00:47', $order->getAttributes()['client_created_at']);
        $this->assertSame('2026-09-28 23:00:47', $order->client_created_at?->format('Y-m-d H:i:s'));
        $this->assertSame(ErpTimezone::DEFAULT, $order->client_created_at?->timezoneName);
    }

    public function test_persiste_created_at_com_offset_menos_03_sem_deslocar(): void
    {
        $order = $this->pushOrcamento([
            'uuid' => 'fv-tz-offset-03',
            'created_at' => '2026-09-28T23:00:47-03:00',
        ]);

        $this->assertSame('2026-09-28 23:00:47', $order->getAttributes()['client_created_at']);
        $this->assertSame('2026-09-28 23:00:47', $order->client_created_at?->format('Y-m-d H:i:s'));
    }

    public function test_sem_created_at_mantem_client_created_at_nulo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 23:30:00', ErpTimezone::DEFAULT));

        $order = $this->pushOrcamento([
            'uuid' => 'fv-tz-sem-created-at',
        ]);

        $this->assertNull($order->getAttributes()['client_created_at'] ?? null);
        $this->assertNull($order->client_created_at);
        $this->assertSame('2026-09-28 23:30:00', $order->received_at?->format('Y-m-d H:i:s'));
        $this->assertTrue($order->dataAberturaAt()?->equalTo($order->received_at));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pushOrcamento(array $extra): ForcaVendasOrder
    {
        $suffix = substr((string) ($extra['uuid'] ?? uniqid('fv', true)), -8);

        $vendedor = Vendedor::query()->create([
            'codigo' => 'V'.$suffix,
            'nome' => 'VENDEDOR '.$suffix,
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'vendedor_id' => $vendedor->id,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'C'.$suffix,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE '.$suffix,
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $product = Product::query()->create([
            'codigo' => 'P'.$suffix,
            'descricao' => 'PRODUTO '.$suffix,
            'unidade' => 'UN',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $payload = array_merge([
            'cliente_id' => $cliente->id,
            'tipo' => ForcaVendasOrder::TIPO_ORCAMENTO,
            'desconto_valor' => 0,
            'itens' => [
                [
                    'product_id' => $product->id,
                    'quantidade' => 1,
                    'preco_unitario' => 10,
                    'desconto' => 0,
                ],
            ],
        ], $extra);

        $results = app(ForcaVendasSyncService::class)->applyPush([$payload], $user);

        $this->assertSame(ForcaVendasOrder::STATUS_IMPORTADO, $results[0]['status'] ?? null, json_encode($results));

        $order = ForcaVendasOrder::query()->where('uuid', $payload['uuid'])->first();
        $this->assertNotNull($order);

        return $order;
    }
}
