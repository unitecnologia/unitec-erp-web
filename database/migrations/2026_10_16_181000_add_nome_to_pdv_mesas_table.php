<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nome personalizado da mesa (ex.: "Varanda"). Vazio/null = rótulo padrão Mesa 01…
 * Reduzir a quantidade em param_pdv_qtd_mesas não apaga linhas — só deixa de exibir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_mesas') || Schema::hasColumn('pdv_mesas', 'nome')) {
            return;
        }

        Schema::table('pdv_mesas', function (Blueprint $table): void {
            $table->string('nome', 60)->nullable()->after('numero');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pdv_mesas') || ! Schema::hasColumn('pdv_mesas', 'nome')) {
            return;
        }

        Schema::table('pdv_mesas', function (Blueprint $table): void {
            $table->dropColumn('nome');
        });
    }
};
