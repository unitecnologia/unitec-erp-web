<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\Erp\Fabricacao\ProductReceitaCycleGuard;
use App\Support\Erp\Fabricacao\ProductReceitaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema mínimo SQLite (sem MigratesSqliteMemory — migrate completo quebra em legado).
 */
class ProductReceitaCycleGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('product_receita_itens');
        Schema::dropIfExists('product_receitas');
        Schema::dropIfExists('products');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->nullable();
            $table->string('descricao', 180)->nullable();
            $table->string('unidade', 10)->nullable();
            $table->decimal('preco_compra', 12, 2)->default(0);
            $table->decimal('preco_custo', 12, 2)->default(0);
            $table->decimal('preco_venda', 12, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->boolean('is_servico')->default(false);
            $table->boolean('is_composicao')->default(false);
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
    }

    public function test_auto_referencia_e_ciclo_indireto(): void
    {
        $a = $this->product('G_A');
        $b = $this->product('G_B');
        $c = $this->product('G_C');

        $this->assertTrue(ProductReceitaCycleGuard::wouldCreateCycle((int) $a->id, (int) $a->id));

        $service = new ProductReceitaService();
        $service->sync($b, 1, [['component_product_id' => (int) $c->id, 'quantidade' => 1]]);
        $service->sync($a, 1, [['component_product_id' => (int) $b->id, 'quantidade' => 1]]);

        $this->assertTrue(ProductReceitaCycleGuard::wouldCreateCycle((int) $c->id, (int) $a->id));
        $this->assertFalse(ProductReceitaCycleGuard::wouldCreateCycle((int) $a->id, (int) $c->id));
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
            'ativo' => true,
            'is_servico' => false,
            'is_composicao' => false,
        ], $extra));
    }
}
