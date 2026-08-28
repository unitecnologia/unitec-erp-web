<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('terminais')) {
            return;
        }

        Schema::table('terminais', function (Blueprint $table): void {
            foreach (['caminho_sat_dll', 'modelo_sat_dll', 'tipo_sat_dll'] as $column) {
                if (Schema::hasColumn('terminais', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('terminais')) {
            return;
        }

        Schema::table('terminais', function (Blueprint $table): void {
            if (! Schema::hasColumn('terminais', 'caminho_sat_dll')) {
                $table->string('caminho_sat_dll')->nullable();
            }

            if (! Schema::hasColumn('terminais', 'modelo_sat_dll')) {
                $table->string('modelo_sat_dll', 40)->nullable();
            }

            if (! Schema::hasColumn('terminais', 'tipo_sat_dll')) {
                $table->string('tipo_sat_dll', 40)->nullable();
            }
        });
    }
};
