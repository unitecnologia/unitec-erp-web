<?php

namespace Tests\Feature;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\Empresa;
use App\Models\ExtratoConciliacao;
use App\Models\ExtratoLancamento;
use App\Models\PdvVenda;
use App\Models\TefIntencao;
use App\Models\TefLiquidacaoDestino;
use App\Models\TefLiquidacaoLote;
use App\Models\TefLiquidacaoLoteExtrato;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Erp\Financeiro\Conciliacao\ConciliacaoManualException;
use App\Support\Erp\Financeiro\Conciliacao\ConciliacaoManualService;
use App\Support\Erp\Tef\TefLiquidacaoLoteOfxVinculoException;
use App\Support\Erp\Tef\TefLiquidacaoLoteOfxVinculoService;
use App\Support\Erp\Tef\TefLiquidacaoLoteService;
use App\Support\Erp\Tef\TefMoney;
use App\Support\Erp\Tef\TefProviderId;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TefLiquidacaoLoteOfxVinculoC5c2Test extends TestCase
{
    private TefLiquidacaoLoteOfxVinculoService $vinculo;

    private TefLiquidacaoLoteService $lotes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        $this->vinculo = app(TefLiquidacaoLoteOfxVinculoService::class);
        $this->lotes = app(TefLiquidacaoLoteService::class);
    }

    public function test_vinculo_1_para_1_integral(): void
    {
        [$empresa, $terminal, $banco, $lote] = $this->seedLoteConferido(100.00, 0);
        $user = $this->user();
        $ext = $this->credito($empresa, $banco, '100.00', '2026-11-10');

        $r = $this->vinculo->vincular($lote, $ext, '100.00', null, (int) $user->id);

        $this->assertSame(TefLiquidacaoLote::OFX_INTEGRAL, $r['lote_ofx_situacao']);
        $this->assertSame(TefLiquidacaoLote::OFX_INTEGRAL, $this->vinculo->situacaoExtratoTef($ext->fresh()));
        $this->assertSame(0, DB::table('caixa_lancamentos')->count());
        $this->assertSame(ExtratoLancamento::SITUACAO_PENDENTE, $ext->fresh()->situacao);
    }

    public function test_n_lotes_um_credito(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $user = $this->user();
        $l1 = $this->criarLoteConferido($empresa, $terminal, 40.00);
        $l2 = $this->criarLoteConferido($empresa, $terminal, 60.00);
        $ext = $this->credito($empresa, $banco, '100.00', '2026-11-11');

        $this->vinculo->vincular($l1, $ext, '40.00', null, (int) $user->id);
        $v1 = TefLiquidacaoLoteExtrato::query()->orderBy('id')->first();
        $this->assertSame('0.00', (string) $v1->diferenca);

        $this->vinculo->vincular($l2, $ext, '60.00', null, (int) $user->id);

        $this->assertSame(TefLiquidacaoLote::OFX_INTEGRAL, $l1->fresh()->ofx_situacao);
        $this->assertSame(TefLiquidacaoLote::OFX_INTEGRAL, $l2->fresh()->ofx_situacao);
        $this->assertSame(TefLiquidacaoLote::OFX_INTEGRAL, $this->vinculo->situacaoExtratoTef($ext->fresh()));
    }

    public function test_um_lote_n_creditos_parciais(): void
    {
        [$empresa, $terminal, $banco, $lote] = $this->seedLoteConferido(100.00, 0);
        $user = $this->user();
        $e1 = $this->credito($empresa, $banco, '40.00', '2026-11-12');
        $e2 = $this->credito($empresa, $banco, '60.00', '2026-11-13');

        $this->vinculo->vincular($lote, $e1, '40.00', null, (int) $user->id);
        $this->assertSame(TefLiquidacaoLote::OFX_PARCIAL, $lote->fresh()->ofx_situacao);

        $this->vinculo->vincular($lote->fresh(), $e2, '60.00', null, (int) $user->id);
        $this->assertSame(TefLiquidacaoLote::OFX_INTEGRAL, $lote->fresh()->ofx_situacao);
    }

    public function test_excesso_de_valor_bloqueado(): void
    {
        [$empresa, $terminal, $banco, $lote] = $this->seedLoteConferido(50.00, 0);
        $user = $this->user();
        $ext = $this->credito($empresa, $banco, '50.00', '2026-11-14');

        $this->expectException(TefLiquidacaoLoteOfxVinculoException::class);
        $this->vinculo->vincular($lote, $ext, '50.01', null, (int) $user->id);
    }

    public function test_divergencia_sem_justificativa_bloqueia(): void
    {
        // Bruto 100 − taxa 1,50 = líquido 98,50; OFX 100 → sobra 1,50 ao fechar o lote.
        [$empresa, $terminal, $banco, $lote] = $this->seedLoteConferido(100.00, 1.50);
        $user = $this->user();
        $ext = $this->credito($empresa, $banco, '100.00', '2026-11-15');

        $this->expectException(TefLiquidacaoLoteOfxVinculoException::class);
        $this->expectExceptionMessage('justificativa');
        $this->vinculo->vincular($lote, $ext, '98.50', null, (int) $user->id, encerrarCreditoComSobra: true);
    }

    public function test_divergencia_com_justificativa_parcial_explicita(): void
    {
        [$empresa, $terminal, $banco, $lote] = $this->seedLoteConferido(100.00, 1.50);
        $user = $this->user();
        $ext = $this->credito($empresa, $banco, '100.00', '2026-11-15');

        $r = $this->vinculo->vincular($lote, $ext, '98.50', 'taxa adquirente', (int) $user->id, encerrarCreditoComSobra: true);

        $this->assertSame(TefLiquidacaoLote::OFX_INTEGRAL, $r['lote_ofx_situacao']);
        // Sobra encerrada zera restante — crédito não fica “disponível” nem vira taxa automática.
        $this->assertSame('0.00', $this->vinculo->restanteExtrato($ext->fresh()));
        $this->assertSame(TefLiquidacaoLote::OFX_INTEGRAL, $this->vinculo->situacaoExtratoTef($ext->fresh()));
        $v = TefLiquidacaoLoteExtrato::query()->first();
        $this->assertSame('1.50', (string) $v->diferenca);
        $this->assertSame('taxa adquirente', $v->diferenca_motivo);

        $this->vinculo->reverter($v, 'desfaz sobra', (int) $user->id);
        $this->assertSame('100.00', $this->vinculo->restanteExtrato($ext->fresh()));
        $this->assertSame(TefLiquidacaoLote::OFX_PENDENTE, $lote->fresh()->ofx_situacao);
    }

    public function test_exclusao_mutua_c3_bloqueia_tef(): void
    {
        [$empresa, $terminal, $banco, $lote] = $this->seedLoteConferido(10.00, 0);
        $user = $this->user();
        $ext = $this->credito($empresa, $banco, '10.00', '2026-11-16');
        $caixa = $this->caixaEntrada($empresa, $banco, '10.00', '2026-11-16');
        app(ConciliacaoManualService::class)->conciliar($ext, $caixa, (int) $user->id, (int) $empresa->id);

        $this->expectException(TefLiquidacaoLoteOfxVinculoException::class);
        $this->vinculo->vincular($lote, $ext->fresh(), '10.00', null, (int) $user->id);
    }

    public function test_exclusao_mutua_tef_bloqueia_c3(): void
    {
        [$empresa, $terminal, $banco, $lote] = $this->seedLoteConferido(10.00, 0);
        $user = $this->user();
        $ext = $this->credito($empresa, $banco, '10.00', '2026-11-17');
        $this->vinculo->vincular($lote, $ext, '10.00', null, (int) $user->id);
        $caixa = $this->caixaEntrada($empresa, $banco, '10.00', '2026-11-17');

        $this->expectException(ConciliacaoManualException::class);
        $this->expectExceptionMessage('lote TEF');
        app(ConciliacaoManualService::class)->conciliar($ext->fresh(), $caixa, (int) $user->id, (int) $empresa->id);
    }

    public function test_outra_empresa_bloqueada(): void
    {
        [$empresa, $terminal, $banco, $lote] = $this->seedLoteConferido(10.00, 0);
        $outra = Empresa::query()->create(['razao_social' => 'X', 'nome_fantasia' => 'X']);
        $banco2 = $this->criarBanco($outra, 1, 'B2');
        $ext = $this->credito($outra, $banco2, '10.00', '2026-11-18');
        $user = $this->user();

        $this->expectException(TefLiquidacaoLoteOfxVinculoException::class);
        $this->vinculo->vincular($lote, $ext, '10.00', null, (int) $user->id);
    }

    public function test_lote_cancelado_bloqueado(): void
    {
        [$empresa, $terminal, $banco, $lote] = $this->seedLoteConferido(10.00, 0);
        $this->lotes->cancelarLote($lote);
        $ext = $this->credito($empresa, $banco, '10.00', '2026-11-19');
        $user = $this->user();

        $this->expectException(TefLiquidacaoLoteOfxVinculoException::class);
        $this->vinculo->vincular($lote->fresh(), $ext, '10.00', null, (int) $user->id);
    }

    public function test_reversao_preserva_auditoria_e_libera_valor(): void
    {
        [$empresa, $terminal, $banco, $lote] = $this->seedLoteConferido(25.00, 0);
        $user = $this->user();
        $ext = $this->credito($empresa, $banco, '25.00', '2026-11-20');
        $r = $this->vinculo->vincular($lote, $ext, '25.00', null, (int) $user->id);
        $v = TefLiquidacaoLoteExtrato::query()->find($r['vinculo_id']);

        $this->vinculo->reverter($v, 'ajuste', (int) $user->id);

        $v = $v->fresh();
        $this->assertSame(TefLiquidacaoLoteExtrato::STATUS_REVERTIDO, $v->status);
        $this->assertNotNull($v->revertido_em);
        $this->assertSame(TefLiquidacaoLote::OFX_PENDENTE, $lote->fresh()->ofx_situacao);
        $this->assertSame('0.00', $this->vinculo->somaVinculadaExtrato((int) $ext->id));
        $this->assertSame(TefIntencao::STATUS_PAGA, TefIntencao::query()->first()->status);
    }

    public function test_parcelamento_lote_com_parcela(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $user = $this->user();
        $venda = $this->venda();
        $i = $this->intencao($empresa, $terminal, $venda, 90.00, 3);
        $lote = $this->lotes->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id, 'parcela_numero' => 1],
        ], ['terminal_id' => $terminal->id]);
        $this->lotes->alterarSituacao($lote, TefLiquidacaoLote::SIT_CONFERIDO);
        $ext = $this->credito($empresa, $banco, '30.00', '2026-12-01');

        $r = $this->vinculo->vincular($lote->fresh(), $ext, '30.00', null, (int) $user->id);
        $this->assertSame(TefLiquidacaoLote::OFX_INTEGRAL, $r['lote_ofx_situacao']);
        $this->assertSame('30.00', (string) $lote->fresh()->valor_liquido);
    }

    public function test_money_sem_float_em_cadeia(): void
    {
        $this->assertSame('0.30', TefMoney::add('0.10', '0.20'));
        $this->assertSame(0, TefMoney::cmp('10.00', '10.00'));
        $this->assertSame(-1, TefMoney::cmp('9.99', '10.00'));
    }

    public function test_performance_candidatos_limitados(): void
    {
        [$empresa, $terminal, $banco, $lote] = $this->seedLoteConferido(10.00, 0);
        for ($i = 0; $i < 80; $i++) {
            $this->credito($empresa, $banco, '1.00', '2026-11-01');
        }
        $t0 = hrtime(true);
        $n = $this->vinculo->queryCreditosCandidatos($lote)->get(['id'])->count();
        $ms = (hrtime(true) - $t0) / 1e6;
        $this->assertLessThanOrEqual(TefLiquidacaoLoteOfxVinculoService::MAX_CANDIDATOS, $n);
        $this->assertLessThan(800, $ms, "candidatos {$ms}ms");
    }

    /**
     * @return array{0: Empresa, 1: Terminal, 2: CaixaConta, 3: TefLiquidacaoLote}
     */
    private function seedLoteConferido(float $bruto, float $taxa): array
    {
        [$empresa, $terminal, $banco] = $this->seedBase();
        $lote = $this->criarLoteConferido($empresa, $terminal, $bruto, $taxa);

        return [$empresa, $terminal, $banco, $lote];
    }

    private function criarLoteConferido(Empresa $empresa, Terminal $terminal, float $bruto, float $taxa = 0): TefLiquidacaoLote
    {
        $venda = $this->venda();
        $i = $this->intencao($empresa, $terminal, $venda, $bruto);
        $lote = $this->lotes->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], [
            'terminal_id' => $terminal->id,
            'valor_taxas' => $taxa,
        ]);
        $this->lotes->alterarSituacao($lote, TefLiquidacaoLote::SIT_CONFERIDO);

        return $lote->fresh();
    }

    /**
     * @return array{0: Empresa, 1: Terminal, 2: CaixaConta}
     */
    private function seedBase(): array
    {
        $empresa = Empresa::query()->create(['razao_social' => 'EMP', 'nome_fantasia' => 'EMP']);
        $terminal = Terminal::query()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'PDV',
            'ativo' => true,
            'pdv' => true,
        ]);
        $banco = $this->criarBanco($empresa, 1, 'BANCO');
        TefLiquidacaoDestino::query()->create([
            'empresa_id' => $empresa->id,
            'terminal_id' => $terminal->id,
            'provedor' => TefProviderId::SUPERTEF,
            'acquirer_cnpj' => '',
            'caixa_conta_id' => $banco->id,
            'ativo' => true,
        ]);

        return [$empresa, $terminal, $banco];
    }

    private function criarBanco(Empresa $empresa, int $codigo, string $nome): CaixaConta
    {
        return CaixaConta::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => $codigo,
            'nome' => $nome,
            'tipo' => CaixaConta::TIPO_BANCO,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);
    }

    private function venda(): PdvVenda
    {
        return PdvVenda::query()->create([
            'situacao' => 'F',
            'total' => 10,
            'subtotal' => 10,
            'desconto' => 0,
            'acrescimo' => 0,
        ]);
    }

    private function intencao(Empresa $e, Terminal $t, PdvVenda $v, float $amount, int $parc = 1): TefIntencao
    {
        return TefIntencao::query()->create([
            'empresa_id' => $e->id,
            'terminal_id' => $t->id,
            'pdv_venda_id' => $v->id,
            'order_id' => 'O-'.uniqid(),
            'pos_id' => 1,
            'amount' => $amount,
            'transaction_type' => '1',
            'installment_count' => $parc,
            'installment_type' => 1,
            'status' => TefIntencao::STATUS_PAGA,
            'pago_em' => now(),
            'nsu' => 'N'.random_int(1, 9999),
            'requer_reconciliacao' => false,
        ]);
    }

    private function credito(Empresa $e, CaixaConta $b, string $valor, string $data): ExtratoLancamento
    {
        return ExtratoLancamento::query()->create([
            'empresa_id' => $e->id,
            'caixa_conta_id' => $b->id,
            'data_movimento' => $data,
            'tipo' => ExtratoLancamento::TIPO_CREDITO,
            'valor' => $valor,
            'historico' => 'CREDITO TEF TESTE',
            'situacao' => ExtratoLancamento::SITUACAO_PENDENTE,
            'fingerprint' => hash('sha256', uniqid('', true)),
            'origem_tipo' => 'ofx',
        ]);
    }

    private function caixaEntrada(Empresa $e, CaixaConta $b, string $valor, string $data): CaixaLancamento
    {
        return CaixaLancamento::query()->create([
            'empresa_id' => $e->id,
            'caixa_conta_id' => $b->id,
            'emissao' => $data,
            'documento' => 'DOC-'.uniqid(),
            'historico' => 'TESTE',
            'entrada' => $valor,
            'saida' => 0,
        ]);
    }

    private function user(): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Op',
            'email' => 'op'.uniqid('', true).'@t.test',
            'password' => bcrypt('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function createMinimalSchema(): void
    {
        foreach ([
            'tef_liquidacao_lote_extratos', 'tef_liquidacao_lote_itens', 'tef_liquidacao_lotes',
            'tef_liquidacao_destinos', 'tef_intencoes', 'pdv_vendas', 'extrato_conciliacoes',
            'extrato_lancamentos', 'caixa_lancamentos', 'caixa_contas', 'terminais', 'empresas', 'users',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('razao_social')->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->timestamps();
        });

        Schema::create('terminais', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('nome')->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('pdv')->default(false);
            $table->timestamps();
        });

        Schema::create('caixa_contas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('nome');
            $table->string('tipo', 20)->default('BANCO');
            $table->string('situacao', 20)->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->timestamps();
        });

        Schema::create('caixa_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->date('emissao')->nullable();
            $table->string('documento', 40)->nullable();
            $table->string('historico', 500)->nullable();
            $table->decimal('entrada', 15, 2)->default(0);
            $table->decimal('saida', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->string('situacao', 2)->default('F');
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('acrescimo', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('tef_intencoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('terminal_id')->index();
            $table->unsignedBigInteger('pdv_venda_id')->nullable();
            $table->string('order_id', 64)->unique();
            $table->unsignedInteger('pos_id')->default(1);
            $table->decimal('amount', 12, 2);
            $table->string('transaction_type', 8)->default('1');
            $table->unsignedTinyInteger('installment_count')->default(1);
            $table->unsignedTinyInteger('installment_type')->default(1);
            $table->string('status', 24);
            $table->timestamp('pago_em')->nullable();
            $table->string('nsu', 40)->nullable();
            $table->string('acquirer_cnpj', 14)->nullable();
            $table->boolean('requer_reconciliacao')->default(false);
            $table->timestamps();
        });

        Schema::create('tef_liquidacao_destinos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('terminal_id');
            $table->string('provedor', 20)->default('supertef');
            $table->string('acquirer_cnpj', 14)->default('');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'terminal_id', 'provedor', 'acquirer_cnpj'], 'tef_liq_dest_emp_term_prov_acq_uq');
        });

        Schema::create('tef_liquidacao_lotes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('terminal_id');
            $table->string('acquirer_cnpj', 14)->default('');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->date('periodo_de')->nullable();
            $table->date('periodo_ate')->nullable();
            $table->date('data_prevista_liquidacao')->nullable();
            $table->decimal('valor_bruto', 15, 2)->default(0);
            $table->decimal('valor_taxas', 15, 2)->default(0);
            $table->string('taxa_origem', 24)->default('nao_informada');
            $table->decimal('valor_liquido', 15, 2)->default(0);
            $table->string('situacao', 24)->default('preparacao');
            $table->string('ofx_situacao', 24)->default('pendente');
            $table->string('identificador_externo', 80)->nullable();
            $table->string('origem', 40)->default('manual');
            $table->string('observacao', 500)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('tef_liquidacao_lote_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lote_id');
            $table->unsignedBigInteger('tef_intencao_id');
            $table->unsignedBigInteger('pdv_venda_id')->nullable();
            $table->decimal('valor_bruto', 15, 2);
            $table->decimal('valor_taxa', 15, 2)->default(0);
            $table->decimal('valor_liquido', 15, 2);
            $table->string('nsu', 40)->nullable();
            $table->string('autorizacao', 40)->nullable();
            $table->string('bandeira', 40)->nullable();
            $table->string('transaction_type', 8)->nullable();
            $table->unsignedTinyInteger('installment_count')->default(1);
            $table->unsignedTinyInteger('parcela_numero')->default(1);
            $table->string('acquirer_cnpj', 14)->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->date('data_prevista_parcela')->nullable();
            $table->timestamps();
        });

        Schema::create('extrato_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->unsignedBigInteger('extrato_importacao_id')->nullable();
            $table->date('data_movimento');
            $table->date('data_posted')->nullable();
            $table->string('tipo', 16);
            $table->decimal('valor', 15, 2);
            $table->string('historico', 500)->nullable();
            $table->string('memo', 500)->nullable();
            $table->string('fitid', 120)->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->string('situacao', 20)->default('pendente');
            $table->boolean('ambiguo')->default(false);
            $table->timestamp('conciliado_em')->nullable();
            $table->unsignedBigInteger('conciliado_por_user_id')->nullable();
            $table->string('origem_tipo', 40)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->unsignedBigInteger('caixa_lancamento_id')->nullable();
            $table->timestamps();
        });

        Schema::create('extrato_conciliacoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->unsignedBigInteger('extrato_lancamento_id');
            $table->unsignedBigInteger('caixa_lancamento_id');
            $table->string('status', 20)->default('ativa');
            $table->string('tipo_extrato', 16);
            $table->decimal('valor_extrato', 14, 2);
            $table->decimal('valor_caixa', 14, 2);
            $table->date('data_movimento_extrato');
            $table->date('data_emissao_caixa')->nullable();
            $table->string('historico_extrato', 500)->nullable();
            $table->string('fitid', 120)->nullable();
            $table->string('documento_caixa', 40)->nullable();
            $table->string('historico_caixa', 500)->nullable();
            $table->unsignedBigInteger('conciliado_por_user_id')->nullable();
            $table->timestamp('conciliado_em')->nullable();
            $table->unsignedBigInteger('revertido_por_user_id')->nullable();
            $table->timestamp('revertido_em')->nullable();
            $table->string('motivo_reversao', 255)->nullable();
            $table->string('lock_extrato', 32)->nullable()->unique();
            $table->string('lock_caixa', 32)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('tef_liquidacao_lote_extratos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->unsignedBigInteger('lote_id');
            $table->unsignedBigInteger('extrato_lancamento_id');
            $table->decimal('valor_vinculado', 15, 2);
            $table->decimal('valor_lote_liquido_ref', 15, 2);
            $table->decimal('valor_extrato_ref', 15, 2);
            $table->decimal('diferenca', 15, 2)->default(0);
            $table->string('diferenca_motivo', 255)->nullable();
            $table->string('status', 20)->default('ativo');
            $table->unsignedBigInteger('vinculado_por_user_id')->nullable();
            $table->timestamp('vinculado_em')->nullable();
            $table->unsignedBigInteger('revertido_por_user_id')->nullable();
            $table->timestamp('revertido_em')->nullable();
            $table->string('motivo_reversao', 255)->nullable();
            $table->string('lock_token', 64)->nullable()->unique();
            $table->timestamps();
        });
    }
}
