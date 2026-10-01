<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'configuracao_inicial_concluida')) {
            Schema::table('empresas', function (Blueprint $table): void {
                $table->boolean('configuracao_inicial_concluida')->default(false);
            });
        }

        DB::table('empresas')->update([
            'configuracao_inicial_concluida' => true,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresas', 'configuracao_inicial_concluida')) {
            Schema::table('empresas', function (Blueprint $table): void {
                $table->dropColumn('configuracao_inicial_concluida');
            });
        }
    }
};
