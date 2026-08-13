<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_meta_vendas_mensal')) {
                $table->decimal('param_meta_vendas_mensal', 15, 2)->default(0);
            }

            if (! Schema::hasColumn('empresas', 'param_meta_faturamento_mensal')) {
                $table->decimal('param_meta_faturamento_mensal', 15, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_meta_vendas_mensal')) {
                $table->dropColumn('param_meta_vendas_mensal');
            }

            if (Schema::hasColumn('empresas', 'param_meta_faturamento_mensal')) {
                $table->dropColumn('param_meta_faturamento_mensal');
            }
        });
    }
};
