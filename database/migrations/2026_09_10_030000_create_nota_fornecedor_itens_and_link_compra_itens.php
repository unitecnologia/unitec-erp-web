<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nota_fornecedor_itens')) {
            Schema::create('nota_fornecedor_itens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('nota_fornecedor_id')->constrained('notas_fornecedores')->cascadeOnDelete();
                $table->unsignedSmallInteger('n_item');
                $table->string('c_prod', 60)->nullable();
                $table->string('c_ean', 20)->nullable();
                $table->string('descricao', 255)->nullable();
                $table->string('ncm', 10)->nullable();
                $table->string('cfop', 10)->nullable();
                $table->string('unidade', 10)->nullable();
                $table->decimal('quantidade', 15, 4)->default(0);
                $table->decimal('valor_unitario', 15, 4)->default(0);
                $table->decimal('valor_total', 15, 2)->default(0);
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->json('fiscal_snapshot')->nullable();
                $table->boolean('fiscal_inconsistente')->default(false);
                $table->json('fiscal_snapshot_conflito')->nullable();
                $table->timestamp('fiscal_inconsistente_em')->nullable();
                $table->timestamps();

                $table->unique(['nota_fornecedor_id', 'n_item'], 'nota_fornecedor_itens_nota_nitem_unique');
                $table->index('product_id');
            });
        }

        if (Schema::hasTable('compra_itens') && ! Schema::hasColumn('compra_itens', 'nota_fornecedor_item_id')) {
            Schema::table('compra_itens', function (Blueprint $table) {
                $table->foreignId('nota_fornecedor_item_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('nota_fornecedor_itens')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('nfe_itens') && ! Schema::hasColumn('nfe_itens', 'p_red_bc_icms')) {
            Schema::table('nfe_itens', function (Blueprint $table) {
                $table->decimal('p_red_bc_icms', 7, 4)->default(0)->after('aliq_icms');
                $table->string('mod_bc_icms', 1)->nullable()->after('p_red_bc_icms');
            });
        } elseif (Schema::hasTable('nfe_itens') && ! Schema::hasColumn('nfe_itens', 'mod_bc_icms')) {
            Schema::table('nfe_itens', function (Blueprint $table) {
                $table->string('mod_bc_icms', 1)->nullable()->after('p_red_bc_icms');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nfe_itens')) {
            Schema::table('nfe_itens', function (Blueprint $table) {
                if (Schema::hasColumn('nfe_itens', 'mod_bc_icms')) {
                    $table->dropColumn('mod_bc_icms');
                }
                if (Schema::hasColumn('nfe_itens', 'p_red_bc_icms')) {
                    $table->dropColumn('p_red_bc_icms');
                }
            });
        }

        if (Schema::hasTable('compra_itens') && Schema::hasColumn('compra_itens', 'nota_fornecedor_item_id')) {
            Schema::table('compra_itens', function (Blueprint $table) {
                $table->dropConstrainedForeignId('nota_fornecedor_item_id');
            });
        }

        Schema::dropIfExists('nota_fornecedor_itens');
    }
};
