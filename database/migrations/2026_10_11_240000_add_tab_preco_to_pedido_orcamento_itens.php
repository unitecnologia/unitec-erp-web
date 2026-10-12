<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persiste a escolha de Tab. Preço (nível) nos itens de pedido/orçamento do app FV.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pedido_itens')) {
            Schema::table('pedido_itens', function (Blueprint $table): void {
                if (! Schema::hasColumn('pedido_itens', 'price_table_id')) {
                    $table->unsignedBigInteger('price_table_id')->nullable()->after('product_grade_id');
                }
                if (! Schema::hasColumn('pedido_itens', 'tabela_nivel')) {
                    $table->string('tabela_nivel', 20)->nullable()->after('price_table_id');
                }
            });
        }

        if (Schema::hasTable('orcamento_itens')) {
            Schema::table('orcamento_itens', function (Blueprint $table): void {
                if (! Schema::hasColumn('orcamento_itens', 'price_table_id')) {
                    $table->unsignedBigInteger('price_table_id')->nullable()->after('product_grade_id');
                }
                if (! Schema::hasColumn('orcamento_itens', 'tabela_nivel')) {
                    $table->string('tabela_nivel', 20)->nullable()->after('price_table_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pedido_itens')) {
            Schema::table('pedido_itens', function (Blueprint $table): void {
                if (Schema::hasColumn('pedido_itens', 'tabela_nivel')) {
                    $table->dropColumn('tabela_nivel');
                }
                if (Schema::hasColumn('pedido_itens', 'price_table_id')) {
                    $table->dropColumn('price_table_id');
                }
            });
        }

        if (Schema::hasTable('orcamento_itens')) {
            Schema::table('orcamento_itens', function (Blueprint $table): void {
                if (Schema::hasColumn('orcamento_itens', 'tabela_nivel')) {
                    $table->dropColumn('tabela_nivel');
                }
                if (Schema::hasColumn('orcamento_itens', 'price_table_id')) {
                    $table->dropColumn('price_table_id');
                }
            });
        }
    }
};
