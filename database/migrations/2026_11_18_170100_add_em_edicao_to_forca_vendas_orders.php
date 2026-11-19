<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('forca_vendas_orders')) {
            return;
        }

        Schema::table('forca_vendas_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('forca_vendas_orders', 'em_edicao_fv')) {
                $table->boolean('em_edicao_fv')->default(false)->after('situacao');
            }
            if (! Schema::hasColumn('forca_vendas_orders', 'em_edicao_fv_at')) {
                $table->timestamp('em_edicao_fv_at')->nullable()->after('em_edicao_fv');
            }
            if (! Schema::hasColumn('forca_vendas_orders', 'em_edicao_device_uuid')) {
                $table->string('em_edicao_device_uuid', 100)->nullable()->after('em_edicao_fv_at');
            }
            if (! Schema::hasColumn('forca_vendas_orders', 'em_edicao_user_id')) {
                $table->unsignedBigInteger('em_edicao_user_id')->nullable()->after('em_edicao_device_uuid');
            }
            if (! Schema::hasColumn('forca_vendas_orders', 'em_edicao_erp')) {
                $table->boolean('em_edicao_erp')->default(false)->after('em_edicao_user_id');
            }
            if (! Schema::hasColumn('forca_vendas_orders', 'em_edicao_erp_at')) {
                $table->timestamp('em_edicao_erp_at')->nullable()->after('em_edicao_erp');
            }
            if (! Schema::hasColumn('forca_vendas_orders', 'em_edicao_erp_user_id')) {
                $table->unsignedBigInteger('em_edicao_erp_user_id')->nullable()->after('em_edicao_erp_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('forca_vendas_orders')) {
            return;
        }

        Schema::table('forca_vendas_orders', function (Blueprint $table): void {
            foreach ([
                'em_edicao_erp_user_id',
                'em_edicao_erp_at',
                'em_edicao_erp',
                'em_edicao_user_id',
                'em_edicao_device_uuid',
                'em_edicao_fv_at',
                'em_edicao_fv',
            ] as $col) {
                if (Schema::hasColumn('forca_vendas_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
