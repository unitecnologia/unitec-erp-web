<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grupos')) {
            return;
        }

        Schema::table('grupos', function (Blueprint $table): void {
            if (! Schema::hasColumn('grupos', 'balanca_marcado')) {
                $table->boolean('balanca_marcado')
                    ->default(false)
                    ->after('mostrar_no_app');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('grupos')) {
            return;
        }

        Schema::table('grupos', function (Blueprint $table): void {
            if (Schema::hasColumn('grupos', 'balanca_marcado')) {
                $table->dropColumn('balanca_marcado');
            }
        });
    }
};
