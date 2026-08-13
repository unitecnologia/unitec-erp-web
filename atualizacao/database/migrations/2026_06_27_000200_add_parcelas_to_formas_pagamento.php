<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->json('parcelas')->nullable()->after('disponivel_mobile');
        });
    }

    public function down(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->dropColumn('parcelas');
        });
    }
};
