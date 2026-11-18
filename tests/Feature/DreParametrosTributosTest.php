<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Support\Erp\Dre\DreDemonstrativoGerencialService;
use App\Support\Erp\Dre\DreEmpresaParametrosService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DreParametrosTributosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    public function test_padrao_nao_exibe_tributos_nem_estimativa(): void
    {
        $e = $this->empresa('simples');
        $p = app(DreEmpresaParametrosService::class)->obter((int) $e->id);
        $this->assertFalse($p['exibir_tributos']);
        $this->assertFalse($p['calcular_estimativa_simples']);
        $this->assertNull($p['aliquota_simples_pct']);
        $this->assertTrue($p['permite_estimativa_simples']);

        $demo = $this->montar((int) $e->id, [
            'dre_parametros' => $p,
            'tributos_informativos' => ['crt' => 1, 'disponivel' => false],
        ]);
        $this->assertFalse($demo['tributos_exibir']);
        $this->assertFalse($demo['tributos_estimativa']['ativa']);
        $this->assertStringNotContainsString('Simples', implode(' ', $demo['pendencias']));
        $this->assertStringContainsString('CMV', implode(' ', $demo['pendencias']));
    }

    public function test_estimativa_simples_10_porcento(): void
    {
        $e = $this->empresa('simples');
        app(DreEmpresaParametrosService::class)->salvar((int) $e->id, true, true, '10');

        $p = app(DreEmpresaParametrosService::class)->obter((int) $e->id);
        $demo = $this->montar((int) $e->id, [
            'dre_parametros' => $p,
            'cmv_historico' => [
                'cmv_disponivel' => true,
                'cmv' => '100.00',
                'cmv_liquido' => '100.00',
                'cobertura_pct' => 100,
                'pendencia' => '',
            ],
        ], liquida: '14306.99');

        $this->assertTrue($demo['tributos_exibir']);
        $this->assertTrue($demo['tributos_estimativa']['ativa']);
        $this->assertSame('1430.70', $demo['tributos_estimativa']['valor']);
        $this->assertFalse($demo['tributos_estimativa']['deduzida_nos_resultados']);
        // Resultados não incluem a estimativa (anti-duplicidade DAS).
        $this->assertSame('14206.99', $demo['resultado_bruto']);
        $this->assertSame('14206.99', $demo['resultado_operacional']);
    }

    public function test_presumido_nao_permite_estimativa_simples(): void
    {
        $e = $this->empresa('presumido');
        app(DreEmpresaParametrosService::class)->salvar((int) $e->id, true, true, '10');
        $p = app(DreEmpresaParametrosService::class)->obter((int) $e->id);
        $this->assertFalse($p['permite_estimativa_simples']);
        $this->assertFalse($p['calcular_estimativa_simples']);

        $demo = $this->montar((int) $e->id, [
            'dre_parametros' => $p,
            'tributos_informativos' => [
                'exibir' => true,
                'disponivel' => true,
                'crt' => 3,
                'regime' => 'presumido',
                'icms_proprio' => '10.00',
                'icms_st_cobrada' => '0.00',
                'pis' => '1.00',
                'cofins' => '2.00',
                'total_destacado' => '13.00',
                'docs_considerados' => 1,
                'cobertura_pct' => 50,
                'pendencia' => '',
            ],
        ]);
        $this->assertFalse($demo['tributos_estimativa']['ativa']);
        $this->assertTrue($demo['tributos_exibir']);
    }

    public function test_mei_nao_permite_estimativa(): void
    {
        $e = $this->empresa('mei');
        $p = app(DreEmpresaParametrosService::class)->obter((int) $e->id);
        $this->assertFalse($p['permite_estimativa_simples']);
    }

    public function test_isolamento_multiempresa(): void
    {
        $e1 = $this->empresa('simples');
        $e2 = $this->empresa('simples');
        app(DreEmpresaParametrosService::class)->salvar((int) $e1->id, true, true, '8');
        app(DreEmpresaParametrosService::class)->salvar((int) $e2->id, false, false, null);

        $p1 = app(DreEmpresaParametrosService::class)->obter((int) $e1->id);
        $p2 = app(DreEmpresaParametrosService::class)->obter((int) $e2->id);
        $this->assertTrue($p1['exibir_tributos']);
        $this->assertSame('8.0000', $p1['aliquota_simples_pct']);
        $this->assertFalse($p2['exibir_tributos']);
        $this->assertNull($p2['aliquota_simples_pct']);
    }

    public function test_estimativa_nao_desbloqueia_sem_cmv(): void
    {
        $e = $this->empresa('simples');
        app(DreEmpresaParametrosService::class)->salvar((int) $e->id, true, true, '10');
        $p = app(DreEmpresaParametrosService::class)->obter((int) $e->id);
        $demo = $this->montar((int) $e->id, [
            'dre_parametros' => $p,
            'cmv_historico' => [
                'cmv_disponivel' => false,
                'cmv' => null,
                'cobertura_pct' => 0,
                'pendencia' => 'CMV incompleto',
            ],
        ], liquida: '1000.00');

        $this->assertTrue($demo['tributos_estimativa']['ativa']);
        $this->assertSame('100.00', $demo['tributos_estimativa']['valor']);
        $this->assertFalse($demo['resultado_bruto_disponivel']);
        $this->assertFalse($demo['resultado_operacional_disponivel']);
        $this->assertNull($demo['resultado_bruto']);
    }

    /**
     * @param  array<string, mixed>  $despesasExtra
     * @return array<string, mixed>
     */
    private function montar(int $empresaId, array $despesasExtra = [], string $liquida = '100.00'): array
    {
        return app(DreDemonstrativoGerencialService::class)->montar([
            'empresa_id' => $empresaId,
            'periodo_de' => '2026-11-01',
            'periodo_ate' => '2026-11-30',
            'receita_bruta_operacional' => $liquida,
            'receita_mercadorias' => $liquida,
            'receita_servicos' => '0.00',
            'receita_pecas_os' => '0.00',
            'devolucoes' => '0.00',
            'receita_liquida_operacional' => $liquida,
            'descontos_embutidos_informativo' => '0.00',
        ], array_merge([
            'despesas_operacionais' => '0.00',
            'despesas_administrativas' => '0.00',
            'despesas_comerciais' => '0.00',
            'despesas_financeiras_competencia' => '0.00',
            'custos_competencia' => '0.00',
            'cmv_historico' => [
                'cmv_disponivel' => false,
                'cmv' => null,
                'cobertura_pct' => 0,
                'pendencia' => 'CMV incompleto',
            ],
            'contagens' => ['pendencias_sem_competencia' => 0],
        ], $despesasExtra));
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

    private function createSchema(): void
    {
        Schema::dropIfExists('dre_empresa_parametros');
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
        Schema::create('dre_empresa_parametros', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->unique();
            $table->boolean('exibir_tributos')->default(false);
            $table->boolean('calcular_estimativa_simples')->default(false);
            $table->decimal('aliquota_simples_pct', 7, 4)->nullable();
            $table->timestamps();
        });
    }
}
