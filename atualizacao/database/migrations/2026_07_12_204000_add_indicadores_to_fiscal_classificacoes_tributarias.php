<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_classificacoes_tributarias', function (Blueprint $table) {
            if (! Schema::hasColumn('fiscal_classificacoes_tributarias', 'ind_nfe')) {
                $table->boolean('ind_nfe')->nullable()->after('nome_reduzido');
            }
            if (! Schema::hasColumn('fiscal_classificacoes_tributarias', 'ind_nfce')) {
                $table->boolean('ind_nfce')->nullable()->after('ind_nfe');
            }
            if (! Schema::hasColumn('fiscal_classificacoes_tributarias', 'ind_nfse')) {
                $table->boolean('ind_nfse')->nullable()->after('ind_nfce');
            }
            if (! Schema::hasColumn('fiscal_classificacoes_tributarias', 'ind_cte')) {
                $table->boolean('ind_cte')->nullable()->after('ind_nfse');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_classificacoes_tributarias', function (Blueprint $table) {
            foreach (['ind_nfe', 'ind_nfce', 'ind_nfse', 'ind_cte'] as $column) {
                if (Schema::hasColumn('fiscal_classificacoes_tributarias', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
