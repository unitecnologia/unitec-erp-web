<?php

namespace Tests\Unit;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\PixCobranca;
use App\Models\User;
use App\Support\Erp\Pdv\PdvCaixaFechamentoService;
use App\Support\Erp\Pdv\PdvCaixaMovimentoService;
use App\Support\Erp\Queries\CaixaPdvMovimentacaoQuery;
use App\Support\Pix\PixPdvLivroCaixaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ciclo daqui para frente: abertura com PDV vinculado → venda/recebimento →
 * aparece na retaguarda → fechamento no Geral sem duplicar PIX bancário.
 * Schema isolado (não toca sessões DEV / sessão 8).
 */
class PdvCaixaCicloCompletoTest extends TestCase
{
    private Empresa $empresa;

    private User $user;

    private CaixaConta $pdv1;

    private CaixaConta $geral;

    private CaixaConta $banco;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchema();

        $this->empresa = Empresa::query()->create(['nome' => 'EMP CICLO', 'ativo' => true]);
        $this->geral = CaixaConta::ensureCaixaGeral();
        $this->pdv1 = CaixaConta::query()->create([
            'codigo' => 2,
            'nome' => 'CAIXA PDV 1',
            'tipo' => CaixaConta::TIPO_PDV,
            'ativo' => true,
            'sistema' => false,
        ]);
        $this->banco = CaixaConta::query()->create([
            'codigo' => 6,
            'nome' => 'CONTA AILOS',
            'tipo' => CaixaConta::TIPO_BANCO,
            'ativo' => true,
            'sistema' => false,
            'empresa_id' => $this->empresa->id,
        ]);
        $this->empresa->forceFill(['param_pix_caixa_conta_id' => $this->banco->id])->save();

        $this->user = User::query()->create([
            'name' => 'USUARIO',
            'password' => Hash::make('x'),
            'empresa_id' => $this->empresa->id,
            'ativo' => true,
            'is_admin' => true,
        ]);

