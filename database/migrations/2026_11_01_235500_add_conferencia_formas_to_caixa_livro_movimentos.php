<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conferência por forma no fechamento do Livro Caixa (F8).
 * Persistência mínima: JSON das modalidades + total informado.
 * Não cria lançamentos nem altera histórico SCV/PDV.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caixa_livro_movimentos')) {
            return;
        }

        Schema::table('caixa_livro_movimentos', function (Blueprint $table): void {
            if (! Schema::hasColumn('caixa_livro_movimentos', 'conferencia_formas')) {
                $table->json('conferencia_formas')->nullable()->after('diferenca');
            }
            if (! Schema::hasColumn('caixa_livro_movimentos', 'total_informado')) {
                $table->decimal('total_informado', 15, 2)->nullable()->after('conferencia_formas');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('caixa_livro_movimentos')) {
            return;
        }

        Schema::table('caixa_livro_movimentos', function (Blueprint $table): void {
            if (Schema::hasColumn('caixa_livro_movimentos', 'total_informado')) {
                $table->dropColumn('total_informado');
            }
            if (Schema::hasColumn('caixa_livro_movimentos', 'conferencia_formas')) {
                $table->dropColumn('conferencia_formas');
            }
        });
    }
};
