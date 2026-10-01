<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_venda_nfce') || ! Schema::hasColumn('pdv_venda_nfce', 'motivo_rejeicao')) {
            return;
        }

        Schema::table('pdv_venda_nfce', function (Blueprint $table): void {
            $table->text('motivo_rejeicao')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pdv_venda_nfce') || ! Schema::hasColumn('pdv_venda_nfce', 'motivo_rejeicao')) {
            return;
        }

        Schema::table('pdv_venda_nfce', function (Blueprint $table): void {
            $table->string('motivo_rejeicao')->nullable()->change();
        });
    }
};
