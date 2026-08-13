<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'tem_info_nutricional')) {
                $table->boolean('tem_info_nutricional')->default(false)->after('produto_pesado');
            }
            if (! Schema::hasColumn('products', 'nutri_porcao_qtd')) {
                $table->unsignedSmallInteger('nutri_porcao_qtd')->default(0)->after('tem_info_nutricional');
            }
            if (! Schema::hasColumn('products', 'nutri_porcao_unidade')) {
                $table->string('nutri_porcao_unidade', 1)->default('0')->after('nutri_porcao_qtd');
            }
            if (! Schema::hasColumn('products', 'nutri_medida_inteiro')) {
                $table->unsignedTinyInteger('nutri_medida_inteiro')->default(0)->after('nutri_porcao_unidade');
            }
            if (! Schema::hasColumn('products', 'nutri_medida_fracao')) {
                $table->string('nutri_medida_fracao', 1)->default('0')->after('nutri_medida_inteiro');
            }
            if (! Schema::hasColumn('products', 'nutri_medida_tipo')) {
                $table->string('nutri_medida_tipo', 2)->default('00')->after('nutri_medida_fracao');
            }
            if (! Schema::hasColumn('products', 'nutri_valor_energetico')) {
                $table->decimal('nutri_valor_energetico', 8, 1)->default(0)->after('nutri_medida_tipo');
            }
            if (! Schema::hasColumn('products', 'nutri_carboidratos')) {
                $table->decimal('nutri_carboidratos', 8, 1)->default(0)->after('nutri_valor_energetico');
            }
            if (! Schema::hasColumn('products', 'nutri_proteinas')) {
                $table->decimal('nutri_proteinas', 6, 1)->default(0)->after('nutri_carboidratos');
            }
            if (! Schema::hasColumn('products', 'nutri_gorduras_totais')) {
                $table->decimal('nutri_gorduras_totais', 6, 1)->default(0)->after('nutri_proteinas');
            }
            if (! Schema::hasColumn('products', 'nutri_gorduras_saturadas')) {
                $table->decimal('nutri_gorduras_saturadas', 6, 1)->default(0)->after('nutri_gorduras_totais');
            }
            if (! Schema::hasColumn('products', 'nutri_gorduras_trans')) {
                $table->decimal('nutri_gorduras_trans', 6, 1)->default(0)->after('nutri_gorduras_saturadas');
            }
            if (! Schema::hasColumn('products', 'nutri_fibra')) {
                $table->decimal('nutri_fibra', 6, 1)->default(0)->after('nutri_gorduras_trans');
            }
            if (! Schema::hasColumn('products', 'nutri_sodio')) {
                $table->decimal('nutri_sodio', 8, 1)->default(0)->after('nutri_fibra');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $cols = [
                'tem_info_nutricional',
                'nutri_porcao_qtd',
                'nutri_porcao_unidade',
                'nutri_medida_inteiro',
                'nutri_medida_fracao',
                'nutri_medida_tipo',
                'nutri_valor_energetico',
                'nutri_carboidratos',
                'nutri_proteinas',
                'nutri_gorduras_totais',
                'nutri_gorduras_saturadas',
                'nutri_gorduras_trans',
                'nutri_fibra',
                'nutri_sodio',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
