<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_receber', function (Blueprint $table): void {
            if (! Schema::hasColumn('contas_receber', 'juros_diario_pct')) {
                $table->decimal('juros_diario_pct', 8, 4)->default(0)->after('juros');
            }

            if (! Schema::hasColumn('contas_receber', 'carencia_juros_dias')) {
                $table->unsignedInteger('carencia_juros_dias')->default(0)->after('juros_diario_pct');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contas_receber', function (Blueprint $table): void {
            foreach (['carencia_juros_dias', 'juros_diario_pct'] as $col) {
                if (Schema::hasColumn('contas_receber', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