        DB::table('caixa_conta_user')->insert([
            'user_id' => $this->user->id,
            'empresa_id' => $this->empresa->id,
            'caixa_conta_id' => $this->pdv1->id,
            'is_padrao' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ciclo_abertura_venda_recebimento_fechamento_sem_duplicar_pix(): void
    {
        // 1) Abertura obrigatória no Caixa PDV vinculado (nunca Geral/null)
        $caixaId = $this->user->caixaPdvVinculadoId((int) $this->empresa->id);
        $this->assertSame((int) $this->pdv1->id, (int) $caixaId);
        $this->assertNotSame((int) $this->geral->id, (int) $caixaId);

        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $this->user->id,
            'empresa_id' => $this->empresa->id,
            'terminal_id' => 1,
            'caixa_conta_id' => $caixaId,
            'valor_abertura' => 10,
            'aberto_em' => now(),
        ]);
        PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'abertura',
            'historico' => 'ABERTURA DE CAIXA',
            'entrada' => 10,
            'saida' => 0,
        ]);

        $this->assertSame((int) $this->pdv1->id, (int) $sessao->fresh()->caixa_conta_id);
        $this->assertNotNull($sessao->caixa_conta_id);

        // 2) Venda dinheiro + PIX Ailos (banco) + recebimento CR
        $vendaDin = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'numero' => '100',
            'total' => 40,
            'situacao' => 'fechada',
        ]);
        (new PdvCaixaMovimentoService())->registrarEntradasVenda($sessao->id, $vendaDin, [
            ['forma' => 'DINHEIRO', 'valor' => 40, 'tipo' => 'dinheiro', 'tipo_movimento' => 'caixa'],
        ]);

        [$vendaPix, $pagPix, $cobranca] = $this->vendaPixAilos(25.00, $sessao);
        $pixLanc = app(PixPdvLivroCaixaService::class)->vincularELancar((int) $cobranca->id, $vendaPix, $pagPix);
        $this->assertNotNull($pixLanc);
        $this->assertSame((int) $this->banco->id, (int) $pixLanc->caixa_conta_id);

        // Movimento PIX na sessão (como o PDV registra) — fechamento deve excluir do Geral
        PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'venda',
            'historico' => 'VENDA PDV #101 - PIX',
            'forma_pagamento' => 'PIX',
            'entrada' => 25,
            'saida' => 0,
            'pdv_venda_id' => $vendaPix->id,
        ]);

        PdvCaixaMovimento::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'tipo' => 'recebimento',
            'historico' => 'RECEB. CR 999',
            'forma_pagamento' => 'DINHEIRO',
            'entrada' => 15,
            'saida' => 0,
        ]);

        // 3) Retaguarda: aparece no filtro do Caixa PDV 1 (não no legado null)
        $aggPdv = $this->aggFiltro((int) $this->pdv1->id);
        $this->assertContains((int) $sessao->id, $aggPdv['sessoes']);
        $this->assertGreaterThanOrEqual(1, $aggPdv['vendas']);

        $aggLegado = $this->aggFiltro(CaixaPdvMovimentacaoQuery::FILTRO_SESSOES_SEM_CONTA);
        $this->assertNotContains((int) $sessao->id, $aggLegado['sessoes']);

        // 4) Fechamento → Geral só dinheiro+recebimento (40+15=55); PIX fica no banco
        $sessao->forceFill(['fechado_em' => now(), 'valor_fechamento' => 90])->save();
        $fechamento = new PdvCaixaFechamentoService();
        $lancs = $fechamento->lancarNoLivroCaixa($sessao->fresh(), $this->user);
        $this->assertCount(1, $lancs);
        $this->assertSame((int) $this->geral->id, (int) $lancs[0]->caixa_conta_id);
        $this->assertEquals(55.00, (float) $lancs[0]->entrada);
        $this->assertSame('PDV-CX-'.$sessao->id, (string) $lancs[0]->documento);

        // Idempotência: não duplica consolidação
        $again = $fechamento->lancarNoLivroCaixa($sessao->fresh(), $this->user);
        $this->assertSame([], $again);
        $this->assertSame(1, CaixaLancamento::query()->where('documento', 'PDV-CX-'.$sessao->id)->count());

        $this->assertEquals(25.00, (float) CaixaLancamento::query()
            ->where('caixa_conta_id', $this->banco->id)->sum('entrada'));
        $this->assertSame(1, CaixaLancamento::query()
            ->where('documento', PixPdvLivroCaixaService::documentoDoPagamento((int) $pagPix->id))
            ->count());
    }

    public function test_sessao_aberta_sem_caixa_pdv_nao_e_valida_para_reuse(): void
    {
        $legado = PdvCaixaSessao::query()->create([
            'user_id' => $this->user->id,
            'empresa_id' => $this->empresa->id,
            'terminal_id' => 99,
            'caixa_conta_id' => null,
            'valor_abertura' => 200,
            'aberto_em' => now()->subDays(30),
        ]);

        $this->assertNull($legado->caixa_conta_id);
        $this->assertFalse($this->caixaContaIdEhPdvOperacional(null));
        $this->assertFalse($this->caixaContaIdEhPdvOperacional((int) $this->geral->id));
        $this->assertTrue($this->caixaContaIdEhPdvOperacional((int) $this->pdv1->id));
    }

    private function caixaContaIdEhPdvOperacional(?int $caixaContaId): bool
    {
        if (! $caixaContaId || $caixaContaId <= 0) {
            return false;
        }
        if ($caixaContaId === (int) $this->geral->id) {
            return false;
        }
        $tipo = (string) (CaixaConta::query()->whereKey($caixaContaId)->value('tipo') ?? '');

        return in_array($tipo, [CaixaConta::TIPO_PDV, 'CAIXA', 'X'], true);
    }

    /**
     * @return array{sessoes: list<int>, vendas: int}
     */
    private function aggFiltro(int $contaId): array
    {
        $rows = DB::query()->fromSub((new CaixaPdvMovimentacaoQuery($contaId))->build(), 'x')->get();

        return [
            'sessoes' => $rows->pluck('pdv_sessao_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'vendas' => $rows->filter(fn ($r) => str_starts_with((string) $r->historico, 'VENDA PDV'))->count(),
        ];
    }

    /**
     * @return array{0: PdvVenda, 1: PdvVendaPagamento, 2: PixCobranca}
     */
    private function vendaPixAilos(float $valor, PdvCaixaSessao $sessao): array
    {
        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'numero' => '101',
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
            'order_uuid' => 'ciclo-pix-'.uniqid(),
            'provedor' => 'ailos',
            'txid' => 'TXCICLO'.random_int(100, 999),
            'valor' => $valor,
            'status' => PixCobranca::STATUS_PAGO,
            'pago_em' => now(),
        ]);

        return [$venda, $pag, $cobranca];
    }

    private function criarSchema(): void
    {
        foreach ([
            'pdv_caixa_movimentos', 'pdv_venda_pagamentos', 'pdv_vendas', 'pdv_caixa_sessoes',
            'pix_cobrancas', 'caixa_lancamentos', 'caixa_conta_user', 'vendedores', 'users',
            'terminais', 'planos_contas', 'caixa_contas', 'empresas',
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
            $table->string('tipo', 20)->default('PDV');
            $table->string('situacao', 20)->default('aberto');
            $table->boolean('ativo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->timestamps();
        });
        Schema::create('vendedores', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('password');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('vendedor_id')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('caixa_conta_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('caixa_conta_id');
            $table->boolean('is_padrao')->default(false);
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
        Schema::create('terminais', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 80)->nullable();
            $table->timestamps();
        });
        Schema::create('planos_contas', function (Blueprint $table): void {
            $table->id();
            $table->string('descricao', 120)->nullable();
            $table->string('dc', 1)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->decimal('valor_abertura', 14, 2)->default(0);
            $table->decimal('valor_fechamento', 14, 2)->nullable();
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
            $table->decimal('troco', 14, 2)->default(0);
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
            $table->string('plano_conta_codigo', 40)->nullable();
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
    }
}
