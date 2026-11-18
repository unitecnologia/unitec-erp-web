<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\Fabricacao;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Models\ProductReceita;
use App\Models\ProductReceitaItem;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\Fabricacao\FabricacaoException;
use App\Support\Erp\Fabricacao\FabricacaoService;
use App\Support\Erp\Fabricacao\ProductReceitaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fabricação Etapa 2 — schema mínimo SQLite.
 * lockForUpdate / concorrência real: validação plena requer MySQL (ver notas no final da classe).
 */
class FabricacaoServiceTest extends TestCase
{
    private Empresa $empresa;

    private Estoque $estoque;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        ErpSchema::flush();

        foreach ([
            'estoque_movimentacoes',
            'product_estoque_saldos',
            'fabricacao_itens',
            'fabricacoes',
            'product_receita_itens',
            'product_receitas',
            'product_compositions',
            'products',
            'estoques',
            'empresas',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamps();
        });

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('nome')->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('param_geral_bloquear_estoque_negativo')->default(false);
            $table->timestamps();
        });

        Schema::create('estoques', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->nullable();
            $table->string('descricao', 180)->nullable();
            $table->string('unidade', 10)->nullable();
            $table->decimal('preco_compra', 12, 2)->default(0);
            $table->decimal('preco_custo', 12, 2)->default(0);
            $table->decimal('preco_venda', 12, 2)->default(0);
            $table->decimal('estoque', 12, 3)->default(0);
            $table->boolean('ativo')->default(true);
            $table->boolean('is_servico')->default(false);
            $table->boolean('is_composicao')->default(false);
            $table->boolean('usa_imei')->default(false);
            $table->boolean('is_grade')->default(false);
            $table->boolean('contr_est_grade')->default(false);
            $table->boolean('controla_lote_validade')->default(false);
            $table->timestamps();
        });

        Schema::create('product_receitas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();
            $table->decimal('qtd_produzida', 12, 3)->default(1);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('product_receita_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('receita_id');
            $table->unsignedBigInteger('component_product_id');
            $table->decimal('quantidade', 12, 3)->default(1);
            $table->timestamps();
        });

        Schema::create('fabricacoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('numero', 20);
            $table->dateTime('data_hora');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantidade', 12, 3);
            $table->unsignedBigInteger('estoque_id');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->decimal('custo_total', 14, 2)->default(0);
            $table->decimal('custo_unitario', 14, 2)->default(0);
            $table->string('status', 20)->default('finalizada');
            $table->string('idempotencia_token', 64)->unique();
            $table->decimal('rendimento_receita', 12, 3)->nullable();
            $table->decimal('fator', 18, 8)->nullable();
            $table->string('observacao', 500)->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'numero']);
        });

        Schema::create('fabricacao_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fabricacao_id');
            $table->unsignedBigInteger('component_product_id');
            $table->string('produto_codigo', 40)->nullable();
            $table->string('produto_descricao', 180)->nullable();
            $table->string('unidade', 10)->nullable();
            $table->decimal('quantidade_consumida', 12, 3);
            $table->decimal('custo_unitario', 14, 2)->default(0);
            $table->decimal('custo_total', 14, 2)->default(0);
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
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('observacao', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        ErpSchema::flush();

        $this->empresa = Empresa::query()->create([
            'nome' => 'EMP FAB',
            'ativo' => true,
            'param_geral_bloquear_estoque_negativo' => false,
        ]);
        $this->estoque = Estoque::query()->create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '1',
            'nome' => 'PRINCIPAL',
            'ativo' => true,
        ]);
        $this->user = User::query()->create([
            'name' => 'Admin Fab',
            'email' => 'fab-admin@test.local',
            'password' => 'x',
            'is_admin' => true,
            'ativo' => true,
            'empresa_id' => $this->empresa->id,
        ]);

        Auth::login($this->user);
        session(['erp_empresa_id' => (int) $this->empresa->id]);
        ErpContext::clearMemo();
    }

    public function test_fabricacao_simples_baixa_ingredientes_e_entra_acabado(): void
    {
        [$pao, $farinha, $sal] = $this->receitaPao(rendimento: 100, farinhaQtd: 10, salQtd: 0.2);
        $this->creditar($farinha, 50);
        $this->creditar($sal, 5); // @phpstan-ignore-line

        $fab = $this->service()->confirmar([
            'product_id' => (int) $pao->id,
            'quantidade' => 100,
            'idempotencia_token' => (string) Str::uuid(),
        ]);

        $this->assertTrue($fab->isFinalizada());
        $this->assertSame(100.0, (float) $fab->quantidade);
        $this->assertCount(2, $fab->itens);
        $this->assertEqualsWithDelta(40.0, (float) $farinha->fresh()->estoque, 0.001);
        $this->assertEqualsWithDelta(4.8, (float) $sal->fresh()->estoque, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $pao->fresh()->estoque, 0.001);

        // 10*4 + 0.2*2 = 40.4
        $this->assertEqualsWithDelta(40.4, (float) $fab->custo_total, 0.001);
        $this->assertEqualsWithDelta(0.4, (float) $fab->custo_unitario, 0.01);

        $movs = EstoqueMovimentacao::query()->where('origem_tipo', 'fabricacao')->where('origem_id', $fab->id)->get();
        $this->assertCount(3, $movs);
        $this->assertTrue($movs->every(fn ($m) => $m->tipo === EstoqueMovimentacao::TIPO_FABRICACAO));
    }

    public function test_rendimento_diferente_escala_consumo(): void
    {
        [$pao, $farinha] = $this->receitaPao(rendimento: 50, farinhaQtd: 5, salQtd: 0);
        $this->creditar($farinha, 100);

        $fab = $this->service()->confirmar([
            'product_id' => (int) $pao->id,
            'quantidade' => 100, // fator 2
            'idempotencia_token' => (string) Str::uuid(),
        ]);

        $this->assertEqualsWithDelta(2.0, (float) $fab->fator, 0.0001);
        $this->assertEqualsWithDelta(90.0, (float) $farinha->fresh()->estoque, 0.001); // 100 - 10
        $this->assertEqualsWithDelta(100.0, (float) $pao->fresh()->estoque, 0.001);
    }

    public function test_saldo_insuficiente_bloqueia_mesmo_com_negativo_permitido(): void
    {
        [$pao, $farinha] = $this->receitaPao(salQtd: 0);
        $this->creditar($farinha, 1);

        $this->expectException(FabricacaoException::class);
        $this->expectExceptionMessage('insuficiente');

        $this->service()->confirmar([
            'product_id' => (int) $pao->id,
            'quantidade' => 100,
            'idempotencia_token' => (string) Str::uuid(),
        ]);
    }

    public function test_ingrediente_sem_custo_bloqueia(): void
    {
        $pao = $this->product('PAO-ZC');
        $mp = $this->product('MP-ZC', ['preco_custo' => 0, 'preco_compra' => 0, 'preco_venda' => 99]);
        $this->gravarReceita($pao, 1, [[(int) $mp->id, 1]]);
        $this->creditar($mp, 10);

        $this->expectException(FabricacaoException::class);
        $this->expectExceptionMessage('custo');

        $this->service()->confirmar([
            'product_id' => (int) $pao->id,
            'quantidade' => 1,
            'idempotencia_token' => (string) Str::uuid(),
        ]);
    }

    public function test_produto_sem_receita_bloqueia(): void
    {
        $pao = $this->product('SEM-REC');

        $this->expectException(FabricacaoException::class);
        $this->expectExceptionMessage('receita');

        $this->service()->confirmar([
            'product_id' => (int) $pao->id,
            'quantidade' => 1,
            'idempotencia_token' => (string) Str::uuid(),
        ]);
    }

    public function test_produto_inativo_ou_servico_ou_composicao_bloqueia(): void
    {
        $mp = $this->product('MP-X', ['preco_custo' => 1]);
        $this->creditar($mp, 10);

        $inativo = $this->product('INA', ['ativo' => true]);
        $this->gravarReceita($inativo, 1, [[(int) $mp->id, 1]]);
        $inativo->update(['ativo' => false]);
        try {
            $this->service()->confirmar([
                'product_id' => (int) $inativo->id,
                'quantidade' => 1,
                'idempotencia_token' => (string) Str::uuid(),
            ]);
            $this->fail('inativo');
        } catch (FabricacaoException $e) {
            $this->assertStringContainsString('inativo', mb_strtolower($e->getMessage()));
        }

        $servico = $this->product('SRV', ['is_servico' => false]);
        $this->gravarReceita($servico, 1, [[(int) $mp->id, 1]]);
        $servico->update(['is_servico' => true]);
        try {
            $this->service()->confirmar([
                'product_id' => (int) $servico->id,
                'quantidade' => 1,
                'idempotencia_token' => (string) Str::uuid(),
            ]);
            $this->fail('servico');
        } catch (FabricacaoException $e) {
            $this->assertStringContainsString('serviço', mb_strtolower($e->getMessage()));
        }

        $kit = $this->product('KIT', ['is_composicao' => false]);
        $this->gravarReceita($kit, 1, [[(int) $mp->id, 1]]);
        $kit->update(['is_composicao' => true]);
        $this->expectException(FabricacaoException::class);
        $this->expectExceptionMessage('composição');
        $this->service()->confirmar([
            'product_id' => (int) $kit->id,
            'quantidade' => 1,
            'idempotencia_token' => (string) Str::uuid(),
        ]);
    }

    public function test_empresa_ou_deposito_arbitrario_bloqueia(): void
    {
        [$pao, $farinha] = $this->receitaPao(salQtd: 0);
        $this->creditar($farinha, 100);

        $this->expectException(FabricacaoException::class);
        $this->expectExceptionMessage('Empresa informada');

        $this->service()->confirmar([
            'product_id' => (int) $pao->id,
            'quantidade' => 10,
            'empresa_id' => 999999,
            'idempotencia_token' => (string) Str::uuid(),
        ]);
    }

    public function test_idempotencia_mesmo_token_nao_duplica(): void
    {
        [$pao, $farinha] = $this->receitaPao(salQtd: 0);
        $this->creditar($farinha, 100);
        $token = (string) Str::uuid();

        $a = $this->service()->confirmar([
            'product_id' => (int) $pao->id,
            'quantidade' => 10,
            'idempotencia_token' => $token,
        ]);
        $b = $this->service()->confirmar([
            'product_id' => (int) $pao->id,
            'quantidade' => 10,
            'idempotencia_token' => $token,
        ]);

        $this->assertSame((int) $a->id, (int) $b->id);
        $this->assertSame(1, Fabricacao::query()->where('idempotencia_token', $token)->count());
        $this->assertEqualsWithDelta(10.0, (float) $pao->fresh()->estoque, 0.001);
    }

    public function test_falha_nao_persiste_fabricacao_parcial(): void
    {
        [$pao] = $this->receitaPao(salQtd: 0);
        // sem crédito -> falha
        try {
            $this->service()->confirmar([
                'product_id' => (int) $pao->id,
                'quantidade' => 10,
                'idempotencia_token' => (string) Str::uuid(),
            ]);
            $this->fail('deveria falhar');
        } catch (FabricacaoException) {
            // ok
        }

        $this->assertSame(0, Fabricacao::query()->count());
        $this->assertSame(0, EstoqueMovimentacao::query()->count());
        $this->assertEqualsWithDelta(0.0, (float) $pao->fresh()->estoque, 0.001);
    }

    public function test_nao_altera_precos_do_cadastro(): void
    {
        [$pao, $farinha] = $this->receitaPao(salQtd: 0);
        $this->creditar($farinha, 100);
        $pao->update(['preco_venda' => 7.5, 'preco_custo' => 1.1, 'preco_compra' => 0.9]);
        $farinha->update(['preco_venda' => 12.3]);

        $this->service()->confirmar([
            'product_id' => (int) $pao->id,
            'quantidade' => 10,
            'idempotencia_token' => (string) Str::uuid(),
        ]);

        $this->assertSame(7.5, (float) $pao->fresh()->preco_venda);
        $this->assertSame(1.1, (float) $pao->fresh()->preco_custo);
        $this->assertSame(0.9, (float) $pao->fresh()->preco_compra);
        $this->assertSame(12.3, (float) $farinha->fresh()->preco_venda);
    }

    public function test_preserva_saldo_de_outra_empresa(): void
    {
        [$pao, $farinha] = $this->receitaPao(salQtd: 0);
        $this->creditar($farinha, 100);

        $outra = Empresa::query()->create(['nome' => 'OUTRA', 'ativo' => true]);
        $depOutra = Estoque::query()->create([
            'empresa_id' => $outra->id,
            'codigo' => '1',
            'nome' => 'OUTRA',
            'ativo' => true,
        ]);
        ProductEstoqueSaldo::query()->create([
            'product_id' => $farinha->id,
            'estoque_id' => $depOutra->id,
            'quantidade' => 77,
        ]);
        // espelho global já tem 100; não mexer no da outra

        $this->service()->confirmar([
            'product_id' => (int) $pao->id,
            'quantidade' => 10,
            'idempotencia_token' => (string) Str::uuid(),
        ]);

        $saldoOutra = (float) ProductEstoqueSaldo::query()
            ->where('product_id', $farinha->id)
            ->where('estoque_id', $depOutra->id)
            ->value('quantidade');
        $this->assertEqualsWithDelta(77.0, $saldoOutra, 0.001);
    }

    public function test_imei_e_grade_bloqueados(): void
    {
        $mp = $this->product('MP-G', ['preco_custo' => 1]);
        $this->creditar($mp, 10);

        $imei = $this->product('IMEI');
        $this->gravarReceita($imei, 1, [[(int) $mp->id, 1]]);
        $imei->update(['usa_imei' => true]);
        try {
            $this->service()->confirmar([
                'product_id' => (int) $imei->id,
                'quantidade' => 1,
                'idempotencia_token' => (string) Str::uuid(),
            ]);
            $this->fail('imei');
        } catch (FabricacaoException $e) {
            $this->assertStringContainsString('IMEI', $e->getMessage());
        }

        $grade = $this->product('GRADE');
        $this->gravarReceita($grade, 1, [[(int) $mp->id, 1]]);
        $grade->update(['is_grade' => true, 'contr_est_grade' => true]);
        $this->expectException(FabricacaoException::class);
        $this->expectExceptionMessage('grade');
        $this->service()->confirmar([
            'product_id' => (int) $grade->id,
            'quantidade' => 1,
            'idempotencia_token' => (string) Str::uuid(),
        ]);
    }

    public function test_etapa1_receita_continua_funcionando(): void
    {
        $acabado = $this->product('ET1');
        $mp = $this->product('ET1-MP', ['preco_custo' => 3]);

        $receita = (new ProductReceitaService())->sync($acabado, 10, [
            ['component_product_id' => (int) $mp->id, 'quantidade' => 2],
        ]);

        $this->assertNotNull($receita);
        $this->assertSame(1, ProductReceitaItem::query()->count());
    }

    public function test_quantidade_fracionaria_em_un_bloqueia(): void
    {
        [$pao, $farinha] = $this->receitaPao(salQtd: 0);
        $pao->update(['unidade' => 'UN']);
        $this->creditar($farinha, 100);

        $this->expectException(FabricacaoException::class);
        $this->expectExceptionMessage('inteira');

        $this->service()->confirmar([
            'product_id' => (int) $pao->id,
            'quantidade' => 1.5,
            'idempotencia_token' => (string) Str::uuid(),
        ]);
    }

    /**
     * LIMITAÇÃO: concorrência com lockForUpdate não é comprovada em SQLite desta suíte.
     * Em MySQL/MariaDB, o lock no token + unique impedem duplicidade; validar manualmente em DEV se necessário.
     */
    public function test_documenta_limitacao_concorrencia_sqlite(): void
    {
        $this->assertTrue(true);
    }

    private function service(): FabricacaoService
    {
        return new FabricacaoService();
    }

    /**
     * @return array{0: Product, 1: Product, 2?: Product}
     */
    private function receitaPao(float $rendimento = 100, float $farinhaQtd = 10, float $salQtd = 0.2): array
    {
        $pao = $this->product('PAO', ['unidade' => 'UN', 'preco_venda' => 5]);
        $farinha = $this->product('FAR', [
            'unidade' => 'KG',
            'preco_custo' => 4,
            'preco_compra' => 3.5,
            'preco_venda' => 9,
        ]);
        $itens = [[(int) $farinha->id, $farinhaQtd]];
        $sal = null;
        if ($salQtd > 0) {
            $sal = $this->product('SAL', [
                'unidade' => 'KG',
                'preco_custo' => 0,
                'preco_compra' => 2,
                'preco_venda' => 4,
            ]);
            $itens[] = [(int) $sal->id, $salQtd];
        }
        $this->gravarReceita($pao, $rendimento, $itens);

        return $sal ? [$pao, $farinha, $sal] : [$pao, $farinha];
    }

    /**
     * @param  list<array{0: int, 1: float}>  $itens
     */
    private function gravarReceita(Product $acabado, float $rendimento, array $itens): void
    {
        $payload = [];
        foreach ($itens as [$id, $qtd]) {
            $payload[] = ['component_product_id' => $id, 'quantidade' => $qtd];
        }
        (new ProductReceitaService())->sync($acabado, $rendimento, $payload);
    }

    private function creditar(Product $product, float $qtd): void
    {
        $product->estoque = round((float) $product->estoque + $qtd, 3);
        $product->save();

        ProductEstoqueSaldo::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'estoque_id' => $this->estoque->id,
            ],
            [
                'quantidade' => (float) $product->estoque,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function product(string $codigo, array $extra = []): Product
    {
        return Product::query()->create(array_merge([
            'codigo' => $codigo,
            'descricao' => 'PROD '.$codigo,
            'unidade' => 'UN',
            'preco_compra' => 0,
            'preco_custo' => 0,
            'preco_venda' => 0,
            'estoque' => 0,
            'ativo' => true,
            'is_servico' => false,
            'is_composicao' => false,
            'usa_imei' => false,
            'is_grade' => false,
            'contr_est_grade' => false,
            'controla_lote_validade' => false,
        ], $extra));
    }
}
