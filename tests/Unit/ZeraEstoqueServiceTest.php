<?php

namespace Tests\Unit;

use App\Models\AjusteEstoque;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Support\Erp\ZeraEstoqueService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema mínimo (sem MigratesSqliteMemory): migrate :memory: completo
 * ainda falha em legado do projeto.
 */
class ZeraEstoqueServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaMinimo();
    }

    public function test_zera_um_produto(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->entrar($user, $empresa->id);
        $product = $this->produto('Z1', 10, $estoque->id, grupo: 'BEBIDAS', marca: 'COCA');

        $result = app(ZeraEstoqueService::class)->executar(
            (int) $empresa->id,
            (int) $estoque->id,
            [(int) $product->id],
            ZeraEstoqueService::MOTIVO_PADRAO,
            'tok-um',
        );

        $this->assertSame(1, $result['ajustados']);
        $this->assertEquals(0, $this->saldo($product->id, $estoque->id));
        $this->assertSame(1, AjusteEstoque::query()->where('product_id', $product->id)->count());
        $this->assertEquals(-10, (float) AjusteEstoque::query()->where('product_id', $product->id)->value('qtd_ajust'));
    }

    public function test_zera_um_grupo(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->entrar($user, $empresa->id);
        $a = $this->produto('G1', 5, $estoque->id, grupo: 'LIMPEZA', marca: 'X');
        $b = $this->produto('G2', 8, $estoque->id, grupo: 'LIMPEZA', marca: 'Y');
        $fora = $this->produto('G3', 3, $estoque->id, grupo: 'OUTRO', marca: 'Z');

        $service = app(ZeraEstoqueService::class);
        $ids = $service->idsFiltrados((int) $empresa->id, [
            'estoque_id' => (int) $estoque->id,
            'grupo' => 'LIMPEZA',
            'saldo' => 'todos',
        ]);
        $result = $service->executar((int) $empresa->id, (int) $estoque->id, $ids, 'Balanço grupo');

        $this->assertSame(2, $result['ajustados']);
        $this->assertEquals(0, $this->saldo($a->id, $estoque->id));
        $this->assertEquals(0, $this->saldo($b->id, $estoque->id));
        $this->assertEquals(3, $this->saldo($fora->id, $estoque->id));
    }

    public function test_zera_uma_marca(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->entrar($user, $empresa->id);
        $a = $this->produto('M1', 4, $estoque->id, grupo: 'A', marca: 'NESTLE');
        $b = $this->produto('M2', 6, $estoque->id, grupo: 'B', marca: 'NESTLE');
        $fora = $this->produto('M3', 9, $estoque->id, grupo: 'A', marca: 'OUTRA');

        $service = app(ZeraEstoqueService::class);
        $ids = $service->idsFiltrados((int) $empresa->id, [
            'estoque_id' => (int) $estoque->id,
            'marca' => 'NESTLE',
            'saldo' => 'todos',
        ]);
        $result = $service->executar((int) $empresa->id, (int) $estoque->id, $ids, 'Balanço marca');

        $this->assertSame(2, $result['ajustados']);
        $this->assertEquals(0, $this->saldo($a->id, $estoque->id));
        $this->assertEquals(0, $this->saldo($b->id, $estoque->id));
        $this->assertEquals(9, $this->saldo($fora->id, $estoque->id));
    }

    public function test_combina_grupo_e_marca(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->entrar($user, $empresa->id);
        $alvo = $this->produto('C1', 7, $estoque->id, grupo: 'FRIOS', marca: 'SADIA');
        $this->produto('C2', 7, $estoque->id, grupo: 'FRIOS', marca: 'OUTRA');
        $this->produto('C3', 7, $estoque->id, grupo: 'OUTRO', marca: 'SADIA');

        $service = app(ZeraEstoqueService::class);
        $ids = $service->idsFiltrados((int) $empresa->id, [
            'estoque_id' => (int) $estoque->id,
            'grupo' => 'FRIOS',
            'marca' => 'SADIA',
            'saldo' => 'todos',
        ]);

        $this->assertSame([(int) $alvo->id], $ids);
        $service->executar((int) $empresa->id, (int) $estoque->id, $ids, 'Balanço combo');
        $this->assertEquals(0, $this->saldo($alvo->id, $estoque->id));
    }

    public function test_zera_somente_selecionados(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->entrar($user, $empresa->id);
        $a = $this->produto('S1', 2, $estoque->id);
        $b = $this->produto('S2', 4, $estoque->id);
        $c = $this->produto('S3', 6, $estoque->id);

        app(ZeraEstoqueService::class)->executar(
            (int) $empresa->id,
            (int) $estoque->id,
            [(int) $a->id, (int) $c->id],
            'Seleção',
        );

        $this->assertEquals(0, $this->saldo($a->id, $estoque->id));
        $this->assertEquals(4, $this->saldo($b->id, $estoque->id));
        $this->assertEquals(0, $this->saldo($c->id, $estoque->id));
    }

    public function test_respeita_deposito(): void
    {
        [$empresa, $estoqueA, $user] = $this->cenario();
        $estoqueB = Estoque::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => '2',
            'nome' => 'DEPOSITO B',
            'ativo' => true,
        ]);
        $this->entrar($user, $empresa->id);

        $product = Product::query()->create([
            'codigo' => 'DEP'.random_int(10, 99),
            'descricao' => 'PRODUTO DEP',
            'unidade' => 'UN',
            'estoque' => 15,
            'preco_venda' => 1,
            'preco_custo' => 1,
            'ativo' => true,
            'controla_lote_validade' => false,
        ]);
        ProductEstoqueSaldo::query()->create([
            'product_id' => $product->id,
            'estoque_id' => $estoqueA->id,
            'quantidade' => 10,
        ]);
        ProductEstoqueSaldo::query()->create([
            'product_id' => $product->id,
            'estoque_id' => $estoqueB->id,
            'quantidade' => 5,
        ]);

        app(ZeraEstoqueService::class)->executar(
            (int) $empresa->id,
            (int) $estoqueA->id,
            [(int) $product->id],
            'Só A',
        );

        $this->assertEquals(0, $this->saldo($product->id, $estoqueA->id));
        $this->assertEquals(5, $this->saldo($product->id, $estoqueB->id));
    }

    public function test_trata_saldo_negativo(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->entrar($user, $empresa->id);
        $product = $this->produto('NEG', -3.5, $estoque->id);

        $result = app(ZeraEstoqueService::class)->executar(
            (int) $empresa->id,
            (int) $estoque->id,
            [(int) $product->id],
            'Corrige negativo',
        );

        $this->assertSame(1, $result['ajustados']);
        $this->assertEquals(0, $this->saldo($product->id, $estoque->id));
        $this->assertEquals(3.5, (float) AjusteEstoque::query()->where('product_id', $product->id)->value('qtd_ajust'));
    }

    public function test_isolamento_entre_empresas(): void
    {
        [$empresaA, $estoqueA, $user] = $this->cenario();
        $empresaB = Empresa::query()->create([
            'codigo' => 'B'.random_int(100, 999),
            'nome' => 'EMPRESA B',
            'fantasia' => 'EMPRESA B',
            'ativo' => true,
        ]);
        $estoqueB = Estoque::query()->create([
            'empresa_id' => $empresaB->id,
            'codigo' => '1',
            'nome' => 'DEP B',
            'ativo' => true,
        ]);

        $product = $this->produto('ISO', 8, $estoqueA->id);
        ProductEstoqueSaldo::query()->create([
            'product_id' => $product->id,
            'estoque_id' => $estoqueB->id,
            'quantidade' => 8,
        ]);

        $this->entrar($user, $empresaA->id);
        $service = app(ZeraEstoqueService::class);
        $service->executar((int) $empresaA->id, (int) $estoqueA->id, [(int) $product->id], 'Empresa A');

        $this->assertEquals(0, $this->saldo($product->id, $estoqueA->id));
        $this->assertEquals(8, $this->saldo($product->id, $estoqueB->id));

        $this->expectException(\DomainException::class);
        $service->executar((int) $empresaA->id, (int) $estoqueB->id, [(int) $product->id], 'Depósito alienígena');
    }

    public function test_registra_movimentacao_e_motivo(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->entrar($user, $empresa->id);
        $product = $this->produto('HIST', 12, $estoque->id, custo: 2.5);

        $service = app(ZeraEstoqueService::class);
        $resumo = $service->resumo((int) $empresa->id, (int) $estoque->id, [(int) $product->id]);
        $this->assertSame(1, $resumo['produtos']);
        $this->assertEquals(12.0, $resumo['unidades']);
        $this->assertEquals(30.0, $resumo['valor_estimado']);

        $service->executar(
            (int) $empresa->id,
            (int) $estoque->id,
            [(int) $product->id],
            'Balanço de Estoque',
            'tok-hist',
        );

        $mov = EstoqueMovimentacao::query()
            ->where('produto_id', $product->id)
            ->where('tipo', EstoqueMovimentacao::TIPO_AJUSTE)
            ->latest('id')
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(12, (float) $mov->saldo_anterior);
        $this->assertEquals(0, (float) $mov->saldo_atual);
        $this->assertSame('Balanço de Estoque', (string) $mov->observacao);
        $this->assertSame((int) $user->id, (int) $mov->usuario_id);
        $this->assertSame((int) $estoque->id, (int) $mov->estoque_id);
    }

    public function test_bloqueia_selecao_vazia_e_token_duplicado(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->entrar($user, $empresa->id);
        $product = $this->produto('DUP', 1, $estoque->id);
        $service = app(ZeraEstoqueService::class);

        try {
            $service->executar((int) $empresa->id, (int) $estoque->id, [], 'x');
            $this->fail('Seleção vazia deveria falhar.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Nenhum produto', $e->getMessage());
        }

        $service->executar((int) $empresa->id, (int) $estoque->id, [(int) $product->id], 'ok', 'tok-dup');

        try {
            $service->executar((int) $empresa->id, (int) $estoque->id, [(int) $product->id], 'ok', 'tok-dup');
            $this->fail('Token duplicado deveria falhar.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('já foi processada', $e->getMessage());
        }
    }

    public function test_consulta_nao_zera_estoque(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->entrar($user, $empresa->id);
        $product = $this->produto('QRY', 11, $estoque->id, grupo: 'X', marca: 'Y');

        $lista = app(ZeraEstoqueService::class)->listar((int) $empresa->id, [
            'estoque_id' => (int) $estoque->id,
            'grupo' => 'X',
            'marca' => 'Y',
            'saldo' => 'positivo',
        ]);

        $this->assertSame(1, $lista['total']);
        $this->assertEquals(11, $this->saldo($product->id, $estoque->id));
        $this->assertSame(0, AjusteEstoque::query()->count());
    }

    private function criarSchemaMinimo(): void
    {
        Schema::dropIfExists('ajustes_estoque');
        Schema::dropIfExists('estoque_movimentacoes');
        Schema::dropIfExists('product_estoque_saldos');
        Schema::dropIfExists('estoques');
        Schema::dropIfExists('products');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresas');

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('nome')->nullable();
            $table->string('fantasia')->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('param_geral_bloquear_estoque_negativo')->default(false);
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
            $table->string('grupo')->nullable();
            $table->string('marca')->nullable();
            $table->string('codigo_barras')->nullable();
            $table->string('referencia')->nullable();
            $table->unsignedBigInteger('ult_fornecedor_id')->nullable();
            $table->decimal('estoque', 12, 3)->default(0);
            $table->decimal('preco_venda', 15, 4)->default(0);
            $table->decimal('preco_custo', 15, 4)->default(0);
            $table->decimal('preco_compra', 15, 4)->default(0);
            $table->boolean('ativo')->default(true);
            $table->boolean('controla_lote_validade')->default(false);
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

        Schema::create('ajustes_estoque', function (Blueprint $table): void {
            $table->id();
            $table->date('data');
            $table->unsignedBigInteger('product_id');
            $table->decimal('qtd_ajust', 12, 3);
            $table->timestamps();
        });
    }

    /**
     * @return array{0: Empresa, 1: Estoque, 2: User}
     */
    private function cenario(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => (string) random_int(1000, 9999),
            'nome' => 'EMPRESA ZERA',
            'fantasia' => 'EMPRESA ZERA',
            'ativo' => true,
        ]);
        $estoque = Estoque::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => '1',
            'nome' => 'DEPOSITO',
            'ativo' => true,
        ]);
        $user = User::query()->create([
            'name' => 'Admin Zera',
            'email' => 'adminzera'.random_int(1000, 9999).'@test.local',
            'password' => bcrypt('x'),
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        return [$empresa, $estoque, $user];
    }

    private function produto(
        string $codigo,
        float $quantidade,
        int $estoqueId,
        string $grupo = '',
        string $marca = '',
        float $custo = 1.0,
    ): Product {
        $codigo .= random_int(10, 99);
        $product = Product::query()->create([
            'codigo' => $codigo,
            'descricao' => 'PRODUTO '.$codigo,
            'unidade' => 'UN',
            'grupo' => $grupo,
            'marca' => $marca,
            'estoque' => $quantidade,
            'preco_venda' => 1,
            'preco_custo' => $custo,
            'ativo' => true,
            'controla_lote_validade' => false,
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
        return round((float) app(ProductEstoqueSaldoService::class)->fisico($productId, $estoqueId), 3);
    }

    private function entrar(User $user, int $empresaId): void
    {
        Auth::login($user);
        session(['erp_empresa_id' => $empresaId]);
        ErpContext::clearMemo();
    }
}
