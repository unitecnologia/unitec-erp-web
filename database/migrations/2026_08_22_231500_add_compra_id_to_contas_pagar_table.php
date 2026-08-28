<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contas_pagar') || Schema::hasColumn('contas_pagar', 'compra_id')) {
            return;
        }

        Schema::table('contas_pagar', function (Blueprint $table): void {
            $table->unsignedBigInteger('compra_id')->nullable()->after('fornecedor_id');
            $table->index('compra_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contas_pagar') || ! Schema::hasColumn('contas_pagar', 'compra_id')) {
            return;
        }

        Schema::table('contas_pagar', function (Blueprint $table): void {
            $table->dropIndex(['compra_id']);
            $table->dropColumn('compra_id');
        });
    }
};
