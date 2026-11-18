<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Support\Erp\Dre\DreDemonstrativoGerencialService;
use App\Support\Erp\Dre\DreTributosInformativosService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DreTributosInformativosD271Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    public function test_crt3_soma_tributos_sem_deduzir_receita(): void
    {
        $emp = $this->empresa('presumido');
        $this->nfe($emp->id, [
            'total' => 1000,
            'total_icms' => 120,
            'valor_icms_st' => 30,
            'total_icms_pis' => 16.50,
            'total_icms_cofins' => 76,
        ]);
        // Cancelada — ignorada.
        $this->nfe($emp->id, [
            'total' => 500,
            'total_icms' => 50,
            'status' => Nfe::STATUS_CANCELADA,
            'situacao' => Nfe::SITUACAO_CANCELADA,
        ]);
        // Entrada — ignorada.
        $this->nfe($emp->id, [
            'total' => 200,
            'total_icms' => 20,
            'movimento' => '0',
        ]);

        $t = app(DreTributosInformativosService::class)->calcular(
            (int) $emp->id,
            '2026-11-01',
            '2026-11-30',
            '1000.00',
        );

        $this->assertTrue($t['disponivel']);
        $this->assertSame(3, $t['crt']);
        $this->assertSame('120.00', $t['icms_proprio']);
        $this->assertSame('30.00', $t['icms_st_cobrada']);
        $this->assertSame('16.50', $t['pis']);
        $this->assertSame('76.00', $t['cofins']);
        $this->assertSame('242.50', $t['total_destacado']);
        $this->assertSame(1, $t['docs_considerados']);
        $this->assertGreaterThan(0, $t['cobertura_pct']);

        $demo = app(DreDemonstrativoGerencialService::class)->montar([
            'empresa_id' => (int) $emp->id,
            'periodo_de' => '2026-11-01',
            'periodo_ate' => '2026-11-30',
            'receita_bruta_operacional' => '1000.00',
            'devolucoes' => '0.00',
            'receita_liquida_operacional' => '1000.00',
            'descontos_embutidos_informativo' => '0.00',
        ], [
            'dre_parametros' => [
                'exibir_tributos' => true,
                'calcular_estimativa_simples' => false,
                'aliquota_simples_pct' => null,
                'crt' => 3,
                'regime' => 'presumido',
                'permite_estimativa_simples' => false,
            ],
            'tributos_informativos' => $t,
            'cmv_historico' => [
                'cmv_disponivel' => false,
                'cmv' => null,
                'cmv_liquido' => null,
                'cobertura_pct' => 0,
                'pendencia' => 'Sem CMV',
            ],
            'despesas_operacionais' => '0.00',
            'despesas_administrativas' => '0.00',
            'despesas_comerciais' => '0.00',
            'despesas_financeiras_competencia' => '0.00',
            'custos_competencia' => '0.00',
        ]);

        $this->assertFalse($demo['impostos_disponivel']);
        $this->assertNull($demo['impostos']);
        $this->assertSame('1000.00', $demo['receita_liquida']);
        $this->assertTrue($demo['tributos_exibir']);
        $this->assertSame('242.50', $demo['tributos_informativos']['total_destacado']);
    }

    public function test_simples_fica_pendente_sem_valores(): void
    {
        $emp = $this->empresa('simples');
        $this->nfe($emp->id, ['total' => 100, 'total_icms' => 0]);

        $t = app(DreTributosInformativosService::class)->calcular((int) $emp->id, '2026-11-01', '2026-11-30', '100.00');
        $this->assertFalse($t['disponivel']);
        $this->assertNull($t['icms_proprio']);
        $this->assertStringContainsString('Simples', $t['pendencia']);
        $this->assertStringContainsString('não disponível', mb_strtolower($t['pendencia']));
    }

    public function test_isolamento_empresa(): void
    {
        $e1 = $this->empresa('real');
        $e2 = $this->empresa('normal');
        $this->nfe($e1->id, ['total' => 100, 'total_icms' => 10]);
        $this->nfe($e2->id, ['total' => 100, 'total_icms' => 99]);

        $t1 = app(DreTributosInformativosService::class)->calcular((int) $e1->id, '2026-11-01', '2026-11-30');
        $t2 = app(DreTributosInformativosService::class)->calcular((int) $e2->id, '2026-11-01', '2026-11-30');
        $this->assertSame('10.00', $t1['icms_proprio']);
        $this->assertSame('99.00', $t2['icms_proprio']);
    }

    public function test_resultado_operacional_gerencial_sem_deduzir_imposto(): void
    {
        $demo = app(DreDemonstrativoGerencialService::class)->montar([
            'empresa_id' => 1,
            'periodo_de' => '2026-11-01',
            'periodo_ate' => '2026-11-30',
            'receita_bruta_operacional' => '200.00',
            'devolucoes' => '0.00',
            'receita_liquida_operacional' => '200.00',
            'descontos_embutidos_informativo' => '0.00',
        ], [
            'dre_parametros' => [
                'exibir_tributos' => true,
                'calcular_estimativa_simples' => false,
                'crt' => 3,
                'regime' => 'presumido',
                'permite_estimativa_simples' => false,
            ],
            'cmv_historico' => [
                'cmv_disponivel' => true,
                'cmv' => '40.00',
                'cmv_liquido' => '40.00',
                'cobertura_pct' => 100,
                'pendencia' => '',
            ],
            'tributos_informativos' => [
                'disponivel' => true,
                'total_destacado' => '50.00',
                'pendencia' => 'Informativo',
                'icms_proprio' => '50.00',
                'crt' => 3,
            ],
            'despesas_operacionais' => '20.00',
            'despesas_administrativas' => '10.00',
            'despesas_comerciais' => '5.00',
            'despesas_financeiras_competencia' => '5.00',
            'custos_competencia' => '80.00',
        ]);

        $this->assertTrue($demo['resultado_bruto_disponivel']);
        $this->assertSame('160.00', $demo['resultado_bruto']);
        $this->assertTrue($demo['resultado_operacional_disponivel']);
        // 160 - 20 - 10 - 5 - 5 = 120 (custos CP e impostos fora)
        $this->assertSame('120.00', $demo['resultado_operacional']);
        $this->assertSame('80.00', $demo['custos_cp_competencia']);
        $this->assertFalse($demo['impostos_disponivel']);
    }

    private function empresa(string $regime): Empresa
    {
        return Empresa::query()->create([
            'codigo' => 'E'.uniqid(),
            'nome' => 'EMP',
            'razao_social' => 'EMP',
            'regime_tributario' => $regime,
            'ativo' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function nfe(int $empresaId, array $attrs): void
    {
        DB::table('nfes')->insert(array_merge([
            'empresa_id' => $empresaId,
            'numero' => random_int(1, 99999),
            'serie' => '1',
            'data_emissao' => '2026-11-10',
            'status' => Nfe::STATUS_TRANSMITIDA,
            'situacao' => Nfe::SITUACAO_TRANSMITIDA,
            'finalidade' => '1',
            'movimento' => '1',
            'total' => 0,
            'total_icms' => 0,
            'valor_icms_st' => 0,
            'total_icms_pis' => 0,
            'total_icms_cofins' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('nfes');
        Schema::dropIfExists('empresas');

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 120)->nullable();
            $table->string('razao_social', 120)->nullable();
            $table->string('regime_tributario', 40)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('nfes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedInteger('numero')->nullable();
            $table->string('serie', 5)->nullable();
            $table->date('data_emissao')->nullable();
            $table->string('status', 20)->nullable();
            $table->string('situacao', 5)->nullable();
            $table->string('finalidade', 20)->nullable();
            $table->string('movimento', 10)->nullable();
            $table->unsignedBigInteger('devolucao_compra_id')->nullable();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->unsignedBigInteger('pdv_venda_id')->nullable();
            $table->unsignedBigInteger('ordem_servico_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('total_icms', 15, 2)->default(0);
            $table->decimal('valor_icms_st', 15, 2)->default(0);
            $table->decimal('total_icms_pis', 15, 2)->default(0);
            $table->decimal('total_icms_cofins', 15, 2)->default(0);
            $table->timestamps();
        });
    }
}
