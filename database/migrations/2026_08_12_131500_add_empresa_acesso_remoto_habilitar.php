<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_acesso_remoto_habilitar')) {
                $table->boolean('param_acesso_remoto_habilitar')->default(true);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_acesso_remoto_habilitar')) {
                $table->dropColumn('param_acesso_remoto_habilitar');
            }
        });
    }
};
