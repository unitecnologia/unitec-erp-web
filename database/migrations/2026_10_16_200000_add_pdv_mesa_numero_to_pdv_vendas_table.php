<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liga a venda do PDV à mesa que a originou (histórico para relatórios de restaurante).
 * Backfill: última venda conhecida por mesa (pdv_mesas.ultima_pdv_venda_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_vendas') || Schema::hasColumn('pdv_vendas', 'pdv_mesa_numero')) {
            return;
        }

        Schema::table('pdv_vendas', function (Blueprint $table): void {
            $table->unsignedSmallInteger('pdv_mesa_numero')->nullable()->after('numero');
            $table->index(['pdv_mesa_numero', 'fechado_em'], 'pdv_vendas_mesa_fechado_idx');
        });

        if (Schema::hasTable('pdv_mesas')) {
            $vendas = Schema::getConnection()->getTablePrefix().'pdv_vendas';
            $mesas = Schema::getConnection()->getTablePrefix().'pdv_mesas';

            DB::statement(
                "UPDATE {$vendas} v
                 INNER JOIN {$mesas} m ON m.ultima_pdv_venda_id = v.id
                 SET v.pdv_mesa_numero = m.numero
                 WHERE v.pdv_mesa_numero IS NULL
                   AND m.ultima_pdv_venda_id IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pdv_vendas') || ! Schema::hasColumn('pdv_vendas', 'pdv_mesa_numero')) {
            return;
        }

        Schema::table('pdv_vendas', function (Blueprint $table): void {
            $table->dropIndex('pdv_vendas_mesa_fechado_idx');
            $table->dropColumn('pdv_mesa_numero');
        });
    }
};
