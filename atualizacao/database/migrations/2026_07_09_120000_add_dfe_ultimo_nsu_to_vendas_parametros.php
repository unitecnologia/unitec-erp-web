<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            $table->string('dfe_ultimo_nsu', 15)->default('000000000000000')->after('numero_nfe');
        });
    }

    public function down(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            $table->dropColumn('dfe_ultimo_nsu');
        });
    }
};
