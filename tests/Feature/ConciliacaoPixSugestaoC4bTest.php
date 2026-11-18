<?php

namespace Tests\Feature;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\Empresa;
use App\Models\ExtratoImportacao;
use App\Models\ExtratoLancamento;
use App\Models\PixCobranca;
use App\Models\User;
use App\Support\Erp\Financeiro\Conciliacao\ConciliacaoPixSugestaoService;
use App\Support\Erp\Financeiro\Conciliacao\PixSugestaoResultado;
use App\Support\Pix\Ailos\AilosPixService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConciliacaoPixSugestaoC4bTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        config([
            'unitec.conciliacao.c3_date_window_days' => 3,
            'unitec.conciliacao.c4b_date_window_days' => 3,
            'unitec.conciliacao.c4b_candidates_max' => 20,
        ]);
    }

    public function test_forte_por_txid_com_caixa_na_mesma_conta(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        $txid = $this->txidAilos('FORTE001');

        $pix = PixCobranca::query()->create([
            'empresa_id' => $empresa->id,
            'origem' => PixCobranca::ORIGEM_TITULO,
            'provedor' => 'ailos',
            'txid' => $txid,
            'provider_ref' => $txid,
            'valor' => 150.00,
            'status' => PixCobranca::STATUS_PAGO,
            'pago_em' => '2026-10-10 12:00:00',
        ]);

        $caixa = $this->criarCaixa($empresa, $banco, 150.00, 0, '2026-10-10', 'VENDA PDV PIX '.$txid);
        $extrato = $this->criarExtrato($empresa, $banco, 'credito', '150.00', '2026-10-10', 'CREDITO PIX '.$txid);

        $r = app(ConciliacaoPixSugestaoService::class)->sugerir($extrato);

        $this->assertSame(PixSugestaoResultado::CLASS_FORTE, $r->classificacao);
        $this->assertCount(1, $r->candidatos);
        $this->assertSame((int) $caixa->id, $r->candidatos[0]->origemId);
        $this->assertSame((int) $pix->id, (int) ($r->candidatos[0]->evidencias['pix_cobranca_id'] ?? 0));
        $this->assertSame(ExtratoLancamento::SITUACAO_PENDENTE, $extrato->fresh()->situacao);
    }

    public function test_possivel_apenas_valor_e_data_no_caixa(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        $this->criarCaixa($empresa, $banco, 80.00, 0, '2026-10-11', 'Recebimento PIX #9');
        $extrato = $this->criarExtrato($empresa, $banco, 'credito', '80.00', '2026-10-11', 'CREDITO PIX - CLIENTE X');

        $r = app(ConciliacaoPixSugestaoService::class)->sugerir($extrato);

        $this->assertSame(PixSugestaoResultado::CLASS_POSSIVEL, $r->classificacao);
        $this->assertCount(1, $r->candidatos);
    }

    public function test_identificadores_diferentes_fitid_nao_vira_txid(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        $txid = $this->txidAilos('DIFF0001');
        PixCobranca::query()->create([
            'empresa_id' => $empresa->id,
            'origem' => 'titulo',
            'provedor' => 'ailos',
            'txid' => $txid,
            'provider_ref' => $txid,
            'valor' => 10.00,
            'status' => PixCobranca::STATUS_PAGO,
            'pago_em' => '2026-10-12 10:00:00',
        ]);
        $this->criarCaixa($empresa, $banco, 10.00, 0, '2026-10-12', 'Recebimento PIX');

        $extrato = $this->criarExtrato(
            $empresa,
            $banco,
            'credito',
            '10.00',
            '2026-10-12',
            'CREDITO PIX - SEM TXID',
            fitid: 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
        );

        $r = app(ConciliacaoPixSugestaoService::class)->sugerir($extrato);

        $this->assertNotSame(PixSugestaoResultado::CLASS_FORTE, $r->classificacao);
        $this->assertTrue(collect($r->avisos)->contains(fn ($a) => str_contains($a, 'FITID')));
    }

    public function test_pix_entrada_e_saida(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        $this->criarCaixa($empresa, $banco, 25.00, 0, '2026-10-13', 'PIX IN');
        $this->criarCaixa($empresa, $banco, 0, 40.00, '2026-10-13', 'PIX OUT');

        $ent = $this->criarExtrato($empresa, $banco, 'credito', '25.00', '2026-10-13', 'CREDITO PIX - A');
        $sai = $this->criarExtrato($empresa, $banco, 'debito', '40.00', '2026-10-13', 'DEBITO PIX - B');

        $rIn = app(ConciliacaoPixSugestaoService::class)->sugerir($ent);
        $rOut = app(ConciliacaoPixSugestaoService::class)->sugerir($sai);

        $this->assertSame(PixSugestaoResultado::CLASS_POSSIVEL, $rIn->classificacao);
        $this->assertSame(PixSugestaoResultado::CLASS_POSSIVEL, $rOut->classificacao);
        $this->assertSame(25.0, $rIn->candidatos[0]->valor);
        $this->assertSame(40.0, $rOut->candidatos[0]->valor);
    }

    public function test_dois_pagamentos_mesmo_valor_fica_ambiguo(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        $this->criarCaixa($empresa, $banco, 50.00, 0, '2026-10-14', 'PIX A');
        $this->criarCaixa($empresa, $banco, 50.00, 0, '2026-10-14', 'PIX B');
        $extrato = $this->criarExtrato($empresa, $banco, 'credito', '50.00', '2026-10-14', 'CREDITO PIX');

        $r = app(ConciliacaoPixSugestaoService::class)->sugerir($extrato);

        $this->assertSame(PixSugestaoResultado::CLASS_AMBIGUA, $r->classificacao);
        $this->assertGreaterThanOrEqual(2, count($r->candidatos));
    }

    public function test_pagamento_outra_empresa_ignorado(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        $outra = Empresa::query()->create([
            'codigo' => 'OTR',
            'nome' => 'OUTRA',
            'fantasia' => 'OTR',
            'ativo' => true,
        ]);
        PixCobranca::query()->create([
            'empresa_id' => $outra->id,
            'origem' => 'titulo',
            'provedor' => 'ailos',
            'txid' => $this->txidAilos('OUTRA001'),
            'provider_ref' => $this->txidAilos('OUTRA001'),
            'valor' => 33.00,
            'status' => PixCobranca::STATUS_PAGO,
            'pago_em' => '2026-10-15 11:00:00',
        ]);
        $extrato = $this->criarExtrato($empresa, $banco, 'credito', '33.00', '2026-10-15', 'CREDITO PIX');

        $r = app(ConciliacaoPixSugestaoService::class)->sugerir($extrato);
        $this->assertSame(PixSugestaoResultado::CLASS_SEM, $r->classificacao);
    }

    public function test_pagamento_outra_conta_bancaria_ignorado(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        $outro = CaixaConta::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 9402,
            'nome' => 'OUTRO BANCO',
            'tipo' => CaixaConta::TIPO_BANCO,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);
        $this->criarCaixa($empresa, $outro, 60.00, 0, '2026-10-16', 'PIX OUTRA CONTA');
        $extrato = $this->criarExtrato($empresa, $banco, 'credito', '60.00', '2026-10-16', 'CREDITO PIX');

        $r = app(ConciliacaoPixSugestaoService::class)->sugerir($extrato);
        $this->assertSame(PixSugestaoResultado::CLASS_SEM, $r->classificacao);
    }

    public function test_pix_pago_sem_caixa_nao_e_forte(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        $txid = $this->txidAilos('NOCX0001');
        PixCobranca::query()->create([
            'empresa_id' => $empresa->id,
            'origem' => 'titulo',
            'provedor' => 'ailos',
            'txid' => $txid,
            'provider_ref' => $txid,
            'valor' => 99.00,
            'status' => PixCobranca::STATUS_PAGO,
            'pago_em' => '2026-10-17 09:00:00',
        ]);
        $extrato = $this->criarExtrato($empresa, $banco, 'credito', '99.00', '2026-10-17', 'CREDITO PIX '.$txid);

        $r = app(ConciliacaoPixSugestaoService::class)->sugerir($extrato);

        $this->assertNotSame(PixSugestaoResultado::CLASS_FORTE, $r->classificacao);
        $this->assertSame(PixSugestaoResultado::CLASS_POSSIVEL, $r->classificacao);
        $this->assertSame('pix_pago_sem_caixa_bancario', $r->candidatos[0]->evidencias['motivo'] ?? null);
    }

    public function test_nenhum_candidato(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        $extrato = $this->criarExtrato($empresa, $banco, 'credito', '12.34', '2026-10-18', 'CREDITO PIX - Z');

        $r = app(ConciliacaoPixSugestaoService::class)->sugerir($extrato);
        $this->assertSame(PixSugestaoResultado::CLASS_SEM, $r->classificacao);
        $this->assertSame([], $r->candidatos);
    }

    public function test_varios_candidatos_ambiguo(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        PixCobranca::query()->create([
            'empresa_id' => $empresa->id,
            'origem' => 'titulo',
            'provedor' => 'ailos',
            'txid' => $this->txidAilos('MULTI001'),
            'provider_ref' => $this->txidAilos('MULTI001'),
            'valor' => 70.00,
            'status' => PixCobranca::STATUS_PAGO,
            'pago_em' => '2026-10-19 10:00:00',
        ]);
        $this->criarCaixa($empresa, $banco, 70.00, 0, '2026-10-19', 'A');
        $this->criarCaixa($empresa, $banco, 70.00, 0, '2026-10-19', 'B');
        $extrato = $this->criarExtrato($empresa, $banco, 'credito', '70.00', '2026-10-19', 'CREDITO PIX');

        $r = app(ConciliacaoPixSugestaoService::class)->sugerir($extrato);
        $this->assertSame(PixSugestaoResultado::CLASS_AMBIGUA, $r->classificacao);
    }

    public function test_nao_altera_extrato_nem_financeiro(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        $caixa = $this->criarCaixa($empresa, $banco, 15.00, 0, '2026-10-20', 'PIX');
        $attrs = $caixa->fresh()->getAttributes();
        $extrato = $this->criarExtrato($empresa, $banco, 'credito', '15.00', '2026-10-20', 'CREDITO PIX');

        app(ConciliacaoPixSugestaoService::class)->sugerir($extrato);

        $this->assertSame($attrs, $caixa->fresh()->getAttributes());
        $this->assertNull($extrato->fresh()->caixa_lancamento_id);
        $this->assertSame(ExtratoLancamento::SITUACAO_PENDENTE, $extrato->fresh()->situacao);
        $this->assertSame(0, DB::table('extrato_conciliacoes')->count());
    }

    public function test_performance_volume_limitado(): void
    {
        [$empresa, $banco] = $this->seedEmpresaEConta();
        for ($i = 0; $i < 200; $i++) {
            $this->criarCaixa($empresa, $banco, 5.00, 0, '2026-10-21', 'PIX VOL '.$i);
        }
        $extrato = $this->criarExtrato($empresa, $banco, 'credito', '5.00', '2026-10-21', 'CREDITO PIX VOL');

        $t0 = hrtime(true);
        $m0 = memory_get_usage(true);
        $r = app(ConciliacaoPixSugestaoService::class)->sugerir($extrato);
        $ms = (hrtime(true) - $t0) / 1e6;
        $memDelta = memory_get_usage(true) - $m0;

        $this->assertSame(PixSugestaoResultado::CLASS_AMBIGUA, $r->classificacao);
        $this->assertLessThanOrEqual(20, count($r->candidatos));
        $this->assertLessThan(500, $ms, 'Sugestão deve ser rápida mesmo com volume.');
        $this->assertLessThan(16 * 1024 * 1024, $memDelta, 'Delta de memória deve ser moderado.');
    }

    /**
     * @return array{0: Empresa, 1: CaixaConta}
     */
    private function seedEmpresaEConta(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => 'C4B',
            'nome' => 'EMPRESA C4B',
            'fantasia' => 'C4B',
            'ativo' => true,
        ]);
        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);
        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($admin);

        $banco = CaixaConta::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 9401,
            'nome' => 'BANCO C4B',
            'tipo' => CaixaConta::TIPO_BANCO,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);

        return [$empresa, $banco];
    }

    private function txidAilos(string $ref8): string
    {
        $ref8 = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $ref8) ?: 'REF', 0, 8));

        return AilosPixService::buildTxid($ref8, '10.00', 'teste');
    }

    private function criarExtrato(
        Empresa $empresa,
        CaixaConta $banco,
        string $tipo,
        string $valor,
        string $data,
        string $historico,
        ?string $fitid = null,
    ): ExtratoLancamento {
        $fitid = $fitid ?: strtoupper(bin2hex(random_bytes(16)));
        $imp = ExtratoImportacao::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'c4b.ofx',
            'arquivo_sha256' => hash('sha256', $fitid.microtime(true)),
            'formato' => 'ofx',
            'status' => 'concluida',
            'total_linhas' => 1,
            'imported_at' => now(),
        ]);

        return ExtratoLancamento::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'extrato_importacao_id' => $imp->id,
            'data_movimento' => $data,
            'tipo' => $tipo,
            'valor' => $valor,
            'historico' => $historico,
            'fitid' => $fitid,
            'fingerprint' => ExtratoLancamento::buildFingerprint(
                $fitid, $data, $tipo, $valor, $historico, null, 1, null, null, null,
                (int) $empresa->id, (int) $banco->id,
            ),
            'situacao' => ExtratoLancamento::SITUACAO_PENDENTE,
            'origem_tipo' => 'ofx',
        ]);
    }

    private function criarCaixa(
        Empresa $empresa,
        CaixaConta $conta,
        float $entrada,
        float $saida,
        string $emissao,
        string $historico,
    ): CaixaLancamento {
        return CaixaLancamento::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => $emissao,
            'documento' => 'PIX-C4B',
            'historico' => $historico,
            'caixa_conta_id' => $conta->id,
            'entrada' => $entrada,
            'saida' => $saida,
        ]);
    }

    private function createMinimalSchema(): void
    {
        foreach ([
            'extrato_conciliacoes', 'extrato_lancamentos', 'extrato_importacoes',
            'pix_cobrancas', 'conta_receber_pagamentos', 'contas_receber',
            'conta_pagar_pagamentos', 'contas_pagar',
            'pdv_venda_pagamentos', 'pdv_vendas',
            'caixa_lancamentos', 'caixa_contas', 'users', 'empresas',
            'cache', 'cache_locks',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 120);
            $table->string('fantasia', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('name');
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('caixa_contas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('nome', 120);
            $table->string('tipo', 20)->default('BANCO');
            $table->string('situacao', 20)->default('aberto');
            $table->boolean('ativo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->timestamps();
        });
        Schema::create('caixa_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->date('emissao')->nullable();
            $table->string('documento', 40)->nullable();
            $table->string('historico', 500)->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable()->index();
            $table->decimal('entrada', 14, 2)->default(0);
            $table->decimal('saida', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['empresa_id', 'caixa_conta_id', 'emissao']);
        });
        Schema::create('pix_cobrancas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->string('origem', 20);
            $table->string('order_uuid', 100)->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('conta_receber_id')->nullable();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->string('provedor', 30)->default('ailos');
            $table->string('txid', 100)->nullable()->index();
            $table->string('provider_ref', 100)->nullable()->index();
            $table->decimal('valor', 12, 2)->default(0);
            $table->string('status', 20)->default('pendente')->index();
            $table->timestamp('expira_em')->nullable();
            $table->timestamp('pago_em')->nullable()->index();
            $table->json('raw')->nullable();
            $table->timestamps();
        });
        Schema::create('extrato_importacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->string('arquivo_nome', 255);
            $table->char('arquivo_sha256', 64);
            $table->string('formato', 16)->default('ofx');
            $table->string('status', 20)->default('pendente');
            $table->unsignedInteger('total_linhas')->default(0);
            $table->decimal('total_creditos', 14, 2)->default(0);
            $table->decimal('total_debitos', 14, 2)->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
        Schema::create('extrato_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('caixa_conta_id')->index();
            $table->unsignedBigInteger('extrato_importacao_id');
            $table->date('data_movimento')->index();
            $table->date('data_posted')->nullable();
            $table->string('tipo', 16)->default('outro');
            $table->decimal('valor', 14, 2);
            $table->string('historico', 500)->nullable();
            $table->string('memo', 500)->nullable();
            $table->string('payee', 255)->nullable();
            $table->string('fitid', 120)->nullable();
            $table->char('fingerprint', 64);
            $table->string('situacao', 20)->default('pendente');
            $table->unsignedBigInteger('caixa_lancamento_id')->nullable();
            $table->string('origem_tipo', 40)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->timestamp('conciliado_em')->nullable();
            $table->unsignedBigInteger('conciliado_por_user_id')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'caixa_conta_id', 'fingerprint'], 'extrato_lanc_emp_conta_fp_uq');
        });
        Schema::create('extrato_conciliacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('extrato_lancamento_id');
            $table->string('status', 20)->default('ativa');
            $table->timestamps();
        });
        Schema::create('cache', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->mediumText('value');
            $t->integer('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('owner');
            $t->integer('expiration');
        });
    }
}
