<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->json('parcelas')->nullable()->after('disponivel_mobile');
        });

        // BOLETO (codigo 5): espelho DEV — parcelas ["30,60,90"].
        DB::table('formas_pagamento')
            ->where('codigo', 5)
            ->where('tipo', 'boleto')
            ->update([
                'parcelas' => json_encode(['30,60,90']),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->dropColumn('parcelas');
        });
    }
};
