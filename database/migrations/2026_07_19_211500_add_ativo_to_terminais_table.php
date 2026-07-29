<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terminal ativo = liberado para carga/retorno do PDV offline (sem token).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('terminais')) {
            return;
        }

        Schema::table('terminais', function (Blueprint $table): void {
            if (! Schema::hasColumn('terminais', 'ativo')) {
                $table->boolean('ativo')->default(true)->after('pdv');
                $table->index(['empresa_id', 'ativo']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('terminais') || ! Schema::hasColumn('terminais', 'ativo')) {
            return;
        }

        Schema::table('terminais', function (Blueprint $table): void {
            $table->dropIndex(['empresa_id', 'ativo']);
            $table->dropColumn('ativo');
        });
    }
};
