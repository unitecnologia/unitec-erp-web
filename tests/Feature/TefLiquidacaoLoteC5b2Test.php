<?php

namespace Tests\Feature;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\TefIntencao;
use App\Models\TefLiquidacaoDestino;
use App\Models\TefLiquidacaoLote;
use App\Models\TefLiquidacaoLoteItem;
use App\Models\Terminal;
use App\Support\Erp\Tef\SuperTefTerminalConfig;
use App\Support\Erp\Tef\TefLiquidacaoLoteService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class TefLiquidacaoLoteC5b2Test extends TestCase
{
    private TefLiquidacaoLoteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        $this->service = app(TefLiquidacaoLoteService::class);
    }

    public function test_cria_lote_com_transacoes_validas_e_soma_bruto(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBaseComDestino();
        $v1 = $this->venda($empresa);
        $v2 = $this->venda($empresa);
        $i1 = $this->intencao($empresa, $terminal, $v1, 100.00);
        $i2 = $this->intencao($empresa, $terminal, $v2, 50.50);

        $caixaAntes = DB::table('caixa_lancamentos')->count();

        $lote = $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i1->id],
            ['tef_intencao_id' => $i2->id],
        ], [
            'terminal_id' => $terminal->id,
            'valor_taxas' => 0,
        ]);

        $this->assertSame('150.50', (string) $lote->valor_bruto);
        $this->assertSame('0.00', (string) $lote->valor_taxas);
        $this->assertSame('150.50', (string) $lote->valor_liquido);
        $this->assertSame(TefLiquidacaoLote::TAXA_NAO_INFORMADA, $lote->taxa_origem);
        $this->assertSame(2, $lote->itens()->count());
        $this->assertSame((int) $banco->id, (int) $lote->caixa_conta_id);
        $this->assertSame($caixaAntes, DB::table('caixa_lancamentos')->count());
        $this->assertSame(0, DB::table('contas_receber')->count());
    }

    public function test_taxa_informada_e_liquido(): void
    {
        [$empresa, $terminal] = $this->seedBaseComDestino();
        $i1 = $this->intencao($empresa, $terminal, $this->venda($empresa), 200.00);

        $lote = $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i1->id],
        ], [
            'terminal_id' => $terminal->id,
            'valor_taxas' => 7.35,
        ]);

        $this->assertSame('200.00', (string) $lote->valor_bruto);
        $this->assertSame('7.35', (string) $lote->valor_taxas);
        $this->assertSame('192.65', (string) $lote->valor_liquido);
        $this->assertSame(TefLiquidacaoLote::TAXA_INFORMADA, $lote->taxa_origem);
    }

    public function test_lote_com_parcelas(): void
    {
        [$empresa, $terminal] = $this->seedBaseComDestino();
        $i = $this->intencao($empresa, $terminal, $this->venda($empresa), 100.00, installment: 3);

        $lote1 = $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id, 'parcela_numero' => 1],
        ], ['terminal_id' => $terminal->id]);

        $this->assertSame('33.33', (string) $lote1->valor_bruto);
        $this->assertSame(1, (int) $lote1->itens()->first()->parcela_numero);

        $lote2 = $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id, 'parcela_numero' => 2],
        ], ['terminal_id' => $terminal->id]);
        $this->assertSame('33.33', (string) $lote2->valor_bruto);

        $lote3 = $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id, 'parcela_numero' => 3],
        ], ['terminal_id' => $terminal->id]);
        $this->assertSame('33.34', (string) $lote3->valor_bruto);
    }

    public function test_bloqueia_cancelada_ou_estornada(): void
    {
        [$empresa, $terminal] = $this->seedBaseComDestino();
        $i = $this->intencao($empresa, $terminal, $this->venda($empresa), 10.00);
        $i->status = TefIntencao::STATUS_CANCELADA;
        $i->save();

        $this->expectException(InvalidArgumentException::class);
        $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id]);
    }

    public function test_bloqueia_orfa_sem_venda(): void
    {
        [$empresa, $terminal] = $this->seedBaseComDestino();
        $i = $this->intencao($empresa, $terminal, null, 10.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('órfã');
        $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id]);
    }

    public function test_duplicidade_em_lote_ativo(): void
    {
        [$empresa, $terminal] = $this->seedBaseComDestino();
        $i = $this->intencao($empresa, $terminal, $this->venda($empresa), 10.00);
        $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('já está em lote ativo');
        $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id]);
    }

    public function test_cancelar_lote_libera_intencao(): void
    {
        [$empresa, $terminal] = $this->seedBaseComDestino();
        $i = $this->intencao($empresa, $terminal, $this->venda($empresa), 10.00);
        $lote = $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id]);

        $this->service->cancelarLote($lote);

        $novo = $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id]);
        $this->assertNotSame($lote->id, $novo->id);
        $this->assertSame(TefIntencao::STATUS_PAGA, $i->fresh()->status);
    }

    public function test_outra_empresa_bloqueada(): void
    {
        [$empresa, $terminal] = $this->seedBaseComDestino();
        $outra = Empresa::query()->create(['razao_social' => 'OUTRA', 'nome_fantasia' => 'O']);
        $i = $this->intencao($outra, $terminal, $this->venda($outra), 10.00);

        $this->expectException(InvalidArgumentException::class);
        $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id]);
    }

    public function test_destino_ausente_bloqueia(): void
    {
        $empresa = Empresa::query()->create(['razao_social' => 'E', 'nome_fantasia' => 'E']);
        $terminal = Terminal::query()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'T',
            'ativo' => true,
            'pdv' => true,
        ]);
        $i = $this->intencao($empresa, $terminal, $this->venda($empresa), 10.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Destino bancário');
        $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id]);
    }

    public function test_destino_ambiguo_bloqueia(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBaseComDestino();
        $banco2 = $this->criarBanco($empresa, 9902, 'B2');
        Schema::table('tef_liquidacao_destinos', function (Blueprint $table): void {
            $table->dropUnique('tef_liq_dest_emp_term_prov_acq_uq');
        });
        TefLiquidacaoDestino::query()->create([
            'empresa_id' => $empresa->id,
            'terminal_id' => $terminal->id,
            'acquirer_cnpj' => '',
            'caixa_conta_id' => $banco2->id,
            'ativo' => true,
        ]);

        $i = $this->intencao($empresa, $terminal, $this->venda($empresa), 10.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ambiguo');
        $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id]);
    }

    public function test_adquirente_nao_identificada_no_lote_sem_cnpj(): void
    {
        [$empresa, $terminal] = $this->seedBaseComDestino();
        $i = $this->intencao($empresa, $terminal, $this->venda($empresa), 15.00);
        $i->acquirer_cnpj = null;
        $i->save();

        $lote = $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id, 'acquirer_cnpj' => '']);

        $this->assertSame('', (string) $lote->acquirer_cnpj);
    }

    public function test_conta_destino_vem_do_c5b1_nao_de_outra_conta_arbitraria(): void
    {
        [$empresa, $terminal, $banco] = $this->seedBaseComDestino();
        $outro = $this->criarBanco($empresa, 9910, 'OUTRO');
        $i = $this->intencao($empresa, $terminal, $this->venda($empresa), 10.00);

        $lote = $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id]);

        $this->assertSame((int) $banco->id, (int) $lote->caixa_conta_id);
        $this->assertNotSame((int) $outro->id, (int) $lote->caixa_conta_id);
    }

    public function test_integridade_livro_caixa_e_status_tef(): void
    {
        [$empresa, $terminal] = $this->seedBaseComDestino();
        $i = $this->intencao($empresa, $terminal, $this->venda($empresa), 22.00);
        $beforeIntent = $i->fresh()->getAttributes();
        $caixaAntes = DB::table('caixa_lancamentos')->count();

        $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $i->id],
        ], ['terminal_id' => $terminal->id, 'valor_taxas' => 1.00]);

        $this->assertSame($beforeIntent, $i->fresh()->getAttributes());
        $this->assertSame($caixaAntes, DB::table('caixa_lancamentos')->count());
        $this->assertSame(0, DB::table('extrato_conciliacoes')->count());
    }

    public function test_regressao_supertef_config(): void
    {
        $t = new Terminal;
        $t->forceFill(['tef_extra' => ['ativo' => true, 'pos_id' => 2]]);
        $cfg = SuperTefTerminalConfig::read($t, false);
        $this->assertTrue($cfg['ativo']);
        $this->assertArrayNotHasKey('lote_id', $cfg);
    }

    public function test_query_candidatas_exclui_orfas_e_ocupadas(): void
    {
        [$empresa, $terminal] = $this->seedBaseComDestino();
        $ok = $this->intencao($empresa, $terminal, $this->venda($empresa), 10.00);
        $this->intencao($empresa, $terminal, null, 9.00); // órfã
        $ocupada = $this->intencao($empresa, $terminal, $this->venda($empresa), 8.00);
        $this->service->criarLote((int) $empresa->id, [
            ['tef_intencao_id' => $ocupada->id],
        ], ['terminal_id' => $terminal->id]);

        $ids = $this->service->queryCandidatas((int) $empresa->id, [
            'terminal_id' => (int) $terminal->id,
        ])->pluck('id')->all();

        $this->assertContains((int) $ok->id, $ids);
        $this->assertNotContains((int) $ocupada->id, $ids);
    }

    public function test_performance_volume(): void
    {
        [$empresa, $terminal] = $this->seedBaseComDestino();
        $venda = $this->venda($empresa);
        $ids = [];
        for ($n = 0; $n < 120; $n++) {
            $i = $this->intencao($empresa, $terminal, $venda, 1.00 + ($n % 7) * 0.1);
            if ($n < 80) {
                $ids[] = ['tef_intencao_id' => (int) $i->id];
            }
        }

        $t0 = hrtime(true);
        $this->service->queryCandidatas((int) $empresa->id, [
            'terminal_id' => (int) $terminal->id,
        ])->limit(50)->get(['id', 'amount']);
        $qMs = (hrtime(true) - $t0) / 1e6;

        $t1 = hrtime(true);
        $lote = $this->service->criarLote((int) $empresa->id, $ids, [
            'terminal_id' => $terminal->id,
            'valor_taxas' => 5.00,
        ]);
        $cMs = (hrtime(true) - $t1) / 1e6;

        $this->assertSame(80, $lote->itens()->count());
        $this->assertLessThan(800, $qMs, "query candidatas {$qMs}ms");
        $this->assertLessThan(2000, $cMs, "criar lote {$cMs}ms");
    }

    /**
     * @return array{0: Empresa, 1: Terminal, 2: CaixaConta}
     */
    private function seedBaseComDestino(): array
    {
        $empresa = Empresa::query()->create(['razao_social' => 'EMP', 'nome_fantasia' => 'EMP']);
        $terminal = Terminal::query()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'PDV 1',
            'ativo' => true,
            'pdv' => true,
        ]);
        $banco = $this->criarBanco($empresa, 9201, 'BANCO');
        TefLiquidacaoDestino::query()->create([
            'empresa_id' => $empresa->id,
            'terminal_id' => $terminal->id,
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

    private function venda(Empresa $empresa): PdvVenda
    {
        return PdvVenda::query()->create([
            'situacao' => 'F',
            'total' => 10,
            'subtotal' => 10,
            'desconto' => 0,
            'acrescimo' => 0,
        ]);
    }

    private function intencao(
        Empresa $empresa,
        Terminal $terminal,
        ?PdvVenda $venda,
        float $amount,
        int $installment = 1,
    ): TefIntencao {
        return TefIntencao::query()->create([
            'empresa_id' => $empresa->id,
            'terminal_id' => $terminal->id,
            'pdv_venda_id' => $venda?->id,
            'order_id' => 'ORD-'.uniqid('', true),
            'pos_id' => 1,
            'amount' => $amount,
            'transaction_type' => '1',
            'installment_count' => $installment,
            'installment_type' => 1,
            'status' => TefIntencao::STATUS_PAGA,
            'pago_em' => now(),
            'nsu' => 'NSU'.random_int(1000, 9999),
            'autorizacao' => 'AUT',
            'bandeira' => 'VISA',
            'acquirer_cnpj' => null,
            'requer_reconciliacao' => false,
        ]);
    }

    private function createMinimalSchema(): void
    {
        foreach ([
            'tef_liquidacao_lote_itens', 'tef_liquidacao_lotes', 'tef_liquidacao_destinos',
            'tef_intencoes', 'pdv_vendas', 'caixa_lancamentos', 'contas_receber',
            'extrato_conciliacoes', 'caixa_contas', 'terminais', 'empresas',
        ] as $t) {
            Schema::dropIfExists($t);
        }

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
            $table->text('tef_extra')->nullable();
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
            $table->unsignedBigInteger('pdv_venda_id')->nullable()->index();
            $table->string('order_id', 64)->unique();
            $table->unsignedInteger('pos_id')->default(1);
            $table->decimal('amount', 12, 2);
            $table->string('transaction_type', 8)->default('1');
            $table->unsignedTinyInteger('installment_count')->default(1);
            $table->unsignedTinyInteger('installment_type')->default(1);
            $table->string('status', 24)->index();
            $table->timestamp('pago_em')->nullable();
            $table->string('nsu', 40)->nullable();
            $table->string('autorizacao', 40)->nullable();
            $table->string('bandeira', 40)->nullable();
            $table->string('acquirer_cnpj', 14)->nullable();
            $table->boolean('requer_reconciliacao')->default(false);
            $table->json('raw_last')->nullable();
            $table->timestamps();
        });

        Schema::create('tef_liquidacao_destinos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('terminal_id')->index();
            $table->string('provedor', 20)->default('supertef');
            $table->string('acquirer_cnpj', 14)->default('');
            $table->unsignedBigInteger('caixa_conta_id')->index();
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->unique(
                ['empresa_id', 'terminal_id', 'provedor', 'acquirer_cnpj'],
                'tef_liq_dest_emp_term_prov_acq_uq'
            );
        });

        Schema::create('tef_liquidacao_lotes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('terminal_id')->index();
            $table->string('acquirer_cnpj', 14)->default('');
            $table->unsignedBigInteger('caixa_conta_id')->index();
            $table->date('periodo_de')->nullable();
            $table->date('periodo_ate')->nullable();
            $table->date('data_prevista_liquidacao')->nullable();
            $table->decimal('valor_bruto', 15, 2)->default(0);
            $table->decimal('valor_taxas', 15, 2)->default(0);
            $table->string('taxa_origem', 24)->default('nao_informada');
            $table->decimal('valor_liquido', 15, 2)->default(0);
            $table->string('situacao', 24)->default('preparacao')->index();
            $table->string('identificador_externo', 80)->nullable();
            $table->string('origem', 40)->default('manual');
            $table->string('observacao', 500)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('tef_liquidacao_lote_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lote_id')->index();
            $table->unsignedBigInteger('tef_intencao_id')->index();
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
            $table->index(['tef_intencao_id', 'parcela_numero'], 'tef_liq_item_intencao_parcela_idx');
        });

        Schema::create('caixa_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
        Schema::create('contas_receber', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
        Schema::create('extrato_conciliacoes', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }
}
