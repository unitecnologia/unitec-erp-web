<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_receber', function (Blueprint $table): void {
            if (! Schema::hasColumn('contas_receber', 'cartao_maquininha')) {
                $table->string('cartao_maquininha', 60)->nullable()->after('cartao_autorizacao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contas_receber', function (Blueprint $table): void {
            if (Schema::hasColumn('contas_receber', 'cartao_maquininha')) {
                $table->dropColumn('cartao_maquininha');
            }
        });
    }
};
