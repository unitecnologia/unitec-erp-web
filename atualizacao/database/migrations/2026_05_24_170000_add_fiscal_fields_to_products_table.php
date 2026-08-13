<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('cfop_interno', 10)->default('5102')->after('ult_fornecedor_id');
            $table->unsignedTinyInteger('origem')->default(0)->after('cfop_interno');
            $table->string('cst_icms', 3)->default('041')->after('origem');
            $table->string('csosn', 3)->default('102')->after('cst_icms');
            $table->decimal('aliq_icms', 8, 2)->default(0)->after('csosn');
            $table->string('cfop_externo', 10)->default('6102')->after('aliq_icms');
            $table->string('cst_externo', 3)->default('041')->after('cfop_externo');
            $table->string('csosn_externo', 3)->default('102')->after('cst_externo');
            $table->decimal('aliq_icms_externo', 8, 2)->default(0)->after('csosn_externo');
            $table->string('cst_entrada', 3)->default('07')->after('aliq_icms_externo');
            $table->string('cst_saida', 3)->default('07')->after('cst_entrada');
            $table->decimal('aliq_pis', 8, 2)->default(0)->after('cst_saida');
            $table->decimal('aliq_cofins', 8, 2)->default(0)->after('aliq_pis');
            $table->string('cst_ipi', 3)->default('53')->after('aliq_cofins');
            $table->decimal('aliq_ipi', 8, 2)->default(0)->after('cst_ipi');
            $table->decimal('fcp_pct', 8, 2)->default(0)->after('aliq_ipi');
            $table->decimal('mva_pct', 8, 2)->default(0)->after('fcp_pct');
            $table->decimal('reducao_base_pct', 8, 2)->default(0)->after('mva_pct');
            $table->string('cod_beneficio')->nullable()->after('reducao_base_pct');
            $table->decimal('glp_pct', 8, 2)->default(0)->after('cod_beneficio');
            $table->decimal('gnn_pct', 8, 2)->default(0)->after('glp_pct');
            $table->decimal('gni_pct', 8, 2)->default(0)->after('gnn_pct');
            $table->decimal('peso_liq', 12, 3)->default(0)->after('gni_pct');
            $table->string('anp_code', 20)->nullable()->after('peso_liq');
            $table->decimal('issqn', 8, 2)->default(0)->after('anp_code');
            $table->string('prefixo_balanca', 10)->nullable()->after('issqn');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'cfop_interno',
                'origem',
                'cst_icms',
                'csosn',
                'aliq_icms',
                'cfop_externo',
                'cst_externo',
                'csosn_externo',
                'aliq_icms_externo',
                'cst_entrada',
                'cst_saida',
                'aliq_pis',
                'aliq_cofins',
                'cst_ipi',
                'aliq_ipi',
                'fcp_pct',
                'mva_pct',
                'reducao_base_pct',
                'cod_beneficio',
                'glp_pct',
                'gnn_pct',
                'gni_pct',
                'peso_liq',
                'anp_code',
                'issqn',
                'prefixo_balanca',
            ]);
        });
    }
};
