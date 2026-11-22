<?php

namespace Tests\Unit;

use App\Models\OrdemServico;
use App\Models\Venda;
use App\Support\Erp\Dashboard\ErpDashboardSalesMixChart;
use App\Support\Erp\ErpSchema;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema mínimo (sem MigratesSqliteMemory): cobre Mix do mês OS faturada + legenda.
 */
class ErpDashboardSalesMixChartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaMinimo();
        ErpSchema::flush();
    }

    public function test_mix_os_so_conta_faturadas_e_oculta_categorias_zeradas(): void
    {
        $hoje = Carbon::today()->toDateString();

        // Finalizada sem faturamento — não deve entrar.
        DB::table('ordens_servico')->insert([
            'numero' => 'MIX-NF',
            'situacao' => OrdemServico::SITUACAO_FINALIZADA,
            'data_termino' => $hoje,
            'cliente_id' => 1,
            'venda_id' => null,
            'total_geral' => 99.00,
            'faturamento_pagamentos' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $semFaturamento = ErpDashboardSalesMixChart::data();
        $this->assertSame([], $semFaturamento['labels'] ?? []);

        $vendaEspelhoId = DB::table('vendas')->insertGetId([
            'numero' => 'MIX-ESP',
            'data' => $hoje,
            'hora' => '10:00:00',
            'cliente_id' => 1,
            'total' => 180.00,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_OS,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ordens_servico')->insert([
            'numero' => 'MIX-OK',
            'situacao' => OrdemServico::SITUACAO_FINALIZADA,
            'data_termino' => $hoje,
            'cliente_id' => 1,
            'venda_id' => $vendaEspelhoId,
            'total_geral' => 180.00,
            'faturamento_pagamentos' => json_encode([['forma' => 'dinheiro', 'valor' => 180]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pedido normal (não OS) — deve aparecer em Pedidos.
        DB::table('vendas')->insert([
            'numero' => 'MIX-PED',
            'data' => $hoje,
            'hora' => '11:00:00',
            'cliente_id' => 1,
            'total' => 50.00,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = ErpDashboardSalesMixChart::data();

        $this->assertSame(['Pedidos', 'OS'], $data['labels']);
        $this->assertEqualsWithDelta(50.0, (float) $data['values'][0], 0.01);
        $this->assertEqualsWithDelta(180.0, (float) $data['values'][1], 0.01);
        $this->assertCount(2, $data['colors']);
        $this->assertNotContains('PDV', $data['labels']);
    }

    private function criarSchemaMinimo(): void
    {
        Schema::dropIfExists('ordens_servico');
        Schema::dropIfExists('vendas');
        Schema::dropIfExists('pdv_vendas');

        Schema::create('vendas', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 30)->nullable();
            $table->string('data', 10)->nullable();
            $table->string('hora', 12)->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->string('status', 20)->nullable();
            $table->string('tipo', 20)->nullable();
            $table->string('plataforma', 20)->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ordens_servico', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 30)->nullable();
            $table->string('situacao', 30)->nullable();
            $table->string('data_termino', 10)->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->decimal('total_geral', 14, 2)->default(0);
            $table->text('faturamento_pagamentos')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamps();
        });
    }
}
