<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_geral_perguntar_replicar_preco_filiais')) {
                $table->boolean('param_geral_perguntar_replicar_preco_filiais')->default(false);
            }
        });

        if (! Schema::hasTable('product_empresa_precos')) {
            Schema::create('product_empresa_precos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->decimal('preco_compra', 12, 2)->default(0);
                $table->decimal('pct_custos', 12, 2)->default(0);
                $table->decimal('preco_custo', 12, 2)->default(0);
                $table->decimal('pct_lucro', 12, 2)->default(0);
                $table->decimal('preco_venda', 12, 2)->default(0);
                $table->decimal('preco_atacado', 12, 2)->default(0);
                $table->decimal('preco_especial', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'empresa_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_empresa_precos');

        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'param_geral_perguntar_replicar_preco_filiais')) {
                $table->dropColumn('param_geral_perguntar_replicar_preco_filiais');
            }
        });
    }
};
