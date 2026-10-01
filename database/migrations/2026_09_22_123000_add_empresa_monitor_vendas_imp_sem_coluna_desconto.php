<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_monitor_vendas_imp_sem_coluna_desconto')) {
                $table->boolean('param_monitor_vendas_imp_sem_coluna_desconto')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_monitor_vendas_imp_sem_coluna_desconto')) {
                $table->dropColumn('param_monitor_vendas_imp_sem_coluna_desconto');
            }
        });
    }
};
