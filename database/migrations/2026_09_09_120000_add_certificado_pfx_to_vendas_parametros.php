<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendas_parametros', 'certificado_pfx')) {
                $table->longText('certificado_pfx')->nullable()->after('caminho_certificado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            if (Schema::hasColumn('vendas_parametros', 'certificado_pfx')) {
                $table->dropColumn('certificado_pfx');
            }
        });
    }
};
