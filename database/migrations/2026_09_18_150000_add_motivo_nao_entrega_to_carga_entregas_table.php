<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carga_entregas', function (Blueprint $table): void {
            $table->string('motivo_nao_entrega', 60)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('carga_entregas', function (Blueprint $table): void {
            $table->dropColumn('motivo_nao_entrega');
        });
    }
};
