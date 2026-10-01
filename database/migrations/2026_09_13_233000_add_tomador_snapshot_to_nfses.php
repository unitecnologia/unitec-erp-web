<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            $table->string('tomador_cidade_codigo')->nullable()->after('tomador_uf');
            $table->string('tomador_email')->nullable()->after('tomador_cidade_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            $table->dropColumn([
                'tomador_cidade_codigo',
                'tomador_email',
            ]);
        });
    }
};
