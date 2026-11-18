<?php

namespace Tests\Unit\Pix;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\Empresa;
use App\Models\ExtratoImportacao;
use App\Models\ExtratoLancamento;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\PixCobranca;
use App\Support\Erp\Financeiro\Conciliacao\ConciliacaoCandidatosQuery;
use App\Support\Erp\Pdv\PdvCaixaFechamentoService;
use App\Support\Erp\Pdv\PdvCaixaMovimentoService;
use App\Support\Pix\PixPdvLivroCaixaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PixPdvLivroCaixaServiceTest extends TestCase
{
    private Empresa $empresa;

    private CaixaConta $banco;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchema();
        $this->empresa = Empresa::query()->create([
            'nome' => 'EMP PIX PDV',
            'ativo' => true,
        ]);
        $this->banco = CaixaConta::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 6,
            'nome' => 'CONTA AILOS',
            'tipo' => CaixaConta::TIPO_BANCO,
            'ativo' => true,
        ]);
        $this->empresa->forceFill(['param_pix_caixa_conta_id' => $this->banco->id])->save();
    }

    public function test_confirmacao_lanca_entrada_no_banco_sem_duplicar(): void
    {
        [$venda, $pag, $cobranca] = $this->vendaPixAilos(50.00);

        $svc = app(PixPdvLivroCaixaService::class);
        $a = $svc->vincularELancar((int) $cobranca->id, $venda, $pag);
        $b = $svc->vincularELancar((int) $cobranca->id, $venda, $pag);
        $c = $svc->lancarSeConfirmado($cobranca->fresh());

        $this->assertNotNull($a);
        $this->assertSame((int) $a->id, (int) $b->id);
        $this->assertSame((int) $a->id, (int) $c->id);
        $this->assertSame(1, CaixaLancamento::query()->where('documento', PixPdvLivroCaixaService::documentoDoPagamento((int) $pag->id))->count());
        $this->assertSame((int) $this->banco->id, (int) $a->caixa_conta_id);
        $this->assertEquals(50.00, (float) $a->entrada);
        $this->assertSame(0.0, (float) $a->saida);
        $this->assertSame((int) $venda->id, (int) $cobranca->fresh()->pdv_venda_id);
        $this->assertSame((int) $pag->id, (int) $cobranca->fresh()->pdv_venda_pagamento_id);
    }

    public function test_fechamento_exclui_pix_1_real_ja_na_conta_ailos(): void
    {
        $sessao = $this->sessao();
        [$vendaPix, $pagPix, $cobranca] = $this->vendaPixAilos(1.00, $sessao);
        $noBanco = app(PixPdvLivroCaixaService::class)->vincularELancar((int) $cobranca->id, $vendaPix, $pagPix);
        $this->assertNotNull($noBanco);
        $this->assertSame((int) $this->banco->id, (int) $noBanco->caixa_conta_id);
        $this->assertEquals(1.00, (float) $noBanco->entrada);

        PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'venda',
            'historico' => 'VENDA PDV #000001 - PIX',
            'forma_pagamento' => 'PIX',
            'entrada' => 1.00,
            'saida' => 0,
            'pdv_venda_id' => $vendaPix->id,
        ]);

        $sessao->forceFill(['fechado_em' => now()])->save();
        $lancs = (new PdvCaixaFechamentoService())->lancarNoLivroCaixa($sessao->fresh());

        $this->assertSame([], $lancs);
        $this->assertSame(0, CaixaLancamento::query()->where('documento', 'PDV-CX-'.$sessao->id)->count());
        $this->assertSame(1, CaixaLancamento::query()->where('caixa_conta_id', $this->banco->id)->count());
        $this->assertEquals(1.00, (float) CaixaLancamento::query()->where('caixa_conta_id', $this->banco->id)->sum('entrada'));
    }

    public function test_webhook_registrar_pagamento_repetido_nao_duplica(): void
    {
        [$venda, $pag, $cobranca] = $this->vendaPixAilos(1.00);
        app(PixPdvLivroCaixaService::class)->vincularELancar((int) $cobranca->id, $venda, $pag);

        $svc = app(\App\Support\Pix\PixCobrancaService::class);
        $svc->registrarPagamento($cobranca->fresh());
        $svc->registrarPagamento($cobranca->fresh());

        $this->assertSame(1, CaixaLancamento::query()
            ->where('documento', PixPdvLivroCaixaService::documentoDoPagamento((int) $pag->id))
            ->count());
        $this->assertTrue($cobranca->fresh()->isPago());
    }

    public function test_fechamento_nao_soma_pix_ja_no_banco_ao_caixa_geral(): void
    {
        $sessao = $this->sessao();
        [$vendaPix, $pagPix, $cobranca] = $this->vendaPixAilos(30.00, $sessao);
        app(PixPdvLivroCaixaService::class)->vincularELancar((int) $cobranca->id, $vendaPix, $pagPix);

        $vendaDin = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'numero' => '000002',
            'total' => 20,
            'situacao' => 'fechada',
        ]);
        PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'venda',
            'historico' => 'VENDA PDV #000001 - PIX',
            'forma_pagamento' => 'PIX',
            'entrada' => 30,
            'saida' => 0,
            'pdv_venda_id' => $vendaPix->id,
        ]);
        PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'venda',
            'historico' => 'VENDA PDV #000002',
            'forma_pagamento' => 'DINHEIRO',
            'entrada' => 20,
            'saida' => 0,
            'pdv_venda_id' => $vendaDin->id,
        ]);

        $sessao->forceFill(['fechado_em' => now()])->save();
        $lancs = (new PdvCaixaFechamentoService())->lancarNoLivroCaixa($sessao->fresh());

        $this->assertCount(1, $lancs);
        $geral = $lancs[0];
        $this->assertSame((int) CaixaConta::ensureCaixaGeral()->id, (int) $geral->caixa_conta_id);
        $this->assertEquals(20.00, (float) $geral->entrada);
        $this->assertEquals(30.00, (float) CaixaLancamento::query()->where('caixa_conta_id', $this->banco->id)->sum('entrada'));
    }

    public function test_confirmacao_apos_fechamento_nao_duplica_no_geral(): void
    {
        $sessao = $this->sessao();
        $vendaDin = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'numero' => '000010',
            'total' => 15,
            'situacao' => 'fechada',
        ]);
        PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'venda',
            'historico' => 'VENDA PDV #000010',
            'forma_pagamento' => 'DINHEIRO',
            'entrada' => 15,
            'saida' => 0,
            'pdv_venda_id' => $vendaDin->id,
        ]);
        $sessao->forceFill(['fechado_em' => now()])->save();
        (new PdvCaixaFechamentoService())->lancarNoLivroCaixa($sessao->fresh());

        [$vendaPix, $pag, $cobranca] = $this->vendaPixAilos(40.00, $sessao);
        app(PixPdvLivroCaixaService::class)->vincularELancar((int) $cobranca->id, $vendaPix, $pag);
        app(PixPdvLivroCaixaService::class)->lancarSeConfirmado($cobranca->fresh());

        $this->assertEquals(15.00, (float) CaixaLancamento::query()
            ->where('documento', 'PDV-CX-'.$sessao->id)
            ->value('entrada'));
        $this->assertSame(1, CaixaLancamento::query()->where('caixa_conta_id', $this->banco->id)->count());
        $this->assertSame(1, CaixaLancamento::query()->where('documento', 'PDV-CX-'.$sessao->id)->count());
    }

    public function test_pix_manual_continua_no_fechamento(): void
    {
        $sessao = $this->sessao();
        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'numero' => '000020',
            'total' => 12,
            'situacao' => 'fechada',
        ]);
        (new PdvCaixaMovimentoService())->registrarEntradasVenda($sessao->id, $venda, [
            ['forma' => 'PIX', 'valor' => 12, 'tipo' => 'pix', 'tipo_movimento' => 'caixa'],
        ]);
        $sessao->forceFill(['fechado_em' => now()])->save();
        $lancs = (new PdvCaixaFechamentoService())->lancarNoLivroCaixa($sessao->fresh());

        $this->assertCount(1, $lancs);
        $this->assertEquals(12.00, (float) $lancs[0]->entrada);
        $this->assertSame((int) CaixaConta::ensureCaixaGeral()->id, (int) $lancs[0]->caixa_conta_id);
        $this->assertSame(0, CaixaLancamento::query()->where('caixa_conta_id', $this->banco->id)->count());
    }

    public function test_conciliacao_encontra_lancamento_por_valor_data_e_conta(): void
    {
        [$venda, $pag, $cobranca] = $this->vendaPixAilos(77.50);
        $lanc = app(PixPdvLivroCaixaService::class)->vincularELancar((int) $cobranca->id, $venda, $pag);
        $this->assertNotNull($lanc);

        $imp = ExtratoImportacao::query()->create([
            'empresa_id' => $this->empresa->id,
            'caixa_conta_id' => $this->banco->id,
            'arquivo_nome' => 'x.ofx',
            'arquivo_sha256' => str_repeat('a', 64),
            'formato' => 'ofx',
            'status' => 'ok',
        ]);
        $extrato = ExtratoLancamento::query()->create([
            'empresa_id' => $this->empresa->id,
            'caixa_conta_id' => $this->banco->id,
            'extrato_importacao_id' => $imp->id,
            'data_movimento' => $lanc->emissao->toDateString(),
            'tipo' => ExtratoLancamento::TIPO_CREDITO,
            'valor' => 77.50,
            'historico' => 'CREDITO PIX',
            'fingerprint' => str_repeat('b', 64),
            'situacao' => ExtratoLancamento::SITUACAO_PENDENTE,
        ]);

        $cands = (new ConciliacaoCandidatosQuery())->query($extrato)->get();
        $this->assertTrue($cands->contains(fn (CaixaLancamento $c): bool => (int) $c->id === (int) $lanc->id));
    }

    /**
     * @return array{0: PdvVenda, 1: PdvVendaPagamento, 2: PixCobranca}
     */
    private function vendaPixAilos(float $valor, ?PdvCaixaSessao $sessao = null): array
    {
        $sessao ??= $this->sessao();
        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'numero' => '000001',
            'total' => $valor,
            'situacao' => 'fechada',
        ]);
        $pag = PdvVendaPagamento::query()->create([
            'pdv_venda_id' => $venda->id,
            'forma' => 'PIX',
            'valor' => $valor,
        ]);
        $cobranca = PixCobranca::query()->create([
            'empresa_id' => $this->empresa->id,
            'origem' => PixCobranca::ORIGEM_PEDIDO,
            'order_uuid' => 'pdv-1-abc',
            'provedor' => 'ailos',
            'txid' => 'TXPDV'.str_pad((string) random_int(1, 99), 2, '0', STR_PAD_LEFT),
            'valor' => $valor,
            'status' => PixCobranca::STATUS_PAGO,
            'pago_em' => now(),
        ]);

        return [$venda, $pag, $cobranca];
    }

    private function sessao(): PdvCaixaSessao
    {
        return PdvCaixaSessao::query()->create([
            'empresa_id' => $this->empresa->id,
            'aberto_em' => now()->subHour(),
        ]);
    }

    private function criarSchema(): void
    {
        foreach ([
            'extrato_lancamentos', 'extrato_importacoes',
            'pdv_caixa_movimentos', 'pdv_venda_pagamentos', 'pdv_vendas', 'pdv_caixa_sessoes',
            'pix_cobrancas', 'caixa_lancamentos', 'caixa_contas', 'empresas',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('param_pix_caixa_conta_id')->nullable();
            $table->timestamps();
        });
        Schema::create('caixa_contas', function (Blueprint $table): void {
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
        Schema::create('caixa_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->date('emissao')->nullable();
            $table->string('documento', 40)->nullable();
            $table->string('historico', 180)->nullable();
            $table->string('plano_contas', 120)->nullable();
            $table->unsignedBigInteger('plano_conta_id')->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->decimal('entrada', 14, 2)->default(0);
            $table->decimal('saida', 14, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('pix_cobrancas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('origem', 20);
            $table->string('order_uuid', 100)->nullable();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->unsignedBigInteger('pdv_venda_id')->nullable();
            $table->unsignedBigInteger('pdv_venda_pagamento_id')->nullable();
            $table->string('provedor', 30)->default('ailos');
            $table->string('txid', 100)->nullable();
            $table->string('provider_ref', 100)->nullable();
            $table->decimal('valor', 12, 2)->default(0);
            $table->string('status', 20)->default('pendente');
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();
        });
        Schema::create('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamp('aberto_em')->nullable();
            $table->timestamp('fechado_em')->nullable();
            $table->timestamps();
        });
        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id')->nullable();
            $table->string('numero', 20)->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->string('situacao', 20)->nullable();
            $table->timestamps();
        });
        Schema::create('pdv_venda_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_venda_id');
            $table->string('forma', 80)->nullable();
            $table->decimal('valor', 14, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('pdv_caixa_movimentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id');
            $table->string('tipo', 30)->nullable();
            $table->string('historico', 180)->nullable();
            $table->string('forma_pagamento', 80)->nullable();
            $table->decimal('entrada', 14, 2)->default(0);
            $table->decimal('saida', 14, 2)->default(0);
            $table->unsignedBigInteger('pdv_venda_id')->nullable();
            $table->timestamps();
        });
        Schema::create('extrato_importacoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->string('arquivo_nome', 255);
            $table->char('arquivo_sha256', 64);
            $table->string('formato', 16)->default('ofx');
            $table->string('status', 20)->default('ok');
            $table->timestamps();
        });
        Schema::create('extrato_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->unsignedBigInteger('extrato_importacao_id');
            $table->date('data_movimento');
            $table->string('tipo', 16);
            $table->decimal('valor', 14, 2);
            $table->string('historico', 500)->nullable();
            $table->char('fingerprint', 64);
            $table->string('situacao', 20)->default('pendente');
            $table->unsignedBigInteger('caixa_lancamento_id')->nullable();
            $table->timestamps();
        });
    }
}
