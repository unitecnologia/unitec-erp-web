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
            if (! Schema::hasColumn('forca_vendas_orders', 'envio_whats_status')) {
                $table->string('envio_whats_status', 30)->nullable()->after('canceled_at');
            }

            if (! Schema::hasColumn('forca_vendas_orders', 'numero_whatsapp')) {
                $table->string('numero_whatsapp', 30)->nullable()->after('envio_whats_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('forca_vendas_orders')) {
            return;
        }

        Schema::table('forca_vendas_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('forca_vendas_orders', 'numero_whatsapp')) {
                $table->dropColumn('numero_whatsapp');
            }

            if (Schema::hasColumn('forca_vendas_orders', 'envio_whats_status')) {
                $table->dropColumn('envio_whats_status');
            }
        });
    }
};
