<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\EstoqueTransferencia;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\EstoqueTransferenciaException;
use App\Support\Erp\EstoqueTransferenciaSaldoCompat;
use App\Support\Erp\EstoqueTransferenciaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Schema mínimo (sem MigratesSqliteMemory): migrate :memory: completo
 * ainda falha em legado do projeto; cobre as regras da Etapa 1.
 */
class EstoqueTransferenciaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaMinimo();
    }

    public function test_transferencia_valida_move_saldos_e_mantem_global(): void
    {
        [$empresa, $origem, $destino, $user] = $this->cenario();
        $product = $this->produto('TRF-OK', 10, (int) $origem->id);
        $this->entrar($user, (int) $empresa->id);

        $globalAntes = (float) $product->fresh()->estoque;

        $transf = app(EstoqueTransferenciaService::class)->confirmar([
            'empresa_id' => (int) $empresa->id,
            'estoque_origem_id' => (int) $origem->id,
            'estoque_destino_id' => (int) $destino->id,
            'idempotencia_token' => (string) Str::uuid(),
            'itens' => [['product_id' => (int) $product->id, 'qtd' => 4]],
        ]);

        $this->assertTrue($transf->isConfirmada());
        $this->assertSame(EstoqueTransferencia::MODO_INTERNA, $transf->modo);
        $this->assertEquals(6, $this->saldo((int) $product->id, (int) $origem->id));
        $this->assertEquals(4, $this->saldo((int) $product->id, (int) $destino->id));
        $this->assertEquals($globalAntes, (float) $product->fresh()->estoque);

        $movs = EstoqueMovimentacao::query()
            ->where('origem_tipo', 'estoque_transferencia')
            ->where('origem_id', $transf->id)
            ->where('tipo', EstoqueMovimentacao::TIPO_TRANSFERENCIA)
            ->get();
        $this->assertCount(2, $movs);
        $this->assertEqualsWithDelta(0.0, (float) $movs->sum('quantidade'), 0.001);
    }

    public function test_saldo_insuficiente_bloqueia(): void
    {
        [$empresa, $origem, $destino, $user] = $this->cenario();
        $product = $this->produto('TRF-INS', 2, (int) $origem->id);
        $this->entrar($user, (int) $empresa->id);

        $this->expectException(EstoqueTransferenciaException::class);
        $this->expectExceptionMessage('Estoque insuficiente');

        app(EstoqueTransferenciaService::class)->confirmar([
            'empresa_id' => (int) $empresa->id,
            'estoque_origem_id' => (int) $origem->id,
            'estoque_destino_id' => (int) $destino->id,
            'itens' => [['product_id' => (int) $product->id, 'qtd' => 5]],
        ]);
    }

    public function test_origem_igual_destino_bloqueia(): void
    {
        [$empresa, $origem, , $user] = $this->cenario();
        $product = $this->produto('TRF-EQ', 5, (int) $origem->id);
        $this->entrar($user, (int) $empresa->id);

        $this->expectException(EstoqueTransferenciaException::class);
        $this->expectExceptionMessage('devem ser diferentes');

        app(EstoqueTransferenciaService::class)->confirmar([
            'empresa_id' => (int) $empresa->id,
            'estoque_origem_id' => (int) $origem->id,
            'estoque_destino_id' => (int) $origem->id,
            'itens' => [['product_id' => (int) $product->id, 'qtd' => 1]],
        ]);
    }

    public function test_confirmacao_duplicada_idempotente_por_token(): void
    {
        [$empresa, $origem, $destino, $user] = $this->cenario();
        $product = $this->produto('TRF-IDEM', 8, (int) $origem->id);
        $this->entrar($user, (int) $empresa->id);
        $token = (string) Str::uuid();
        $service = app(EstoqueTransferenciaService::class);

        $payload = [
            'empresa_id' => (int) $empresa->id,
            'estoque_origem_id' => (int) $origem->id,
            'estoque_destino_id' => (int) $destino->id,
            'idempotencia_token' => $token,
            'itens' => [['product_id' => (int) $product->id, 'qtd' => 3]],
        ];

        $a = $service->confirmar($payload);
        $b = $service->confirmar($payload);

        $this->assertSame((int) $a->id, (int) $b->id);
        $this->assertEquals(5, $this->saldo((int) $product->id, (int) $origem->id));
        $this->assertEquals(3, $this->saldo((int) $product->id, (int) $destino->id));
        $this->assertSame(1, EstoqueTransferencia::query()->where('idempotencia_token', $token)->count());
        $this->assertSame(
            2,
            EstoqueMovimentacao::query()
                ->where('origem_tipo', 'estoque_transferencia')
                ->where('origem_id', $a->id)
                ->count()
        );
    }

    public function test_confirmacao_concorrente_do_mesmo_rascunho_nao_duplica(): void
    {
        [$empresa, $origem, $destino, $user] = $this->cenario();
        $product = $this->produto('TRF-CONC', 10, (int) $origem->id);
        $this->entrar($user, (int) $empresa->id);

        $rascunho = EstoqueTransferencia::query()->create([
            'empresa_origem_id' => (int) $empresa->id,
            'empresa_destino_id' => (int) $empresa->id,
            'estoque_origem_id' => (int) $origem->id,
            'estoque_destino_id' => (int) $destino->id,
            'numero' => EstoqueTransferencia::nextNumero((int) $empresa->id),
            'situacao' => EstoqueTransferencia::SITUACAO_RASCUNHO,
            'modo' => EstoqueTransferencia::MODO_INTERNA,
            'data' => now()->toDateString(),
            'usuario_id' => (int) $user->id,
            'idempotencia_token' => (string) Str::uuid(),
        ]);

        $service = app(EstoqueTransferenciaService::class);
        $payload = [
            'empresa_id' => (int) $empresa->id,
            'estoque_origem_id' => (int) $origem->id,
            'estoque_destino_id' => (int) $destino->id,
            'transferencia_id' => (int) $rascunho->id,
            'idempotencia_token' => (string) $rascunho->idempotencia_token,
            'itens' => [['product_id' => (int) $product->id, 'qtd' => 2]],
        ];

        $primeira = $service->confirmar($payload);
        $segunda = $service->confirmar($payload);

        $this->assertTrue($primeira->isConfirmada());
        $this->assertSame((int) $primeira->id, (int) $segunda->id);
        $this->assertEquals(8, $this->saldo((int) $product->id, (int) $origem->id));
        $this->assertEquals(2, $this->saldo((int) $product->id, (int) $destino->id));
    }

    public function test_falha_faz_rollback_integral(): void
    {
        [$empresa, $origem, $destino, $user] = $this->cenario();
        $ok = $this->produto('TRF-RB1', 5, (int) $origem->id);
        $ruim = $this->produto('TRF-RB2', 1, (int) $origem->id);
        Product::query()->whereKey($ruim->id)->update(['estoque' => 0]);
        $this->entrar($user, (int) $empresa->id);

        $saldoOkAntes = $this->saldo((int) $ok->id, (int) $origem->id);
        $globalOkAntes = (float) $ok->fresh()->estoque;
        $movAntes = EstoqueMovimentacao::query()->count();
        $transfAntes = EstoqueTransferencia::query()->count();

        try {
            app(EstoqueTransferenciaService::class)->confirmar([
                'empresa_id' => (int) $empresa->id,
                'estoque_origem_id' => (int) $origem->id,
                'estoque_destino_id' => (int) $destino->id,
                'itens' => [
                    ['product_id' => (int) $ok->id, 'qtd' => 1],
                    ['product_id' => (int) $ruim->id, 'qtd' => 1],
                ],
            ]);
            $this->fail('Deveria ter bloqueado por inconsistência de saldo.');
        } catch (EstoqueTransferenciaException $e) {
            $this->assertStringContainsString('bloqueada', mb_strtolower($e->getMessage()));
        }

        $this->assertEquals($saldoOkAntes, $this->saldo((int) $ok->id, (int) $origem->id));
        $this->assertEquals($globalOkAntes, (float) $ok->fresh()->estoque);
        $this->assertEquals(0, $this->saldo((int) $ok->id, (int) $destino->id));
        $this->assertSame($movAntes, EstoqueMovimentacao::query()->count());
        $this->assertSame($transfAntes, EstoqueTransferencia::query()->count());
    }

    public function test_isolamento_entre_empresas(): void
    {
        [$empresaA, $origemA, , $userA] = $this->cenario();
        $empresaB = Empresa::query()->create([
            'codigo' => (string) random_int(1000, 9999),
            'nome' => 'EMPRESA B',
            'fantasia' => 'EMPRESA B',
            'ativo' => true,
        ]);
        $origemB = Estoque::query()->create([
            'empresa_id' => $empresaB->id,
            'codigo' => '1',
            'nome' => 'DEP B1',
            'ativo' => true,
        ]);
        $product = $this->produto('TRF-ISO', 7, (int) $origemA->id);
        $this->entrar($userA, (int) $empresaA->id);

        $this->expectException(EstoqueTransferenciaException::class);
        $this->expectExceptionMessage('mesma empresa');

        app(EstoqueTransferenciaService::class)->confirmar([
            'empresa_id' => (int) $empresaA->id,
            'estoque_origem_id' => (int) $origemA->id,
            'estoque_destino_id' => (int) $origemB->id,
            'itens' => [['product_id' => (int) $product->id, 'qtd' => 1]],
        ]);
    }

    public function test_entre_cnpjs_bloqueado_explicitamente(): void
    {
        $this->expectException(EstoqueTransferenciaException::class);
        $this->expectExceptionMessage('CNPJs');

        app(EstoqueTransferenciaService::class)->assertModoInternoPermitido(1, 2);
    }

    public function test_divergencia_pdv_bloqueia_produto_sem_alterar_historico(): void
    {
        [$empresa, $origem, $destino, $user] = $this->cenario();
        $product = $this->produto('TRF-DIV', 10, (int) $origem->id);
        Product::query()->whereKey($product->id)->update(['estoque' => 3]);
        $this->entrar($user, (int) $empresa->id);

        $compat = new EstoqueTransferenciaSaldoCompat();
        $this->assertTrue($compat->depositosSuperestimamGlobal((int) $product->id));
        $this->assertEquals(10, $compat->saldoExplicitoDeposito((int) $product->id, (int) $origem->id));

        $this->expectException(EstoqueTransferenciaException::class);
        $this->expectExceptionMessage('divergência');

        app(EstoqueTransferenciaService::class)->confirmar([
            'empresa_id' => (int) $empresa->id,
            'estoque_origem_id' => (int) $origem->id,
            'estoque_destino_id' => (int) $destino->id,
            'itens' => [['product_id' => (int) $product->id, 'qtd' => 1]],
        ]);
    }

    public function test_sem_saldo_explicito_no_deposito_nao_usa_global_legado(): void
    {
        [$empresa, $origem, $destino, $user] = $this->cenario();
        $product = Product::query()->create([
            'codigo' => 'TRF-LEG'.random_int(10, 99),
            'descricao' => 'SEM SALDO EXPLICITO',
            'unidade' => 'UN',
            'estoque' => 20,
            'preco_venda' => 1,
            'ativo' => true,
        ]);
        $this->entrar($user, (int) $empresa->id);

        $compat = new EstoqueTransferenciaSaldoCompat();
        $this->assertEquals(0.0, $compat->disponivelParaTransferir((int) $product->id, (int) $origem->id));

        $this->expectException(EstoqueTransferenciaException::class);
        $this->expectExceptionMessage('insuficiente');

        app(EstoqueTransferenciaService::class)->confirmar([
            'empresa_id' => (int) $empresa->id,
            'estoque_origem_id' => (int) $origem->id,
            'estoque_destino_id' => (int) $destino->id,
            'itens' => [['product_id' => (int) $product->id, 'qtd' => 1]],
        ]);
    }

    public function test_nao_cria_movimentacao_financeira(): void
    {
        [$empresa, $origem, $destino, $user] = $this->cenario();
        $product = $this->produto('TRF-FIN', 4, (int) $origem->id);
        $this->entrar($user, (int) $empresa->id);

        $caixaAntes = (int) DB::table('caixa_lancamentos')->count();

        app(EstoqueTransferenciaService::class)->confirmar([
            'empresa_id' => (int) $empresa->id,
            'estoque_origem_id' => (int) $origem->id,
            'estoque_destino_id' => (int) $destino->id,
            'itens' => [['product_id' => (int) $product->id, 'qtd' => 2]],
        ]);

        $this->assertSame($caixaAntes, (int) DB::table('caixa_lancamentos')->count());
    }

    public function test_usuario_sem_acesso_a_empresa_e_bloqueado(): void
    {
        [$empresa, $origem, $destino] = $this->cenario();
        $outro = User::query()->create([
            'name' => 'Sem Acesso',
            'email' => 'semacesso'.random_int(1000, 9999).'@test.local',
            'password' => bcrypt('x'),
            'empresa_id' => null,
            'is_admin' => false,
            'ativo' => true,
        ]);
        $product = $this->produto('TRF-PERM', 3, (int) $origem->id);
        $this->entrar($outro, (int) $empresa->id);

        $this->expectException(EstoqueTransferenciaException::class);
        $this->expectExceptionMessage('sem acesso');

        app(EstoqueTransferenciaService::class)->confirmar([
            'empresa_id' => (int) $empresa->id,
            'estoque_origem_id' => (int) $origem->id,
            'estoque_destino_id' => (int) $destino->id,
            'itens' => [['product_id' => (int) $product->id, 'qtd' => 1]],
        ]);
    }

    private function criarSchemaMinimo(): void
    {
        Schema::dropIfExists('estoque_transferencia_itens');
        Schema::dropIfExists('estoque_transferencias');
        Schema::dropIfExists('estoque_movimentacoes');
        Schema::dropIfExists('estoque_reservas');
        Schema::dropIfExists('product_estoque_saldos');
        Schema::dropIfExists('estoques');
        Schema::dropIfExists('products');
        Schema::dropIfExists('caixa_lancamentos');
        Schema::dropIfExists('empresa_user');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresas');

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('nome')->nullable();
            $table->string('fantasia')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('empresa_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('user_id');
        });

        Schema::create('estoques', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('codigo', 20);
            $table->string('nome', 120);
            $table->unsignedBigInteger('vendedor_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('descricao')->nullable();
            $table->string('unidade')->nullable();
            $table->decimal('estoque', 12, 3)->default(0);
            $table->decimal('preco_venda', 15, 4)->default(0);
            $table->boolean('ativo')->default(true);
            $table->boolean('controla_lote_validade')->default(false);
            $table->boolean('is_composicao')->default(false);
            $table->boolean('contr_est_grade')->default(false);
            $table->timestamps();
        });

        Schema::create('product_estoque_saldos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('estoque_id');
            $table->decimal('quantidade', 12, 3)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'estoque_id']);
        });

        Schema::create('estoque_reservas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('estoque_id')->nullable();
            $table->decimal('quantidade', 12, 3)->default(0);
            $table->string('status', 20)->default('ativa');
            $table->timestamps();
        });

        Schema::create('estoque_movimentacoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('produto_id');
            $table->unsignedBigInteger('estoque_id')->nullable();
            $table->dateTime('data_movimentacao');
            $table->string('tipo', 40);
            $table->decimal('quantidade', 12, 3);
            $table->decimal('saldo_anterior', 12, 3);
            $table->decimal('saldo_atual', 12, 3);
            $table->string('origem_tipo', 40)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->string('origem_numero', 40)->nullable();
            $table->string('doc_fiscal_tipo', 20)->nullable();
            $table->string('doc_fiscal_numero', 40)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('observacao', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('estoque_transferencias', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_origem_id');
            $table->unsignedBigInteger('empresa_destino_id');
            $table->unsignedBigInteger('estoque_origem_id');
            $table->unsignedBigInteger('estoque_destino_id');
            $table->string('numero', 30);
            $table->string('situacao', 30)->default('rascunho');
            $table->string('modo', 20)->default('interna');
            $table->date('data');
            $table->string('observacoes', 250)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamp('confirmado_em')->nullable();
            $table->unsignedBigInteger('confirmado_por')->nullable();
            $table->uuid('idempotencia_token')->nullable()->unique();
            $table->unsignedBigInteger('nfe_saida_id')->nullable();
            $table->unsignedBigInteger('nfe_entrada_id')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamp('recebido_em')->nullable();
            $table->timestamps();
            $table->unique(['empresa_origem_id', 'numero']);
        });

        Schema::create('estoque_transferencia_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('estoque_transferencia_id');
            $table->unsignedInteger('item');
            $table->unsignedBigInteger('product_id');
            $table->string('produto_codigo', 40)->nullable();
            $table->string('produto_descricao', 200)->nullable();
            $table->decimal('qtd', 12, 3);
            $table->decimal('qtd_disponivel_origem', 12, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('caixa_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->string('documento')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return array{0: Empresa, 1: Estoque, 2: Estoque, 3: User}
     */
    private function cenario(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => (string) random_int(1000, 9999),
            'nome' => 'EMPRESA TRF',
            'fantasia' => 'EMPRESA TRF',
            'ativo' => true,
        ]);
        $origem = Estoque::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => '1',
            'nome' => 'DEPOSITO A',
            'ativo' => true,
        ]);
        $destino = Estoque::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => '2',
            'nome' => 'DEPOSITO B',
            'ativo' => true,
        ]);
        $user = User::query()->create([
            'name' => 'Admin TRF',
            'email' => 'admintrf'.random_int(1000, 9999).'@test.local',
            'password' => bcrypt('x'),
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        return [$empresa, $origem, $destino, $user];
    }

    private function produto(string $codigo, float $quantidade, int $estoqueId): Product
    {
        $codigo .= random_int(10, 99);
        $product = Product::query()->create([
            'codigo' => $codigo,
            'descricao' => 'PRODUTO '.$codigo,
            'unidade' => 'UN',
            'estoque' => $quantidade,
            'preco_venda' => 1,
            'ativo' => true,
        ]);
        ProductEstoqueSaldo::query()->create([
            'product_id' => $product->id,
            'estoque_id' => $estoqueId,
            'quantidade' => $quantidade,
        ]);

        return $product;
    }

    private function saldo(int $productId, int $estoqueId): float
    {
        return (float) (ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->where('estoque_id', $estoqueId)
            ->value('quantidade') ?? 0);
    }

    private function entrar(User $user, int $empresaId): void
    {
        Auth::login($user);
        session(['erp_empresa_id' => $empresaId]);
        ErpContext::clearMemo();
    }
}
