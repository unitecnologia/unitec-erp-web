<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            $table->string('municipio_prestacao_codigo', 7)->nullable()->after('municipio_incidencia');
            $table->string('municipio_prestacao_nome', 80)->nullable()->after('municipio_prestacao_codigo');
            $table->string('municipio_prestacao_uf', 2)->nullable()->after('municipio_prestacao_nome');
        });
    }

    public function down(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            $table->dropColumn([
                'municipio_prestacao_codigo',
                'municipio_prestacao_nome',
                'municipio_prestacao_uf',
            ]);
        });
    }
};
