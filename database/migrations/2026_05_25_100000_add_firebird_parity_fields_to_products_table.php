<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'ult_compra')) {
                $table->decimal('ult_compra', 12, 2)->default(0)->after('preco_compra');
            }

            if (! Schema::hasColumn('products', 'ult_compra_anterior')) {
                $table->decimal('ult_compra_anterior', 12, 2)->default(0)->after('ult_compra');
            }

            if (! Schema::hasColumn('products', 'e_medio')) {
                $table->decimal('e_medio', 12, 3)->default(0)->after('preco_custo');
            }

            if (! Schema::hasColumn('products', 'preco_custo_anterior')) {
                $table->decimal('preco_custo_anterior', 12, 2)->default(0)->after('e_medio');
            }

            if (! Schema::hasColumn('products', 'preco_venda_prazo')) {
                $table->decimal('preco_venda_prazo', 12, 2)->default(0)->after('preco_venda');
            }

            if (! Schema::hasColumn('products', 'preco_venda_anterior')) {
                $table->decimal('preco_venda_anterior', 12, 2)->default(0)->after('preco_venda_prazo');
            }

            if (! Schema::hasColumn('products', 'produto_pesado')) {
                $table->boolean('produto_pesado')->default(false)->after('prefixo_balanca');
            }

            if (! Schema::hasColumn('products', 'principio_ativo_id')) {
                $table->unsignedInteger('principio_ativo_id')->nullable()->after('is_remedio');
            }

            if (! Schema::hasColumn('products', 'cod_enq_ipi')) {
                $table->string('cod_enq_ipi', 10)->nullable()->after('cst_ipi');
            }

            if (! Schema::hasColumn('products', 'mva_normal')) {
                $table->decimal('mva_normal', 8, 4)->default(0)->after('mva_pct');
            }

            if (! Schema::hasColumn('products', 'icms_diferido')) {
                $table->decimal('icms_diferido', 8, 4)->default(0)->after('reducao_base_pct');
            }

            if (! Schema::hasColumn('products', 'aliq_deson')) {
                $table->decimal('aliq_deson', 8, 4)->default(0)->after('icms_diferido');
            }

            if (! Schema::hasColumn('products', 'motivo_desoneracao')) {
                $table->unsignedSmallInteger('motivo_desoneracao')->nullable()->after('aliq_deson');
            }

            if (! Schema::hasColumn('products', 'tipo_tributacao')) {
                $table->string('tipo_tributacao', 10)->nullable()->after('motivo_desoneracao');
            }

            if (! Schema::hasColumn('products', 'tributacao_monofasica')) {
                $table->boolean('tributacao_monofasica')->default(false)->after('cod_beneficio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = [
                'preco_venda_prazo',
                'e_medio',
                'preco_custo_anterior',
                'preco_venda_anterior',
                'ult_compra',
                'ult_compra_anterior',
                'produto_pesado',
                'principio_ativo_id',
                'cod_enq_ipi',
                'tributacao_monofasica',
                'icms_diferido',
                'aliq_deson',
                'motivo_desoneracao',
                'tipo_tributacao',
                'mva_normal',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
