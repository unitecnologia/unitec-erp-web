<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contas_pagar') && ! Schema::hasColumn('contas_pagar', 'multa')) {
            Schema::table('contas_pagar', function (Blueprint $table): void {
                $table->decimal('multa', 15, 2)->default(0)->after('juros');
            });
        }

        if (Schema::hasTable('conta_pagar_pagamentos')) {
            Schema::table('conta_pagar_pagamentos', function (Blueprint $table): void {
                if (! Schema::hasColumn('conta_pagar_pagamentos', 'perc_multa')) {
                    $table->decimal('perc_multa', 8, 4)->default(0)->after('juros');
                }

                if (! Schema::hasColumn('conta_pagar_pagamentos', 'multa')) {
                    $table->decimal('multa', 15, 2)->default(0)->after('perc_multa');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conta_pagar_pagamentos')) {
            Schema::table('conta_pagar_pagamentos', function (Blueprint $table): void {
                if (Schema::hasColumn('conta_pagar_pagamentos', 'multa')) {
                    $table->dropColumn('multa');
                }

                if (Schema::hasColumn('conta_pagar_pagamentos', 'perc_multa')) {
                    $table->dropColumn('perc_multa');
                }
            });
        }

        if (Schema::hasTable('contas_pagar') && Schema::hasColumn('contas_pagar', 'multa')) {
            Schema::table('contas_pagar', function (Blueprint $table): void {
                $table->dropColumn('multa');
            });
        }
    }
};
