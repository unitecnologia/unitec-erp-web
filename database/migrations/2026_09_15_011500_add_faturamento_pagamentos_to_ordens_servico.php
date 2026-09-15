<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordens_servico')) {
            return;
        }

        Schema::table('ordens_servico', function (Blueprint $table): void {
            if (! Schema::hasColumn('ordens_servico', 'faturamento_pagamentos')) {
                $table->json('faturamento_pagamentos')->nullable()->after('total_geral');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordens_servico')) {
            return;
        }

        Schema::table('ordens_servico', function (Blueprint $table): void {
            if (Schema::hasColumn('ordens_servico', 'faturamento_pagamentos')) {
                $table->dropColumn('faturamento_pagamentos');
            }
        });
    }
};
