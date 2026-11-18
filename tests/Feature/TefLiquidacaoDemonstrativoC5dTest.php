<?php

namespace Tests\Feature;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Models\ExtratoLancamento;
use App\Models\TefIntencao;
use App\Models\TefLiquidacaoDestino;
use App\Models\TefLiquidacaoLote;
use App\Models\TefLiquidacaoLoteExtrato;
use App\Models\TefLiquidacaoLoteItem;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Erp\Tef\TefLiquidacaoDemonstrativoService;
use App\Support\Erp\Tef\TefLiquidacaoLoteOfxVinculoService;
use App\Support\Erp\Tef\TefLiquidacaoLoteService;
use App\Support\Erp\Tef\TefProviderId;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TefLiquidacaoDemonstrativoC5dTest extends TestCase
{
    private TefLiquidacaoDemonstrativoService $demo;

    private TefLiquidacaoLoteService $lotes;

    private TefLiquidacaoLoteOfxVinculoService $vinculo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        $this->demo = app(TefLiquidacaoDemonstrativoService::class);
        $this->lotes = app(TefLiquidacaoLoteService::class);
        $this->vinculo = app(TefLiquidacaoLoteOfxVinculoService::class);
    }

    public function test_demonstrativo_periodo_empresa_totais(): void
    {
        [$e1, $t1] = $this->seedEmpresa();
        [$e2, $t2] = $this->seedEmpresa('OUTRA');

        $this->criarLoteConferido($e1, $t1, 100.00, 0, '2026-11-01', '2026-11-10');
        $this->criarLoteConferido($e1, $t1, 50.00, 1.50, '2026-11-05', '2026-11-15');
        $this->criarLoteConferido($e2, $t2, 999.00, 0, '2026-11-01', '2026-11-30');

        $caixaAntes = DB::table('caixa_lancamentos')->count();
        $crAntes = Schema::hasTable('contas_receber') ? DB::table('contas_receber')->count() : 0;

        $r = $this->demo->consultar((int) $e1->id, [
            'periodo_de' => '2026-11-01',
            'periodo_ate' => '2026-11-30',
            'situacao' => 'ativos',
        ]);

        $this->assertSame(2, $r['totais']['qtd_lotes']);
        $this->assertSame('150.00', $r['totais']['bruto']);
        $this->assertSame('1.50', $r['totais']['taxas_informadas']);
        $this->assertSame(1, $r['totais']['lotes_taxa_nao_informada']);
        $this->assertSame('148.50', $r['totais']['liquido_previsto']);
        $this->assertSame('0.00', $r['totais']['contabilizado']);
        $this->assertSame(TefProviderId::SUPERTEF, $r['meta']['provedor']);
        $this->assertCount(2, $r['rows']);

        $this->assertSame($caixaAntes, DB::table('caixa_lancamentos')->count());
        if (Schema::hasTable('contas_receber')) {
            $this->assertSame($crAntes, DB::table('contas_receber')->count());
        }
    }

    public function test_taxa_nao_informada_nao_vira_zero_efetivo(): void
    {
        [$e, $t] = $this->seedEmpresa();
        $lote = $this->criarLoteConferido($e, $t, 80.00, 0);

        $r = $this->demo->consultar((int) $e->id, ['situacao' => 'ativos']);
        $row = collect($r['rows'])->firstWhere('id', $lote->id);

        $this->assertSame(TefLiquidacaoLote::TAXA_NAO_INFORMADA, $row['taxa_origem']);
        $this->assertNull($row['taxas']);
        $this->assertSame('não informada', $row['taxas_label']);
        $this->assertSame('0.00', $r['totais']['taxas_informadas']);
        $this->assertSame(1, $r['totais']['lotes_taxa_nao_informada']);
    }

    public function test_vinculos_parcial_integral_e_diferenca(): void
    {
        [$e, $t, $banco] = $this->seedEmpresaComBanco();
        $user = $this->user();
        $lote = $this->criarLoteConferido($e, $t, 100.00, 1.50);
        $ext = $this->credito($e, $banco, '100.00', '2026-11-20');

        $this->vinculo->vincular($lote, $ext, '50.00', null, (int) $user->id);
        $parcial = $this->demo->consultar((int) $e->id, ['situacao' => 'conferidos']);
        $this->assertSame('50.00', $parcial['totais']['vinculado_ofx']);
        $this->assertSame('48.50', $parcial['totais']['pendente_ofx']);

        $this->vinculo->reverter(
            TefLiquidacaoLoteExtrato::query()->first(),
            'reset',
            (int) $user->id
        );

        $this->vinculo->vincular(
            $lote->fresh(),
            $ext->fresh(),
            '98.50',
            'taxa adquirente',
            (int) $user->id,
            encerrarCreditoComSobra: true
        );

        $r = $this->demo->consultar((int) $e->id, ['situacao' => 'conferidos']);
        $this->assertSame('98.50', $r['totais']['vinculado_ofx']);
        $this->assertSame('1.50', $r['totais']['diferencas_justificadas']);
        $this->assertSame('0.00', $r['totais']['pendente_ofx']);
        $row = $r['rows'][0];
        $this->assertSame('98.50', $row['vinculado_ofx']);
        $this->assertSame('1.50', $row['diferencas_justificadas']);
    }

    public function test_lotes_cancelados_fora_do_padrao(): void
    {
        [$e, $t] = $this->seedEmpresa();
        $ativo = $this->criarLoteConferido($e, $t, 10.00, 0);
        $canc = $this->criarLoteConferido($e, $t, 20.00, 0);
        $this->lotes->cancelarLote($canc);

        $ativos = $this->demo->consultar((int) $e->id, ['situacao' => 'ativos']);
        $this->assertSame(1, $ativos['totais']['qtd_lotes']);
        $this->assertSame('10.00', $ativos['totais']['bruto']);

        $cancelados = $this->demo->consultar((int) $e->id, ['situacao' => 'cancelados']);
        $this->assertSame(1, $cancelados['totais']['qtd_lotes']);
        $this->assertSame((int) $canc->id, $cancelados['rows'][0]['id']);
        $this->assertSame((int) $ativo->id, $ativos['rows'][0]['id']);
    }

    public function test_parcelamentos_e_tipo_credito_debito(): void
    {
        [$e, $t] = $this->seedEmpresa();
        $venda = $this->venda();
        $i = $this->intencao($e, $t, $venda, 90.00, installment: 3, tipo: '2');
        $lote = $this->lotes->criarLote((int) $e->id, [
            ['tef_intencao_id' => $i->id, 'parcela_numero' => 1],
        ], ['terminal_id' => $t->id]);
        $this->lotes->alterarSituacao($lote, TefLiquidacaoLote::SIT_CONFERIDO);

        $r = $this->demo->consultar((int) $e->id, ['situacao' => 'ativos']);
        $this->assertSame('30.00', $r['rows'][0]['bruto']);
        $this->assertSame('Crédito', $r['rows'][0]['tipo']);
        $this->assertSame(1, $r['rows'][0]['qtd_transacoes']);

        $det = $this->demo->detalhe((int) $e->id, (int) $lote->id);
        $this->assertSame(1, $det['itens'][0]['parcela']);
        $this->assertSame(3, $det['itens'][0]['parcelas']);
        $this->assertSame('Crédito', $det['itens'][0]['tipo']);
    }

    public function test_isolamento_provedor_scope(): void
    {
        [$e, $t] = $this->seedEmpresa();
        $super = $this->criarLoteConferido($e, $t, 40.00, 0);

        $venda = $this->venda();
        $iScope = $this->intencao($e, $t, $venda, 70.00, provedor: TefProviderId::SCOPE);
        $loteScope = TefLiquidacaoLote::query()->create([
            'empresa_id' => $e->id,
            'terminal_id' => $t->id,
            'acquirer_cnpj' => '',
            'caixa_conta_id' => TefLiquidacaoDestino::query()->where('terminal_id', $t->id)->value('caixa_conta_id'),
            'periodo_de' => '2026-11-01',
            'periodo_ate' => '2026-11-30',
            'valor_bruto' => 70,
            'valor_taxas' => 0,
            'taxa_origem' => TefLiquidacaoLote::TAXA_NAO_INFORMADA,
            'valor_liquido' => 70,
            'situacao' => TefLiquidacaoLote::SIT_CONFERIDO,
            'ofx_situacao' => TefLiquidacaoLote::OFX_PENDENTE,
            'origem' => 'manual',
        ]);
        TefLiquidacaoLoteItem::query()->create([
            'lote_id' => $loteScope->id,
            'tef_intencao_id' => $iScope->id,
            'pdv_venda_id' => $venda->id,
            'valor_bruto' => 70,
            'valor_taxa' => 0,
            'valor_liquido' => 70,
            'transaction_type' => '1',
            'installment_count' => 1,
            'parcela_numero' => 1,
        ]);

        $r = $this->demo->consultar((int) $e->id, ['situacao' => 'ativos']);
        $ids = collect($r['rows'])->pluck('id')->all();
        $this->assertContains((int) $super->id, $ids);
        $this->assertNotContains((int) $loteScope->id, $ids);
        $this->assertSame('40.00', $r['totais']['bruto']);
    }

    public function test_totais_sem_duplicacao_por_joins(): void
    {
        [$e, $t, $banco] = $this->seedEmpresaComBanco();
        $user = $this->user();
        $lote = $this->criarLoteConferido($e, $t, 100.00, 0);
        $e1 = $this->credito($e, $banco, '40.00', '2026-11-21');
        $e2 = $this->credito($e, $banco, '60.00', '2026-11-22');
        $this->vinculo->vincular($lote, $e1, '40.00', null, (int) $user->id);
        $this->vinculo->vincular($lote->fresh(), $e2, '60.00', null, (int) $user->id);

        $r = $this->demo->consultar((int) $e->id, ['situacao' => 'ativos']);
        $this->assertSame(1, $r['totais']['qtd_lotes']);
        $this->assertSame('100.00', $r['totais']['bruto']);
        $this->assertSame('100.00', $r['totais']['vinculado_ofx']);
    }

    public function test_performance_volume_e_paginacao(): void
    {
        [$e, $t] = $this->seedEmpresa();
        for ($i = 0; $i < 60; $i++) {
            $this->criarLoteConferido($e, $t, 10.00, 0, '2026-10-01', '2026-10-31');
        }

        $mem0 = memory_get_usage(true);
        $t0 = hrtime(true);
        $r = $this->demo->consultar((int) $e->id, [
            'periodo_de' => '2026-10-01',
            'periodo_ate' => '2026-10-31',
            'situacao' => 'ativos',
            'page' => 1,
            'per_page' => 25,
        ]);
        $ms = (hrtime(true) - $t0) / 1e6;
        $memDelta = memory_get_usage(true) - $mem0;

        $this->assertSame(60, $r['total']);
        $this->assertCount(25, $r['rows']);
        $this->assertSame(3, $r['last_page']);
        $this->assertLessThan(1500, $ms, "consultar {$ms}ms");
        $this->assertLessThan(32 * 1024 * 1024, $memDelta, 'memória delta alta');
    }

    public function test_sem_consulta_automatica_empresa_zero(): void
    {
        $r = $this->demo->consultar(0, []);
        $this->assertSame(0, $r['totais']['qtd_lotes']);
        $this->assertSame([], $r['rows']);
    }

    /**
     * @return array{0: Empresa, 1: Terminal}
     */
    private function seedEmpresa(string $nome = 'EMP'): array
    {
        [$e, $t] = $this->seedEmpresaComBanco($nome);

        return [$e, $t];
    }

    /**
     * @return array{0: Empresa, 1: Terminal, 2: CaixaConta}
     */
    private function seedEmpresaComBanco(string $nome = 'EMP'): array
    {
        $empresa = Empresa::query()->create(['razao_social' => $nome, 'nome_fantasia' => $nome]);
        $terminal = Terminal::query()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'PDV '.$nome,
            'ativo' => true,
            'pdv' => true,
        ]);
        $banco = CaixaConta::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 1,
            'nome' => 'BANCO',
            'tipo' => CaixaConta::TIPO_BANCO,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);
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

    private function criarLoteConferido(
        Empresa $empresa,
        Terminal $terminal,
        float $bruto,
        float $taxa = 0,
        string $de = '2026-11-01',
        string $ate = '2026-11-30',
    ): TefLiquidacaoLote {
        $venda = $this->venda();
        $i = $this->intencao($empresa, $terminal, $venda, $bruto);
        $lote = $this->lotes->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], [
            'terminal_id' => $terminal->id,
            'valor_taxas' => $taxa,
            'periodo_de' => $de,
            'periodo_ate' => $ate,
        ]);
        $this->lotes->alterarSituacao($lote, TefLiquidacaoLote::SIT_CONFERIDO);

        return $lote->fresh();
    }

    private function credito(Empresa $empresa, CaixaConta $banco, string $valor, string $data): ExtratoLancamento
    {
        return ExtratoLancamento::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'data_movimento' => $data,
            'tipo' => ExtratoLancamento::TIPO_CREDITO,
            'valor' => $valor,
            'historico' => 'CRED TEF',
            'fitid' => 'F'.uniqid(),
            'fingerprint' => sha1(uniqid('', true)),
            'situacao' => ExtratoLancamento::SITUACAO_PENDENTE,
        ]);
    }

    private function venda(): \App\Models\PdvVenda
    {
        return \App\Models\PdvVenda::query()->create([
            'situacao' => 'F',
            'total' => 0,
            'subtotal' => 0,
            'desconto' => 0,
            'acrescimo' => 0,
        ]);
    }

    private function intencao(
        Empresa $empresa,
        Terminal $terminal,
        \App\Models\PdvVenda $venda,
        float $amount,
        int $installment = 1,
        string $tipo = '1',
        string $provedor = TefProviderId::SUPERTEF,
    ): TefIntencao {
        return TefIntencao::query()->create([
            'empresa_id' => $empresa->id,
            'terminal_id' => $terminal->id,
            'pdv_venda_id' => $venda->id,
            'order_id' => 'ORD-'.uniqid(),
            'pos_id' => 1,
            'amount' => $amount,
            'transaction_type' => $tipo,
            'installment_count' => $installment,
            'installment_type' => 1,
            'status' => TefIntencao::STATUS_PAGA,
            'pago_em' => now(),
            'provedor' => $provedor,
            'requer_reconciliacao' => false,
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
            'contas_receber',
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
        Schema::create('contas_receber', function (Blueprint $table): void {
            $table->id();
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
            $table->string('provedor', 20)->default('supertef');
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
