<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operacoes_fiscais', function (Blueprint $table): void {
            $table->unsignedInteger('cfop_venda_mercadoria_estadual')->nullable()->after('cfop_financeiro_interestadual');
            $table->unsignedInteger('cfop_venda_mercadoria_interestadual')->nullable()->after('cfop_venda_mercadoria_estadual');
        });
    }

    public function down(): void
    {
        Schema::table('operacoes_fiscais', function (Blueprint $table): void {
            $table->dropColumn([
                'cfop_venda_mercadoria_estadual',
                'cfop_venda_mercadoria_interestadual',
            ]);
        });
    }
};
