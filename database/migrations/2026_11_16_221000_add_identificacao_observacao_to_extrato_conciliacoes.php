<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria da conciliação PIX assistida (identificação comprovada ou não).
 * Não altera caixa_lancamentos nem conciliações já gravadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extrato_conciliacoes')) {
            return;
        }

        Schema::table('extrato_conciliacoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('extrato_conciliacoes', 'identificacao')) {
                $table->string('identificacao', 20)->nullable()->after('status');
            }
            if (! Schema::hasColumn('extrato_conciliacoes', 'observacao')) {
                $table->string('observacao', 255)->nullable()->after('motivo_reversao');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('extrato_conciliacoes')) {
            return;
        }

        Schema::table('extrato_conciliacoes', function (Blueprint $table): void {
            if (Schema::hasColumn('extrato_conciliacoes', 'observacao')) {
                $table->dropColumn('observacao');
            }
            if (Schema::hasColumn('extrato_conciliacoes', 'identificacao')) {
                $table->dropColumn('identificacao');
            }
        });
    }
};
