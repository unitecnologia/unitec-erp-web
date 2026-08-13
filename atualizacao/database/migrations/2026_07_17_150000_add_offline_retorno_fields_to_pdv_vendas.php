<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos para o retorno de vendas do mini-PDV offline (Fase 3): `uuid` garante
 * idempotência da importação; os demais preservam a rastreabilidade da origem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_vendas', function (Blueprint $table): void {
            if (! Schema::hasColumn('pdv_vendas', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique();
            }

            if (! Schema::hasColumn('pdv_vendas', 'origem')) {
                $table->string('origem', 32)->nullable();
            }

            if (! Schema::hasColumn('pdv_vendas', 'terminal_offline')) {
                $table->string('terminal_offline', 60)->nullable();
            }

            if (! Schema::hasColumn('pdv_vendas', 'numero_offline')) {
                $table->unsignedBigInteger('numero_offline')->nullable();
            }

            if (! Schema::hasColumn('pdv_vendas', 'serie_offline')) {
                $table->string('serie_offline', 5)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pdv_vendas', function (Blueprint $table): void {
            foreach (['origem', 'terminal_offline', 'numero_offline', 'serie_offline'] as $col) {
                if (Schema::hasColumn('pdv_vendas', $col)) {
                    $table->dropColumn($col);
                }
            }

            if (Schema::hasColumn('pdv_vendas', 'uuid')) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            }
        });
    }
};
