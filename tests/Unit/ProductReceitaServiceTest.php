<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\ProductReceita;
use App\Models\ProductReceitaItem;
use App\Support\Erp\Fabricacao\ProductReceitaCusto;
use App\Support\Erp\Fabricacao\ProductReceitaService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema mínimo SQLite (migrate :memory: completo quebra em migration legada).
 */
class ProductReceitaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('product_receita_itens');
        Schema::dropIfExists('product_receitas');
        Schema::dropIfExists('product_compositions');
        Schema::dropIfExists('products');

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
            $table->timestamps();
        });

        Schema::create('product_compositions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('component_product_id');
            $table->decimal('quantidade', 12, 3)->default(1);
            $table->decimal('preco', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->boolean('is_adicional')->default(false);
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
            $table->unique(['receita_id', 'component_product_id']);
        });
    }

    public function test_sync_grava_receita_e_itens_sem_alterar_precos_do_acabado(): void
    {
        $acabado = $this->product('PAO', [
            'preco_custo' => 1.00,
            'preco_compra' => 0.80,
            'preco_venda' => 5.00,
        ]);
        $farinha = $this->product('FARINHA', [
            'preco_custo' => 4.00,
            'preco_compra' => 3.50,
            'preco_venda' => 9.99,
            'unidade' => 'KG',
        ]);
        $sal = $this->product('SAL', [
            'preco_custo' => 0,
            'preco_compra' => 2.00,
            'preco_venda' => 4.00,
            'unidade' => 'KG',
        ]);

        $service = new ProductReceitaService();
        $receita = $service->sync($acabado, 100, [
            ['component_product_id' => (int) $farinha->id, 'quantidade' => 10],
            ['component_product_id' => (int) $sal->id, 'quantidade' => 0.2],
        ]);

        $this->assertNotNull($receita);
        $this->assertSame(100.0, (float) $receita->qtd_produzida);
        $this->assertCount(2, $receita->itens);

        $acabado->refresh();
        $this->assertSame(1.0, (float) $acabado->preco_custo);
        $this->assertSame(0.8, (float) $acabado->preco_compra);
        $this->assertSame(5.0, (float) $acabado->preco_venda);
    }

    public function test_estimar_custo_usa_custo_nunca_preco_venda(): void
    {
        $farinha = $this->product('FAR2', [
            'preco_custo' => 4.00,
            'preco_compra' => 3.00,
            'preco_venda' => 99.00,
        ]);
        $sal = $this->product('SAL2', [
            'preco_custo' => 0,
            'preco_compra' => 2.00,
            'preco_venda' => 50.00,
        ]);

        $estimativa = (new ProductReceitaService())->estimarCusto(100, [
            ['component_product_id' => (int) $farinha->id, 'quantidade' => 10],
            ['component_product_id' => (int) $sal->id, 'quantidade' => 0.5],
        ]);

        $this->assertSame(41.0, $estimativa['custo_total']);
        $this->assertSame(0.41, $estimativa['custo_unitario_produzido']);
        $this->assertSame(4.0, $estimativa['linhas'][0]['custo_unitario']);
        $this->assertSame(2.0, $estimativa['linhas'][1]['custo_unitario']);
        $this->assertSame(99.0, $estimativa['linhas'][0]['preco_venda_ignorado']);
    }

    public function test_custo_unitario_helper_ignora_venda(): void
    {
        $product = $this->product('CUSTO', [
            'preco_custo' => 0,
            'preco_compra' => 0,
            'preco_venda' => 80,
        ]);

        $this->assertSame(0.0, ProductReceitaCusto::unitario($product));
    }

    public function test_rejeita_servico_como_acabado(): void
    {
        $servico = $this->product('SRV', ['is_servico' => true]);
        $mp = $this->product('MP1', ['preco_custo' => 1]);

        $this->expectException(DomainException::class);
        (new ProductReceitaService())->sync($servico, 1, [
            ['component_product_id' => (int) $mp->id, 'quantidade' => 1],
        ]);
    }

    public function test_rejeita_ingrediente_servico(): void
    {
        $acabado = $this->product('AC1');
        $servico = $this->product('SRV2', ['is_servico' => true]);

        $this->expectException(DomainException::class);
        (new ProductReceitaService())->sync($acabado, 1, [
            ['component_product_id' => (int) $servico->id, 'quantidade' => 1],
        ]);
    }

    public function test_rejeita_ingrediente_inativo(): void
    {
        $acabado = $this->product('AC2');
        $inativo = $this->product('INA', ['ativo' => false, 'preco_custo' => 1]);

        $this->expectException(DomainException::class);
        (new ProductReceitaService())->sync($acabado, 1, [
            ['component_product_id' => (int) $inativo->id, 'quantidade' => 1],
        ]);
    }

    public function test_rejeita_ciclo_de_receitas(): void
    {
        $a = $this->product('CIC_A');
        $b = $this->product('CIC_B');
        $service = new ProductReceitaService();

        $service->sync($a, 1, [
            ['component_product_id' => (int) $b->id, 'quantidade' => 1],
        ]);

        $this->expectException(DomainException::class);
        $service->sync($b, 1, [
            ['component_product_id' => (int) $a->id, 'quantidade' => 1],
        ]);
    }

    public function test_rejeita_rendimento_invalido_e_limpa_sem_itens(): void
    {
        $acabado = $this->product('AC3');
        $mp = $this->product('MP3', ['preco_custo' => 1]);
        $service = new ProductReceitaService();

        $service->sync($acabado, 10, [
            ['component_product_id' => (int) $mp->id, 'quantidade' => 2],
        ]);

        $this->assertSame(1, ProductReceita::query()->where('product_id', $acabado->id)->count());

        try {
            $service->sync($acabado, 0, [
                ['component_product_id' => (int) $mp->id, 'quantidade' => 2],
            ]);
            $this->fail('Esperava DomainException para rendimento inválido.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('rendimento', mb_strtolower($e->getMessage()));
        }

        $service->sync($acabado, 10, []);
        $this->assertSame(0, ProductReceita::query()->where('product_id', $acabado->id)->count());
        $this->assertSame(0, ProductReceitaItem::query()->count());
    }

    public function test_composicao_de_venda_permanece_independente(): void
    {
        $kit = $this->product('KIT', ['is_composicao' => true, 'preco_venda' => 20]);
        $comp = $this->product('COMP', ['preco_custo' => 2, 'preco_venda' => 5]);

        ProductComposition::query()->create([
            'product_id' => $kit->id,
            'component_product_id' => $comp->id,
            'quantidade' => 1,
            'preco' => 5,
            'total' => 5,
            'is_adicional' => false,
        ]);

        (new ProductReceitaService())->sync($kit, 1, [
            ['component_product_id' => (int) $comp->id, 'quantidade' => 2],
        ]);

        $kit->refresh();
        $this->assertTrue((bool) $kit->is_composicao);
        $this->assertSame(1, $kit->compositions()->count());
        $this->assertNotNull($kit->receita);
        $this->assertSame(2.0, (float) $kit->receita->itens()->first()->quantidade);
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
        ], $extra));
    }
}
