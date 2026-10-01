<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_monitor_vendas_imp_valor_liquido')) {
                $table->boolean('param_monitor_vendas_imp_valor_liquido')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_monitor_vendas_imp_valor_liquido')) {
                $table->dropColumn('param_monitor_vendas_imp_valor_liquido');
            }
        });
    }
};
