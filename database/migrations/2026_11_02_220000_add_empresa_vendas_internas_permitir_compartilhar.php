<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_vendas_internas_permitir_compartilhar')) {
                // Padrão true: empresas existentes mantêm o compartilhamento atual.
                $table->boolean('param_vendas_internas_permitir_compartilhar')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_vendas_internas_permitir_compartilhar')) {
                $table->dropColumn('param_vendas_internas_permitir_compartilhar');
            }
        });
    }
};
