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
            if (! Schema::hasColumn('vendas_internas_orders', 'em_edicao_vi')) {
                $table->boolean('em_edicao_vi')->default(false)->after('situacao');
            }
            if (! Schema::hasColumn('vendas_internas_orders', 'em_edicao_vi_at')) {
                $table->timestamp('em_edicao_vi_at')->nullable()->after('em_edicao_vi');
            }
            if (! Schema::hasColumn('vendas_internas_orders', 'em_edicao_device_uuid')) {
                $table->string('em_edicao_device_uuid', 100)->nullable()->after('em_edicao_vi_at');
            }
            if (! Schema::hasColumn('vendas_internas_orders', 'em_edicao_user_id')) {
                $table->unsignedBigInteger('em_edicao_user_id')->nullable()->after('em_edicao_device_uuid');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendas_internas_orders')) {
            return;
        }

        Schema::table('vendas_internas_orders', function (Blueprint $table): void {
            foreach (['em_edicao_user_id', 'em_edicao_device_uuid', 'em_edicao_vi_at', 'em_edicao_vi'] as $col) {
                if (Schema::hasColumn('vendas_internas_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
