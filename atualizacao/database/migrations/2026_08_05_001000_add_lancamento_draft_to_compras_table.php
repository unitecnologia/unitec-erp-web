<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rascunho do lançamento de compras (grade/precificação).
 * Não implica entrada de estoque nem atualização definitiva de preços —
 * isso só ocorre no Finalizar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table): void {
            if (! Schema::hasColumn('compras', 'lancamento_draft')) {
                $table->longText('lancamento_draft')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table): void {
            if (Schema::hasColumn('compras', 'lancamento_draft')) {
                $table->dropColumn('lancamento_draft');
            }
        });
    }
};
