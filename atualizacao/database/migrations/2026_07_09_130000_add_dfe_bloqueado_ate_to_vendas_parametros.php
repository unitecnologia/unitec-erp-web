<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            $table->timestamp('dfe_bloqueado_ate')->nullable()->after('dfe_ultimo_nsu');
        });
    }

    public function down(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            $table->dropColumn('dfe_bloqueado_ate');
        });
    }
};
