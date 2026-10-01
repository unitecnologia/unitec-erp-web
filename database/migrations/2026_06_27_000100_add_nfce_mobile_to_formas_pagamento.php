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
            $table->boolean('nfce')->default(false)->after('aparece_contas_receber');
            $table->boolean('disponivel_mobile')->default(false)->after('nfce');
        });

        // Seed DEV: todas as formas padrão liberadas no mobile.
        DB::table('formas_pagamento')
            ->whereIn('codigo', [1, 2, 3, 4, 5])
            ->update([
                'disponivel_mobile' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->dropColumn(['nfce', 'disponivel_mobile']);
        });
    }
};
