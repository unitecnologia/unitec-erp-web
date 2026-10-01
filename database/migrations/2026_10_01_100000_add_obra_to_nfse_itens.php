<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfse_itens', function (Blueprint $table): void {
            $table->string('obra_insc_imob_fisc', 30)->nullable()->after('c_ind_op');
            $table->string('obra_tipo', 8)->nullable()->after('obra_insc_imob_fisc');
            $table->string('obra_c_obra', 30)->nullable()->after('obra_tipo');
            $table->string('obra_c_cib', 8)->nullable()->after('obra_c_obra');
            $table->string('obra_cep', 8)->nullable()->after('obra_c_cib');
            $table->string('obra_logradouro', 255)->nullable()->after('obra_cep');
            $table->string('obra_numero', 60)->nullable()->after('obra_logradouro');
            $table->string('obra_complemento', 156)->nullable()->after('obra_numero');
            $table->string('obra_bairro', 60)->nullable()->after('obra_complemento');
        });
    }

    public function down(): void
    {
        Schema::table('nfse_itens', function (Blueprint $table): void {
            $table->dropColumn([
                'obra_insc_imob_fisc',
                'obra_tipo',
                'obra_c_obra',
                'obra_c_cib',
                'obra_cep',
                'obra_logradouro',
                'obra_numero',
                'obra_complemento',
                'obra_bairro',
            ]);
        });
    }
};
