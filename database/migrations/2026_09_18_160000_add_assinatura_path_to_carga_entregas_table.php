<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carga_entregas', function (Blueprint $table): void {
            $table->string('assinatura_path')->nullable()->after('foto_path');
        });
    }

    public function down(): void
    {
        Schema::table('carga_entregas', function (Blueprint $table): void {
            $table->dropColumn('assinatura_path');
        });
    }
};
