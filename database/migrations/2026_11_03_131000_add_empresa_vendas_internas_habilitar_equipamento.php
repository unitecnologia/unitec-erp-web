<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_vendas_internas_habilitar_equipamento')) {
                // Padrão true: empresas existentes mantêm o botão Equipamento.
                $table->boolean('param_vendas_internas_habilitar_equipamento')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_vendas_internas_habilitar_equipamento')) {
                $table->dropColumn('param_vendas_internas_habilitar_equipamento');
            }
        });
    }
};
