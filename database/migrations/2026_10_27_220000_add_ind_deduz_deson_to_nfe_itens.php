<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NT 2023.004 v1.11 — indDeduzDeson (N28b):
 * 0 = vICMSDeson NÃO deduz de vProd / vNF
 * 1 = vICMSDeson deduz de vProd / vNF
 * null = não informado (trata como não deduz, Exceção 4 da W16-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_itens')) {
            return;
        }

        Schema::table('nfe_itens', function (Blueprint $table): void {
            if (! Schema::hasColumn('nfe_itens', 'ind_deduz_deson')) {
                $table->unsignedTinyInteger('ind_deduz_deson')->nullable()->after('valor_desoneracao');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('nfe_itens') || ! Schema::hasColumn('nfe_itens', 'ind_deduz_deson')) {
            return;
        }

        Schema::table('nfe_itens', function (Blueprint $table): void {
            $table->dropColumn('ind_deduz_deson');
        });
    }
};
