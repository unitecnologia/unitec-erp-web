<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_vendas_internas_tipos_venda')) {
                $table->string('param_vendas_internas_tipos_venda', 30)
                    ->default('orcamento_e_pedido');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_vendas_internas_tipos_venda')) {
                $table->dropColumn('param_vendas_internas_tipos_venda');
            }
        });
    }
};
