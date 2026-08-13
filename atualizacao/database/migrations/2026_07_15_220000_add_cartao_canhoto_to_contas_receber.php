<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_receber', function (Blueprint $table): void {
            if (! Schema::hasColumn('contas_receber', 'cartao_nsu')) {
                $table->string('cartao_nsu', 40)->nullable()->after('forma');
            }
            if (! Schema::hasColumn('contas_receber', 'cartao_autorizacao')) {
                $table->string('cartao_autorizacao', 40)->nullable()->after('cartao_nsu');
            }
            if (! Schema::hasColumn('contas_receber', 'cartao_bandeira')) {
                $table->string('cartao_bandeira', 40)->nullable()->after('cartao_autorizacao');
            }
            if (! Schema::hasColumn('contas_receber', 'cartao_parcela')) {
                $table->string('cartao_parcela', 20)->nullable()->after('cartao_bandeira');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contas_receber', function (Blueprint $table): void {
            foreach (['cartao_nsu', 'cartao_autorizacao', 'cartao_bandeira', 'cartao_parcela'] as $col) {
                if (Schema::hasColumn('contas_receber', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
