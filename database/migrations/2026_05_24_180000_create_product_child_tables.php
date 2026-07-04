<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_tables', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('descricao', 120);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('product_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('descricao', 120);
            $table->string('tamanho', 30)->nullable();
            $table->decimal('qtd', 12, 3)->default(0);
            $table->decimal('preco', 12, 2)->default(0);
            $table->decimal('preco_atacado', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['product_id', 'descricao']);
        });

        Schema::create('product_compositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantidade', 12, 3)->default(1);
            $table->decimal('preco', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'component_product_id'], 'unitec_prod_comp_unique');
        });

        Schema::create('product_price_table_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_table_id')->constrained()->cascadeOnDelete();
            $table->decimal('valor', 12, 2)->default(0);
            $table->decimal('fator', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'price_table_id'], 'unitec_prod_price_tbl_unique');
        });

        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('ultimo_preco', 12, 2);
            $table->date('registrado_em');
            $table->string('usuario', 120)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'registrado_em']);
        });

        if (Schema::hasTable('price_tables') && DB::table('price_tables')->count() === 0) {
            DB::table('price_tables')->insert([
                [
                    'codigo' => '1',
                    'descricao' => 'PADRAO',
                    'ativo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'codigo' => '2',
                    'descricao' => 'ATACADO',
                    'ativo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
        Schema::dropIfExists('product_price_table_items');
        Schema::dropIfExists('product_compositions');
        Schema::dropIfExists('product_grades');
        Schema::dropIfExists('price_tables');
    }
};
