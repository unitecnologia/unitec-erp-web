<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ciclo de vida da venda em espera: pendente → em_uso → pendente|finalizada.
 * Permite re-suspender a mesma espera sem duplicar e impedir recuperação após F7.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_venda_esperas')) {
            return;
        }

        Schema::table('pdv_venda_esperas', function (Blueprint $table): void {
            if (! Schema::hasColumn('pdv_venda_esperas', 'status')) {
                $table->string('status', 20)->default('pendente')->after('snapshot');
            }

            if (! Schema::hasColumn('pdv_venda_esperas', 'finalizada_em')) {
                $table->timestamp('finalizada_em')->nullable()->after('status');
            }

            if (! Schema::hasColumn('pdv_venda_esperas', 'pdv_venda_id')) {
                $table->unsignedBigInteger('pdv_venda_id')->nullable()->after('finalizada_em');
            }
        });

        $indexes = collect(Schema::getIndexes('pdv_venda_esperas'))->pluck('name')->all();

        if (! in_array('pdv_espera_status_idx', $indexes, true) && Schema::hasColumn('pdv_venda_esperas', 'status')) {
            Schema::table('pdv_venda_esperas', function (Blueprint $table): void {
                $table->index(['pdv_caixa_sessao_id', 'user_id', 'status'], 'pdv_espera_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pdv_venda_esperas')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('pdv_venda_esperas'))->pluck('name')->all();

        Schema::table('pdv_venda_esperas', function (Blueprint $table) use ($indexes): void {
            if (in_array('pdv_espera_status_idx', $indexes, true)) {
                $table->dropIndex('pdv_espera_status_idx');
            }

            foreach (['pdv_venda_id', 'finalizada_em', 'status'] as $column) {
                if (Schema::hasColumn('pdv_venda_esperas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
