<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->boolean('nfce')->default(false)->after('aparece_contas_receber');
            $table->boolean('disponivel_mobile')->default(false)->after('nfce');
        });
    }

    public function down(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->dropColumn(['nfce', 'disponivel_mobile']);
        });
    }
};
