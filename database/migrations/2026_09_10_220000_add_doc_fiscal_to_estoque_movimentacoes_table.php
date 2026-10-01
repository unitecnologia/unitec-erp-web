<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estoque_movimentacoes')) {
            return;
        }

        Schema::table('estoque_movimentacoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('estoque_movimentacoes', 'doc_fiscal_tipo')) {
                $table->string('doc_fiscal_tipo', 20)->nullable()->after('origem_numero');
            }
            if (! Schema::hasColumn('estoque_movimentacoes', 'doc_fiscal_numero')) {
                $table->string('doc_fiscal_numero', 40)->nullable()->after('doc_fiscal_tipo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('estoque_movimentacoes')) {
            return;
        }

        Schema::table('estoque_movimentacoes', function (Blueprint $table): void {
            if (Schema::hasColumn('estoque_movimentacoes', 'doc_fiscal_numero')) {
                $table->dropColumn('doc_fiscal_numero');
            }
            if (Schema::hasColumn('estoque_movimentacoes', 'doc_fiscal_tipo')) {
                $table->dropColumn('doc_fiscal_tipo');
            }
        });
    }
};
