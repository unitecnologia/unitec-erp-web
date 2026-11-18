<?php

namespace Tests\Feature;

use App\Models\DrePlanoClassificacao;
use App\Models\PlanoConta;
use App\Support\Erp\Dre\DreDespesasGerenciaisService;
use App\Support\Erp\Dre\DrePlanoClassificacaoCatalog;
use App\Support\Erp\Dre\DreReceitasGerenciaisService;
use App\Support\Erp\Financeiro\CaixaTransferencia;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DreDespesasGerenciaisD23Test extends TestCase
{
    private DreDespesasGerenciaisService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        $this->svc = app(DreDespesasGerenciaisService::class);
    }

    public function test_classificacao_por_plano_e_preserva_historico_do_plano(): void
    {
        $emp = 1;
        $plano = $this->plano(10, 'ENERGIA', 'D');
        $descAntes = $plano->descricao;
        $dcAntes = $plano->dc;

        $this->svc->salvarClassificacao($emp, $plano->id, DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL);
        $mapa = $this->svc->mapaClassificacao($emp);
        $this->assertSame(DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL, $mapa[$plano->id]);

        $plano->refresh();
        $this->assertSame($descAntes, $plano->descricao);
        $this->assertSame($dcAntes, $plano->dc);
        $this->assertSame(1, DrePlanoClassificacao::query()->count());
    }

    public function test_despesas_operacionais_e_financeiras_visao_caixa(): void
    {
        $emp = 1;
        $op = $this->plano(5, 'ENERGIA', 'D');
        $fin = $this->plano(13, 'TAXA CARTAO', 'D');
        $this->svc->salvarClassificacao($emp, $op->id, DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL);
        $this->svc->salvarClassificacao($emp, $fin->id, DrePlanoClassificacaoCatalog::DESPESA_FINANCEIRA);

        $this->pagamentoCp($emp, $op->id, '2026-11-10', 100.00);
        $this->pagamentoCp($emp, $fin->id, '2026-11-11', 25.50);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        // D2.4: com coluna competência no schema de teste, apuração por título fica disponível (vazia aqui).
        $this->assertTrue($r['competencia_disponivel']);
        $this->assertSame('0.00', $r['despesas_operacionais_competencia']);
        $this->assertSame('0.00', $r['despesas_financeiras_competencia']);
        $this->assertSame('100.00', $r['caixa_despesas_operacionais_grupo']);
        $this->assertSame('25.50', $r['caixa_despesas_financeiras']);
        $this->assertSame('125.50', $r['caixa_despesas_total_informativo']);
    }

    public function test_pagamento_cp_com_espelho_caixa_nao_duplica(): void
    {
        $emp = 1;
        $plano = $this->plano(5, 'ENERGIA', 'D');
        $this->svc->salvarClassificacao($emp, $plano->id, DrePlanoClassificacaoCatalog::DESPESA_ADMINISTRATIVA);

        $pagId = $this->pagamentoCp($emp, $plano->id, '2026-11-12', 80.00);
        $this->caixaSaida($emp, $plano->id, '2026-11-12', 80.00, 'DOC1', 'Pagamento conta a pagar #1 baixa:'.$pagId);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('80.00', $r['caixa_despesas_operacionais_grupo']);
        $this->assertSame(1, $r['contagens']['pagamentos_cp']);
        $this->assertSame(0, $r['contagens']['caixa_saidas_operacionais']);
        $this->assertSame(1, $r['contagens']['caixa_espelho_cp_excluidos']);
        $this->assertSame('80.00', $r['caixa_excluidos']['espelho_cp']);
    }

    public function test_pagamento_parcial(): void
    {
        $emp = 1;
        $plano = $this->plano(9, 'AGUA', 'D');
        $this->svc->salvarClassificacao($emp, $plano->id, DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL);
        $this->pagamentoCp($emp, $plano->id, '2026-11-05', 30.00);
        $this->pagamentoCp($emp, $plano->id, '2026-11-20', 20.00);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('50.00', $r['caixa_despesas_operacionais']);
        $this->assertSame(2, $r['contagens']['pagamentos_cp']);
    }

    public function test_despesa_sem_plano_vai_para_nao_classificada(): void
    {
        $emp = 1;
        $this->pagamentoCp($emp, null, '2026-11-08', 40.00);
        $this->caixaSaida($emp, null, '2026-11-09', 15.00, 'SX1', 'Saida avulsa');

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('55.00', $r['caixa_nao_classificadas']);
        $this->assertSame('0.00', $r['caixa_despesas_total_informativo']);
    }

    public function test_despesa_sem_competencia_permanece_pendente(): void
    {
        $emp = 1;
        $plano = $this->plano(5, 'ENERGIA', 'D');
        $this->svc->salvarClassificacao($emp, $plano->id, DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL);
        $this->pagamentoCp($emp, $plano->id, '2026-11-10', 10.00);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertTrue($r['competencia_disponivel']);
        $this->assertSame('0.00', $r['despesas_operacionais_competencia']);
        $this->assertGreaterThan(0, $r['pendencias_sem_competencia_qtd']);
        $this->assertStringContainsString('competência', mb_strtolower($r['competencia_motivo']));
    }

    public function test_exclui_transferencias(): void
    {
        $emp = 1;
        $plano = $this->plano(4, 'TRANSFERENCIA D', 'D');
        $this->svc->salvarClassificacao($emp, $plano->id, DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL);
        $doc = CaixaTransferencia::DOCUMENTO_PREFIXO.'ABC';
        $this->caixaSaida($emp, $plano->id, '2026-11-10', 200.00, $doc, CaixaTransferencia::HISTORICO_PREFIXO.' X');

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('0.00', $r['caixa_despesas_operacionais_grupo']);
        $this->assertSame('200.00', $r['caixa_excluidos']['transferencias']);
        $this->assertSame(1, $r['contagens']['caixa_transferencias_excluidas']);
    }

    public function test_exclui_operacoes_patrimoniais(): void
    {
        $emp = 1;
        $plano = $this->plano(11, 'RETIRADA', 'D');
        $this->svc->salvarClassificacao($emp, $plano->id, DrePlanoClassificacaoCatalog::PATRIMONIAL);
        $this->pagamentoCp($emp, $plano->id, '2026-11-10', 500.00);
        $this->caixaSaida($emp, $plano->id, '2026-11-11', 50.00, 'R1', 'Retirada socio');

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('0.00', $r['caixa_despesas_total_informativo']);
        $this->assertSame('550.00', $r['caixa_excluidos']['patrimonial']);
    }

    public function test_isolamento_multiempresa(): void
    {
        $op = $this->plano(5, 'ENERGIA', 'D');
        $this->svc->salvarClassificacao(1, $op->id, DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL);
        $this->svc->salvarClassificacao(2, $op->id, DrePlanoClassificacaoCatalog::DESPESA_FINANCEIRA);
        $this->pagamentoCp(1, $op->id, '2026-11-10', 70.00);
        $this->pagamentoCp(2, $op->id, '2026-11-10', 90.00);

        $r1 = $this->svc->calcular(1, '2026-11-01', '2026-11-30');
        $r2 = $this->svc->calcular(2, '2026-11-01', '2026-11-30');
        $this->assertSame('70.00', $r1['caixa_despesas_operacionais']);
        $this->assertSame('0.00', $r1['caixa_despesas_financeiras']);
        $this->assertSame('0.00', $r2['caixa_despesas_operacionais']);
        $this->assertSame('90.00', $r2['caixa_despesas_financeiras']);
    }

    public function test_nao_reclassifica_automaticamente_nem_altera_financeiro(): void
    {
        $emp = 1;
        $plano = $this->plano(5, 'ENERGIA', 'D');
        $pagAntes = DB::table('conta_pagar_pagamentos')->count();
        $cxAntes = DB::table('caixa_lancamentos')->count();
        $this->pagamentoCp($emp, $plano->id, '2026-11-10', 12.00);

        $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame(0, DrePlanoClassificacao::query()->count());
        $this->assertSame($pagAntes + 1, DB::table('conta_pagar_pagamentos')->count());
        $this->assertSame($cxAntes, DB::table('caixa_lancamentos')->count());
        $this->assertSame('ENERGIA', PlanoConta::query()->whereKey($plano->id)->value('descricao'));
    }

    public function test_regressao_d21_receitas_inalteradas(): void
    {
        Schema::create('vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero', 40);
            $table->date('data');
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20);
            $table->string('tipo', 20)->default('pedido');
            $table->string('plataforma', 20)->nullable();
            $table->timestamps();
        });

        DB::table('vendas')->insert([
            'empresa_id' => 1,
            'numero' => 'V1',
            'data' => '2026-11-10',
            'cliente_id' => 1,
            'total' => 100,
            'status' => 'fechado',
            'tipo' => 'pedido',
            'plataforma' => 'erp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rec = app(DreReceitasGerenciaisService::class)->calcular(1, '2026-11-01', '2026-11-30');
        $this->assertSame('100.00', $rec['receita_mercadorias']);
        $this->assertSame('100.00', $rec['receita_liquida_operacional']);
    }

    private function plano(int $codigo, string $desc, string $dc): PlanoConta
    {
        return PlanoConta::query()->create([
            'codigo' => $codigo,
            'descricao' => $desc,
            'dc' => $dc,
            'ativo' => true,
        ]);
    }

    private function pagamentoCp(int $empresaId, ?int $planoId, string $data, float $valor): int
    {
        $cpId = DB::table('contas_pagar')->insertGetId([
            'empresa_id' => $empresaId,
            'numero' => 'CP'.uniqid(),
            'emissao' => $data,
            'vencimento' => $data,
            'valor' => $valor,
            'desconto' => 0,
            'juros' => 0,
            'valor_pago' => $valor,
            'saldo' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('conta_pagar_pagamentos')->insertGetId([
            'conta_pagar_id' => $cpId,
            'data' => $data,
            'valor_parcela' => $valor,
            'valor_pago' => $valor,
            'plano_conta_id' => $planoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function caixaSaida(
        int $empresaId,
        ?int $planoId,
        string $emissao,
        float $saida,
        string $documento,
        string $historico,
    ): void {
        DB::table('caixa_lancamentos')->insert([
            'empresa_id' => $empresaId,
            'codigo' => (int) (DB::table('caixa_lancamentos')->max('codigo') ?? 0) + 1,
            'emissao' => $emissao,
            'documento' => $documento,
            'historico' => $historico,
            'plano_conta_id' => $planoId,
            'entrada' => 0,
            'saida' => $saida,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMinimalSchema(): void
    {
        foreach ([
            'dre_plano_classificacoes',
            'conta_pagar_pagamentos',
            'contas_pagar',
            'caixa_lancamentos',
            'planos_contas',
            'vendas',
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
