<?php

namespace Tests\Feature;

use App\Models\PlanoConta;
use App\Support\Erp\Dre\DreDespesasGerenciaisService;
use App\Support\Erp\Dre\DrePlanoClassificacaoCatalog;
use App\Support\Erp\Financeiro\ContaPagarCadastroService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DreDespesasCompetenciaD24Test extends TestCase
{
    private DreDespesasGerenciaisService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        $this->svc = app(DreDespesasGerenciaisService::class);
    }

    public function test_titulo_pago_nao_pago_e_parcial_por_competencia(): void
    {
        $emp = 1;
        $plano = $this->plano(5, 'ENERGIA');
        $this->svc->salvarClassificacao($emp, $plano->id, DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL);

        // Aberto (não pago)
        $this->titulo($emp, $plano->id, '2026-11-01', '2026-12-10', 100.00, 0);
        // Pago integral
        $this->titulo($emp, $plano->id, '2026-11-01', '2026-11-15', 50.00, 50.00);
        // Parcial
        $this->titulo($emp, $plano->id, '2026-11-01', '2026-11-20', 80.00, 30.00);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertTrue($r['competencia_disponivel']);
        $this->assertSame('230.00', $r['despesas_operacionais']);
        $this->assertSame('230.00', $r['despesas_operacionais_competencia']);
        $this->assertSame(3, $r['contagens']['titulos_competencia']);
        $this->assertSame(2, $r['contagens']['titulos_em_aberto_competencia']);
    }

    public function test_competencia_diferente_do_vencimento(): void
    {
        $emp = 1;
        $adm = $this->plano(10, 'CONTADOR');
        $this->svc->salvarClassificacao($emp, $adm->id, DrePlanoClassificacaoCatalog::DESPESA_ADMINISTRATIVA);

        // Competência nov/2026, vencimento jan/2027
        $this->titulo($emp, $adm->id, '2026-11-15', '2027-01-10', 200.00, 0);

        $nov = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $jan = $this->svc->calcular($emp, '2027-01-01', '2027-01-31');
        $this->assertSame('200.00', $nov['despesas_administrativas']);
        $this->assertSame('0.00', $jan['despesas_administrativas']);
        $this->assertSame('0.00', $jan['despesas_operacionais_competencia']);
    }

    public function test_antigo_sem_competencia_nao_presume_vencimento(): void
    {
        $emp = 1;
        $plano = $this->plano(5, 'ENERGIA');
        $this->svc->salvarClassificacao($emp, $plano->id, DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL);

        DB::table('contas_pagar')->insert([
            'empresa_id' => $emp,
            'numero' => 'OLD1',
            'emissao' => '2026-10-01',
            'vencimento' => '2026-11-10',
            'competencia' => null,
            'plano_conta_id' => $plano->id,
            'valor' => 99,
            'desconto' => 0,
            'juros' => 0,
            'multa' => 0,
            'valor_pago' => 0,
            'saldo' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('0.00', $r['despesas_operacionais_competencia']);
        $this->assertSame(1, $r['pendencias_sem_competencia_qtd']);
        $this->assertSame('99.00', $r['pendencias_sem_competencia_valor']);
    }

    public function test_nao_duplica_com_pagamento_e_caixa(): void
    {
        $emp = 1;
        $fin = $this->plano(13, 'TAXA');
        $this->svc->salvarClassificacao($emp, $fin->id, DrePlanoClassificacaoCatalog::DESPESA_FINANCEIRA);

        $cpId = $this->titulo($emp, $fin->id, '2026-11-05', '2026-11-20', 40.00, 40.00);
        $pagId = DB::table('conta_pagar_pagamentos')->insertGetId([
            'conta_pagar_id' => $cpId,
            'data' => '2026-11-20',
            'valor_parcela' => 40,
            'valor_pago' => 40,
            'plano_conta_id' => $fin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('caixa_lancamentos')->insert([
            'empresa_id' => $emp,
            'codigo' => 1,
            'emissao' => '2026-11-20',
            'documento' => 'BX1',
            'historico' => 'Pagamento conta a pagar #1 baixa:'.$pagId,
            'plano_conta_id' => $fin->id,
            'entrada' => 0,
            'saida' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('40.00', $r['despesas_financeiras_competencia']);
        $this->assertSame('40.00', $r['caixa_despesas_financeiras']); // pagamento (caixa espelho excluído)
        $this->assertSame(1, $r['contagens']['caixa_espelho_cp_excluidos']);
    }

    public function test_exclui_patrimonial_e_isolamento_empresa(): void
    {
        $ret = $this->plano(11, 'RETIRADA');
        $this->svc->salvarClassificacao(1, $ret->id, DrePlanoClassificacaoCatalog::PATRIMONIAL);
        $this->svc->salvarClassificacao(2, $ret->id, DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL);
        $this->titulo(1, $ret->id, '2026-11-01', '2026-11-30', 500.00, 0);
        $this->titulo(2, $ret->id, '2026-11-01', '2026-11-30', 70.00, 0);

        $r1 = $this->svc->calcular(1, '2026-11-01', '2026-11-30');
        $r2 = $this->svc->calcular(2, '2026-11-01', '2026-11-30');
        $this->assertSame('0.00', $r1['despesas_operacionais_competencia']);
        $this->assertSame('70.00', $r2['despesas_operacionais']);
    }

    public function test_atualizar_competencia_em_titulo_baixado_sem_alterar_valor(): void
    {
        $emp = 1;
        $plano = $this->plano(5, 'ENERGIA');
        $id = $this->titulo($emp, null, null, '2026-11-10', 55.00, 55.00);

        $antes = DB::table('contas_pagar')->where('id', $id)->first();
        app(ContaPagarCadastroService::class)->atualizarCompetenciaDre($id, '2026-11-01', $plano->id);
        $depois = DB::table('contas_pagar')->where('id', $id)->first();

        $this->assertSame('2026-11-01', substr((string) $depois->competencia, 0, 10));
        $this->assertSame((int) $plano->id, (int) $depois->plano_conta_id);
        $this->assertEquals((float) $antes->valor, (float) $depois->valor);
        $this->assertEquals((float) $antes->valor_pago, (float) $depois->valor_pago);
    }

    public function test_grupos_operacional_adm_comercial_financeiro(): void
    {
        $emp = 1;
        $op = $this->plano(5, 'OP');
        $adm = $this->plano(6, 'ADM');
        $com = $this->plano(7, 'COM');
        $fin = $this->plano(8, 'FIN');
        $this->svc->salvarClassificacao($emp, $op->id, DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL);
        $this->svc->salvarClassificacao($emp, $adm->id, DrePlanoClassificacaoCatalog::DESPESA_ADMINISTRATIVA);
        $this->svc->salvarClassificacao($emp, $com->id, DrePlanoClassificacaoCatalog::DESPESA_COMERCIAL);
        $this->svc->salvarClassificacao($emp, $fin->id, DrePlanoClassificacaoCatalog::DESPESA_FINANCEIRA);

        $this->titulo($emp, $op->id, '2026-11-01', '2026-11-10', 10, 0);
        $this->titulo($emp, $adm->id, '2026-11-01', '2026-11-10', 20, 0);
        $this->titulo($emp, $com->id, '2026-11-01', '2026-11-10', 30, 0);
        $this->titulo($emp, $fin->id, '2026-11-01', '2026-11-10', 40, 0);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('10.00', $r['despesas_operacionais']);
        $this->assertSame('20.00', $r['despesas_administrativas']);
        $this->assertSame('30.00', $r['despesas_comerciais']);
        $this->assertSame('60.00', $r['despesas_operacionais_competencia']);
        $this->assertSame('40.00', $r['despesas_financeiras_competencia']);
    }

    private function plano(int $codigo, string $desc): PlanoConta
    {
        return PlanoConta::query()->create([
            'codigo' => $codigo,
            'descricao' => $desc,
            'dc' => 'D',
            'ativo' => true,
        ]);
    }

    private function titulo(
        int $empresaId,
        ?int $planoId,
        ?string $competencia,
        string $vencimento,
        float $valor,
        float $pago,
    ): int {
        return (int) DB::table('contas_pagar')->insertGetId([
            'empresa_id' => $empresaId,
            'numero' => 'T'.uniqid(),
            'emissao' => $competencia ?? $vencimento,
            'vencimento' => $vencimento,
            'competencia' => $competencia,
            'plano_conta_id' => $planoId,
            'valor' => $valor,
            'desconto' => 0,
            'juros' => 0,
            'multa' => 0,
            'valor_pago' => $pago,
            'saldo' => max(0, $valor - $pago),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMinimalSchema(): void
    {
        foreach ([
            'dre_plano_classificacoes', 'conta_pagar_pagamentos', 'contas_pagar',
            'caixa_lancamentos', 'planos_contas',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('planos_contas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('codigo')->unique();
            $table->string('descricao', 120)->nullable();
            $table->string('dc', 1)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('contas_pagar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero', 40)->nullable();
            $table->date('emissao')->nullable();
            $table->date('vencimento')->nullable();
            $table->date('competencia')->nullable();
            $table->unsignedBigInteger('plano_conta_id')->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('juros', 15, 2)->default(0);
            $table->decimal('multa', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->decimal('saldo', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('conta_pagar_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conta_pagar_id');
            $table->date('data')->nullable();
            $table->decimal('valor_parcela', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->unsignedBigInteger('plano_conta_id')->nullable();
            $table->timestamps();
        });
        Schema::create('caixa_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->default(0);
            $table->date('emissao')->nullable();
            $table->string('documento', 40)->nullable();
            $table->string('historico', 255)->nullable();
            $table->unsignedBigInteger('plano_conta_id')->nullable();
            $table->decimal('entrada', 15, 2)->default(0);
            $table->decimal('saida', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('dre_plano_classificacoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('plano_conta_id');
            $table->string('classificacao', 40);
            $table->timestamps();
            $table->unique(['empresa_id', 'plano_conta_id']);
        });
    }
}
