<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RH: vincula funcionário à empresa (inclusive sem login).
 * Listagens/operações filtram pela empresa logada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_funcionarios') || Schema::hasColumn('rh_funcionarios', 'empresa_id')) {
            return;
        }

        Schema::table('rh_funcionarios', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->after('id');
            $table->index('empresa_id', 'rh_funcionarios_empresa_id_index');
        });

        // Seed / legado sem mapa: amarra à empresa 1 quando existir.
        if (Schema::hasTable('empresas') && DB::table('empresas')->where('id', 1)->exists()) {
            DB::table('rh_funcionarios')
                ->whereNull('empresa_id')
                ->update(['empresa_id' => 1, 'updated_at' => now()]);
        }

        Schema::table('rh_funcionarios', function (Blueprint $table) {
            if (Schema::hasTable('empresas')) {
                $table->foreign('empresa_id', 'rh_funcionarios_empresa_id_foreign')
                    ->references('id')
                    ->on('empresas')
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rh_funcionarios') || ! Schema::hasColumn('rh_funcionarios', 'empresa_id')) {
            return;
        }

        Schema::table('rh_funcionarios', function (Blueprint $table) {
            try {
                $table->dropForeign('rh_funcionarios_empresa_id_foreign');
            } catch (\Throwable) {
                // ignore
            }
            try {
                $table->dropIndex('rh_funcionarios_empresa_id_index');
            } catch (\Throwable) {
                // ignore
            }
            $table->dropColumn('empresa_id');
        });
    }
};
