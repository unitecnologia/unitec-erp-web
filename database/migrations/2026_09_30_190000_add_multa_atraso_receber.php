<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresas') && ! Schema::hasColumn('empresas', 'param_multa_atraso_pct')) {
            Schema::table('empresas', function (Blueprint $table): void {
                $table->decimal('param_multa_atraso_pct', 8, 2)->default(0);
            });
        }

        if (Schema::hasTable('contas_receber') && ! Schema::hasColumn('contas_receber', 'multa_pct')) {
            Schema::table('contas_receber', function (Blueprint $table): void {
                $table->decimal('multa_pct', 8, 4)->default(0)->after('carencia_juros_dias');
                $table->decimal('multa', 15, 2)->default(0)->after('multa_pct');
            });
        }

        if (Schema::hasTable('conta_receber_pagamentos') && ! Schema::hasColumn('conta_receber_pagamentos', 'multa')) {
            Schema::table('conta_receber_pagamentos', function (Blueprint $table): void {
                $table->decimal('multa', 15, 2)->default(0)->after('juros');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conta_receber_pagamentos') && Schema::hasColumn('conta_receber_pagamentos', 'multa')) {
            Schema::table('conta_receber_pagamentos', function (Blueprint $table): void {
                $table->dropColumn('multa');
            });
        }

        if (Schema::hasTable('contas_receber') && Schema::hasColumn('contas_receber', 'multa')) {
            Schema::table('contas_receber', function (Blueprint $table): void {
                $table->dropColumn(['multa', 'multa_pct']);
            });
        }

        if (Schema::hasTable('empresas') && Schema::hasColumn('empresas', 'param_multa_atraso_pct')) {
            Schema::table('empresas', function (Blueprint $table): void {
                $table->dropColumn('param_multa_atraso_pct');
            });
        }
    }
};
