<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('entregas')) {
            return;
        }

        Schema::table('entregas', function (Blueprint $table): void {
            if (! Schema::hasColumn('entregas', 'tipo_saida')) {
                $table->string('tipo_saida', 20)->nullable()->after('expedido_em');
            }

            if (! Schema::hasColumn('entregas', 'transportadora_id')) {
                $table->foreignId('transportadora_id')->nullable()->after('tipo_saida')
                    ->constrained('people')->nullOnDelete();
            }

            if (! Schema::hasColumn('entregas', 'qtd_volumes')) {
                $table->unsignedSmallInteger('qtd_volumes')->nullable()->after('transportadora_id');
            }

            if (! Schema::hasColumn('entregas', 'peso_calculado_kg')) {
                $table->decimal('peso_calculado_kg', 12, 3)->nullable()->after('qtd_volumes');
            }

            if (! Schema::hasColumn('entregas', 'romaneio_retirada_emitido_em')) {
                $table->timestamp('romaneio_retirada_emitido_em')->nullable()->after('peso_calculado_kg');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('entregas')) {
            return;
        }

        Schema::table('entregas', function (Blueprint $table): void {
            if (Schema::hasColumn('entregas', 'romaneio_retirada_emitido_em')) {
                $table->dropColumn('romaneio_retirada_emitido_em');
            }

            if (Schema::hasColumn('entregas', 'peso_calculado_kg')) {
                $table->dropColumn('peso_calculado_kg');
            }

            if (Schema::hasColumn('entregas', 'qtd_volumes')) {
                $table->dropColumn('qtd_volumes');
            }

            if (Schema::hasColumn('entregas', 'transportadora_id')) {
                $table->dropForeign(['transportadora_id']);
                $table->dropColumn('transportadora_id');
            }

            if (Schema::hasColumn('entregas', 'tipo_saida')) {
                $table->dropColumn('tipo_saida');
            }
        });
    }
};
