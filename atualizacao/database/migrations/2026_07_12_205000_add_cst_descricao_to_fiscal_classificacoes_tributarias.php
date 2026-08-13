<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_classificacoes_tributarias', function (Blueprint $table) {
            if (! Schema::hasColumn('fiscal_classificacoes_tributarias', 'cst_descricao')) {
                $table->string('cst_descricao', 120)->nullable()->after('cst_ibs_cbs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_classificacoes_tributarias', function (Blueprint $table) {
            if (Schema::hasColumn('fiscal_classificacoes_tributarias', 'cst_descricao')) {
                $table->dropColumn('cst_descricao');
            }
        });
    }
};
