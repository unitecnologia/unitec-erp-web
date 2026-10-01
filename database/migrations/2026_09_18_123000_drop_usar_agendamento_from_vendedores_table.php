<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendedores', function (Blueprint $table): void {
            if (Schema::hasColumn('vendedores', 'usar_agendamento')) {
                $table->dropColumn('usar_agendamento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendedores', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendedores', 'usar_agendamento')) {
                $table->boolean('usar_agendamento')->default(false);
            }
        });
    }
};
