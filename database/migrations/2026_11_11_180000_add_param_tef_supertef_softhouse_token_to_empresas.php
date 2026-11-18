<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresas') || Schema::hasColumn('empresas', 'param_tef_supertef_softhouse_token')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $table->text('param_tef_supertef_softhouse_token')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresas') || ! Schema::hasColumn('empresas', 'param_tef_supertef_softhouse_token')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $table->dropColumn('param_tef_supertef_softhouse_token');
        });
    }
};
