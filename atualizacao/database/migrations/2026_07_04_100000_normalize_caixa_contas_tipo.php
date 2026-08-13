<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixa_contas', function (Blueprint $table) {
            $table->string('tipo', 20)->default('CAIXA')->change();
        });

        DB::table('caixa_contas')->where('tipo', 'X')->update(['tipo' => 'CAIXA']);
        DB::table('caixa_contas')->where('tipo', 'S')->update(['tipo' => 'SUBCAIXA']);
    }

    public function down(): void
    {
        DB::table('caixa_contas')->where('tipo', 'CAIXA')->update(['tipo' => 'X']);
        DB::table('caixa_contas')->where('tipo', 'SUBCAIXA')->update(['tipo' => 'S']);

        Schema::table('caixa_contas', function (Blueprint $table) {
            $table->string('tipo', 1)->default('S')->change();
        });
    }
};
