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
            if (! Schema::hasColumn('grupos', 'mostrar_no_app')) {
                $table->boolean('mostrar_no_app')
                    ->default(false)
                    ->after('ativo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('grupos')) {
            return;
        }

        Schema::table('grupos', function (Blueprint $table): void {
            if (Schema::hasColumn('grupos', 'mostrar_no_app')) {
                $table->dropColumn('mostrar_no_app');
            }
        });
    }
};
