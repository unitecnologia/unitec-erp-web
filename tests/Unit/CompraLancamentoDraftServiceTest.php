<?php

namespace Tests\Unit;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Support\Erp\Compra\CompraLancamentoDraftService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompraLancamentoDraftServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('compra_itens');
        Schema::dropIfExists('compras');

        Schema::create('compras', function (Blueprint $table): void {
            $table->id();
            $table->string('numero')->nullable();
            $table->string('status')->nullable();
            $table->decimal('total', 15, 2)->nullable();
            $table->longText('lancamento_draft')->nullable();
            $table->timestamps();
        });

        Schema::create('compra_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('compra_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantidade', 15, 3)->default(0);
            $table->decimal('valor_unitario', 15, 4)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function test_build_payload_contem_version_rows_e_params(): void
    {
        $service = new CompraLancamentoDraftService();
        $payload = $service->buildPayload(
            [
                [
                    'product_id' => '10',
                    'qtd' => '8,000',
                    'preco_venda' => '100,00',
                    'margem_varejo' => '20,00',
                ],
            ],
            ['frete' => '10,00', 'total' => '500,00'],
            [
                'ajusta_preco' => true,
                'gerar_financeiro' => false,
                'gera_estoque' => true,
                'item_index' => 0,
            ],
        );

        $this->assertSame(CompraLancamentoDraftService::VERSION, $payload['version']);
        $this->assertNotEmpty($payload['saved_at']);
        $this->assertCount(1, $payload['rows']);
        $this->assertSame('8,000', $payload['rows'][0]['qtd']);
        $this->assertTrue($payload['params']['ajusta_preco']);
        $this->assertFalse($payload['params']['gerar_financeiro']);
        $this->assertTrue($payload['params']['gera_estoque']);
    }

    public function test_save_read_clear_roundtrip(): void
    {
        $compra = Compra::query()->create([
            'numero' => '000001',
            'status' => Compra::STATUS_ABERTA,
            'total' => 100,
        ]);

        CompraItem::query()->create([
            'compra_id' => $compra->id,
            'product_id' => 10,
            'quantidade' => 1,
            'valor_unitario' => 100,
            'total' => 100,
        ]);

        $service = new CompraLancamentoDraftService();
        $payload = $service->buildPayload(
            [['product_id' => '10', 'qtd' => '2,000', 'preco_venda' => '150,00', 'margem_varejo' => '50,00']],
            ['total' => '100,00'],
            ['ajusta_preco' => true, 'gerar_financeiro' => true, 'gera_estoque' => true],
        );

        $service->save($compra->fresh(), $payload);

        $draft = $service->read($compra->fresh());
        $this->assertNotNull($draft);
        $this->assertSame('2,000', $draft['rows'][0]['qtd']);
        $this->assertSame('150,00', $draft['rows'][0]['preco_venda']);

        $service->clear($compra->fresh());
        $this->assertNull($service->read($compra->fresh()));
    }

    public function test_save_nao_grava_se_compra_fechada(): void
    {
        $compra = Compra::query()->create([
            'numero' => '000002',
            'status' => Compra::STATUS_FECHADA,
            'total' => 50,
        ]);

        $service = new CompraLancamentoDraftService();
        $service->save($compra, $service->buildPayload(
            [['product_id' => '1', 'qtd' => '1,000']],
            [],
            [],
        ));

        $this->assertNull($service->read($compra->fresh()));
    }

    public function test_is_compatible_rejeita_product_id_diferente(): void
    {
        $compra = Compra::query()->create([
            'numero' => '000003',
            'status' => Compra::STATUS_ABERTA,
            'total' => 10,
        ]);

        CompraItem::query()->create([
            'compra_id' => $compra->id,
            'product_id' => 99,
            'quantidade' => 1,
            'valor_unitario' => 10,
            'total' => 10,
        ]);

        $service = new CompraLancamentoDraftService();
        $draft = $service->buildPayload(
            [['product_id' => '11', 'qtd' => '1,000']],
            [],
            [],
        );

        $this->assertFalse($service->isCompatible($compra->fresh(), $draft));
    }

    public function test_is_compatible_aceita_mesmos_itens(): void
    {
        $compra = Compra::query()->create([
            'numero' => '000004',
            'status' => Compra::STATUS_ABERTA,
            'total' => 10,
        ]);

        CompraItem::query()->create([
            'compra_id' => $compra->id,
            'product_id' => 11,
            'quantidade' => 1,
            'valor_unitario' => 10,
            'total' => 10,
        ]);

        $service = new CompraLancamentoDraftService();
        $draft = $service->buildPayload(
            [['product_id' => '11', 'qtd' => '3,000', 'preco_venda' => '20,00']],
            ['frete' => '1,00'],
            [],
        );

        $this->assertTrue($service->isCompatible($compra->fresh(), $draft));
    }

    public function test_finalizar_limpa_rascunho_no_update_de_status(): void
    {
        $compra = Compra::query()->create([
            'numero' => '000005',
            'status' => Compra::STATUS_ABERTA,
            'total' => 10,
            'lancamento_draft' => '{"version":1,"rows":[{"product_id":"1"}]}',
        ]);

        $this->assertNotNull($compra->fresh()->lancamento_draft);

        $compra->update([
            'status' => Compra::STATUS_FECHADA,
            'lancamento_draft' => null,
        ]);

        $this->assertSame(Compra::STATUS_FECHADA, $compra->fresh()->status);
        $this->assertNull($compra->fresh()->lancamento_draft);
    }
}
