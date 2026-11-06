<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transportadora / volumes / frete da OS (mesmo contrato JSON da TV / NF-e).
 * Nullable: OS sem transporte continua válida. Não altera dados históricos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordens_servico')) {
            return;
        }

        Schema::table('ordens_servico', function (Blueprint $table): void {
            if (! Schema::hasColumn('ordens_servico', 'transporte')) {
                $table->json('transporte')->nullable()->after('faturamento_pagamentos');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordens_servico')) {
            return;
        }

        Schema::table('ordens_servico', function (Blueprint $table): void {
            if (Schema::hasColumn('ordens_servico', 'transporte')) {
                $table->dropColumn('transporte');
            }
        });
    }
};
