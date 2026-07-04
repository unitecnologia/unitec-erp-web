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

        Schema::table('forca_vendas_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('forca_vendas_orders', 'situacao')) {
                $table->string('situacao', 20)->default('pendente')->after('status');
            }

            if (! Schema::hasColumn('forca_vendas_orders', 'identificacao')) {
                $table->string('identificacao', 60)->nullable()->after('situacao');
            }

            if (! Schema::hasColumn('forca_vendas_orders', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('received_at');
            }

            if (! Schema::hasColumn('forca_vendas_orders', 'faturado_at')) {
                $table->timestamp('faturado_at')->nullable()->after('confirmed_at');
            }

            if (! Schema::hasColumn('forca_vendas_orders', 'canceled_at')) {
                $table->timestamp('canceled_at')->nullable()->after('faturado_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('forca_vendas_orders')) {
            return;
        }

        Schema::table('forca_vendas_orders', function (Blueprint $table) {
            foreach (['situacao', 'identificacao', 'confirmed_at', 'faturado_at', 'canceled_at'] as $column) {
                if (Schema::hasColumn('forca_vendas_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
