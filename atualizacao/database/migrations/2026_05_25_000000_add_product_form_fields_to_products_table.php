<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('codigo_barras_caixa')->nullable()->after('codigo_barras');
            $table->string('tipo_produto', 10)->default('00')->after('descricao');
            $table->string('marca')->nullable()->after('tipo_produto');
            $table->string('unidade', 10)->default('UN')->after('grupo');
            $table->decimal('preco_compra', 12, 2)->default(0)->after('unidade');
            $table->decimal('pct_custos', 8, 2)->default(0)->after('preco_compra');
            $table->decimal('preco_custo', 12, 2)->default(0)->after('pct_custos');
            $table->decimal('pct_lucro', 8, 2)->default(0)->after('preco_custo');
            $table->decimal('qtd_atacado', 12, 3)->default(0)->after('preco_venda');
            $table->decimal('preco_atacado', 12, 2)->default(0)->after('qtd_atacado');
            $table->decimal('comissao_pct', 8, 2)->default(0)->after('preco_atacado');
            $table->decimal('desconto_pct', 8, 2)->default(0)->after('comissao_pct');
            $table->decimal('estoque_minimo', 12, 3)->default(1)->after('estoque');
            $table->decimal('estoque_inicial', 12, 3)->default(0)->after('estoque_minimo');
            $table->decimal('peso_kg', 12, 3)->default(0)->after('estoque_inicial');
            $table->string('ncm', 8)->default('00000000')->after('peso_kg');
            $table->string('ncm_descricao')->nullable()->after('ncm');
            $table->string('cest', 7)->nullable()->after('ncm_descricao');
            $table->boolean('is_fiscal')->default(true)->after('ativo');
            $table->boolean('paga_comissao')->default(false)->after('is_fiscal');
            $table->boolean('preco_variavel')->default(false)->after('paga_comissao');
            $table->boolean('is_composicao')->default(false)->after('preco_variavel');
            $table->boolean('is_servico')->default(false)->after('is_composicao');
            $table->boolean('is_grade')->default(false)->after('is_servico');
            $table->boolean('usa_tab_preco')->default(false)->after('is_grade');
            $table->boolean('is_combustivel')->default(false)->after('usa_tab_preco');
            $table->boolean('usa_imei')->default(false)->after('is_combustivel');
            $table->boolean('contr_est_grade')->default(false)->after('usa_imei');
            $table->boolean('mostrar_no_app')->default(true)->after('contr_est_grade');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'codigo_barras_caixa',
                'tipo_produto',
                'marca',
                'unidade',
                'preco_compra',
                'pct_custos',
                'preco_custo',
                'pct_lucro',
                'qtd_atacado',
                'preco_atacado',
                'comissao_pct',
                'desconto_pct',
                'estoque_minimo',
                'estoque_inicial',
                'peso_kg',
                'ncm',
                'ncm_descricao',
                'cest',
                'is_fiscal',
                'paga_comissao',
                'preco_variavel',
                'is_composicao',
                'is_servico',
                'is_grade',
                'usa_tab_preco',
                'is_combustivel',
                'usa_imei',
                'contr_est_grade',
                'mostrar_no_app',
            ]);
        });
    }
};
