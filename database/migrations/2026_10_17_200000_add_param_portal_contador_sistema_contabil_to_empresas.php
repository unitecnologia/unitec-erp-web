<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('empresas', 'param_portal_contador_sistema_contabil')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $table->string('param_portal_contador_sistema_contabil', 40)
                ->default('nao_definido')
                ->after('param_portal_contador_contador_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('empresas', 'param_portal_contador_sistema_contabil')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $table->dropColumn('param_portal_contador_sistema_contabil');
        });
    }
};
