<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendas_internas_orders')) {
            return;
        }

        Schema::table('vendas_internas_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendas_internas_orders', 'em_edicao_erp')) {
                $table->boolean('em_edicao_erp')->default(false)->after('em_edicao_user_id');
            }
            if (! Schema::hasColumn('vendas_internas_orders', 'em_edicao_erp_at')) {
                $table->timestamp('em_edicao_erp_at')->nullable()->after('em_edicao_erp');
            }
            if (! Schema::hasColumn('vendas_internas_orders', 'em_edicao_erp_user_id')) {
                $table->unsignedBigInteger('em_edicao_erp_user_id')->nullable()->after('em_edicao_erp_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendas_internas_orders')) {
            return;
        }

        Schema::table('vendas_internas_orders', function (Blueprint $table): void {
            foreach (['em_edicao_erp_user_id', 'em_edicao_erp_at', 'em_edicao_erp'] as $col) {
                if (Schema::hasColumn('vendas_internas_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
