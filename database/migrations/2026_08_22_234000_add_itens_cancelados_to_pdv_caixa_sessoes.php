<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_caixa_sessoes') || Schema::hasColumn('pdv_caixa_sessoes', 'itens_cancelados')) {
            return;
        }

        Schema::table('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->json('itens_cancelados')->nullable()->after('fechado_em');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pdv_caixa_sessoes') || ! Schema::hasColumn('pdv_caixa_sessoes', 'itens_cancelados')) {
            return;
        }

        Schema::table('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->dropColumn('itens_cancelados');
        });
    }
};
