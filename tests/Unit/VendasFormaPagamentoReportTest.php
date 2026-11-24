<?php

namespace Tests\Unit;

use App\Models\ForcaVendasOrder;
use App\Models\Person;
use App\Models\User;
use App\Models\Venda;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\Reports\Tabular\Definitions\VendasFormaPagamentoReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendasFormaPagamentoReportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        ErpSchema::flush();
        $this->criarSchemaSqlite();
    }

    public function test_participacao_ordem_e_rateio_fv_misto(): void
    {
        if (! Schema::hasTable('forca_vendas_orders')) {
            $this->markTestSkipped('Tabela forca_vendas_orders ausente.');
        }

        $data = Carbon::parse('2099-09-01');
        $this->vendaSimples($data, 'DINHEIRO', 70);
        $this->vendaSimples($data, 'PIX', 100);
        $this->vendaFvMista($data, 35, 15);
        $this->vendaSimples($data, 'POS CREDITO', 40);
        $this->vendaSimples($data, 'POS DEBITO', 20);

        $built = (new VendasFormaPagamentoReport)->build($this->request([
            'de' => $data->toDateString(),
            'ate' => $data->toDateString(),
        ]));

        $porForma = [];
        foreach ($built['rows'] as $row) {
            $porForma[(string) $row['forma']] = $row;
        }

        $this->assertSame('105,00', $porForma['DINHEIRO']['total']);
        $this->assertSame('115,00', $porForma['PIX']['total']);
        $this->assertSame('40,00', $porForma['POS CREDITO']['total']);
        $this->assertSame('20,00', $porForma['POS DEBITO']['total']);
        $this->assertArrayHasKey('participacao', $porForma['DINHEIRO']);
        $this->assertSame('280,00', $built['totals']['total']);
        $this->assertSame('100,00%', $built['totals']['participacao']);
        $this->assertSame('5', $built['totals']['qtd']);
        $this->assertTrue(collect($built['summary'])->contains(
            fn (string $linha): bool => str_contains($linha, 'VENDAS ÚNICAS: 5')
        ));
        $this->assertSame('forma', $built['filters']['ordem']);
        $this->assertContains('participacao', $built['filters']['cols']);

        $maior = (new VendasFormaPagamentoReport)->build($this->request([
            'de' => $data->toDateString(),
            'ate' => $data->toDateString(),
            'ordem' => 'maior_valor',
        ]));

        $this->assertSame('PIX', $maior['rows'][0]['forma']);
        $this->assertSame('maior_valor', $maior['filters']['ordem']);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(array $query): Request
    {
        return Request::create('/relatorios/vendas-forma-pagamento', 'GET', $query);
    }

    private function vendaSimples(Carbon $data, string $forma, float $total): Venda
    {
        return Venda::query()->create([
            'numero' => 'RF'.bin2hex(random_bytes(5)),
            'data' => $data->toDateString(),
            'hora' => '10:00:00',
            'cliente_id' => $this->cliente()->id,
            'total' => $total,
            'forma_pagamento' => $forma,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_ERP,
        ]);
    }

    private function vendaFvMista(Carbon $data, float $dinheiro, float $pix): void
    {
        $total = round($dinheiro + $pix, 2);
        $venda = $this->vendaSimples($data, 'DINHEIRO / PIX', $total);

        ForcaVendasOrder::query()->create([
            'uuid' => (string) Str::uuid(),
            'device_uuid' => 'test-device',
            'user_id' => User::factory()->create()->id,
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'cliente_id' => $venda->cliente_id,
            'venda_id' => $venda->id,
            'total' => $total,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
            'payload' => [
                'forma_pagamento' => 'DINHEIRO / PIX',
                'pagamentos' => [
                    ['forma_pagamento' => 'DINHEIRO', 'forma' => 'DINHEIRO', 'valor' => $dinheiro],
                    ['forma_pagamento' => 'PIX', 'forma' => 'PIX', 'valor' => $pix],
                ],
            ],
        ]);
    }

    private function cliente(): Person
    {
        return Person::query()->create([
            'codigo' => 'C'.bin2hex(random_bytes(5)),
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE REL FORMA',
            'is_cliente' => true,
            'ativo' => true,
        ]);
    }

    private function criarSchemaSqlite(): void
    {
        foreach ([
            'pdv_venda_pagamentos',
            'pdv_vendas',
            'forca_vendas_orders',
            'vendas',
            'people',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->boolean('ativo')->default(true);
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('pessoa_tipo', 20);
            $table->string('nome_razao');
            $table->boolean('is_cliente')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Sem empresa_id: evita escopo ReportEmpresaScope vazio (sem sessão) zerar o relatório.
        Schema::create('vendas', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->date('data');
            $table->time('hora')->nullable();
            $table->unsignedBigInteger('cliente_id');
            $table->decimal('total', 15, 2)->default(0);
            $table->string('forma_pagamento')->nullable();
            $table->string('status', 20)->default('aberto');
            $table->string('tipo', 20)->default('pedido');
            $table->string('plataforma', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->string('origem')->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_venda_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_venda_id');
            $table->string('forma', 30);
            $table->decimal('valor', 12, 2);
            $table->timestamps();
        });

        Schema::create('forca_vendas_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->string('device_uuid')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('tipo', 20)->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 30)->nullable();
            $table->string('situacao', 30)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }
}
