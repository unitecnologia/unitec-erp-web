<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('caixa_contas')->where('tipo', 'CAIXA')->update(['tipo' => 'PDV']);

        if (Schema::hasColumn('caixa_contas', 'tipo')) {
            Schema::table('caixa_contas', function (Blueprint $table) {
                $table->string('tipo', 20)->default('PDV')->change();
            });
        }
    }

    public function down(): void
    {
        DB::table('caixa_contas')->where('tipo', 'PDV')->update(['tipo' => 'CAIXA']);

        if (Schema::hasColumn('caixa_contas', 'tipo')) {
            Schema::table('caixa_contas', function (Blueprint $table) {
                $table->string('tipo', 20)->default('CAIXA')->change();
            });
        }
    }
};
