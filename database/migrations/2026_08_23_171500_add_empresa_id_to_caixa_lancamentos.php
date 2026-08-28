<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caixa_lancamentos') || Schema::hasColumn('caixa_lancamentos', 'empresa_id')) {
            return;
        }

        Schema::table('caixa_lancamentos', function (Blueprint $table): void {
            $table->foreignId('empresa_id')
                ->nullable()
                ->after('id')
                ->constrained('empresas')
                ->nullOnDelete();
            $table->index('empresa_id');
        });

        $this->backfillFromFvOrders();
    }

    public function down(): void
    {
        if (! Schema::hasTable('caixa_lancamentos') || ! Schema::hasColumn('caixa_lancamentos', 'empresa_id')) {
            return;
        }

        Schema::table('caixa_lancamentos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('empresa_id');
        });
    }

    private function backfillFromFvOrders(): void
    {
        if (! Schema::hasTable('forca_vendas_orders') || ! Schema::hasColumn('forca_vendas_orders', 'empresa_id')) {
            return;
        }

        $prefix = DB::getTablePrefix();
        $lanc = $prefix.'caixa_lancamentos';
        $orders = $prefix.'forca_vendas_orders';

        // Documento FV-{id} ou FV-{id}/n → empresa do pedido
        DB::statement("
            UPDATE {$lanc} cl
            INNER JOIN {$orders} o
                ON cl.documento = CONCAT('FV-', o.id)
                OR cl.documento LIKE CONCAT('FV-', o.id, '/%')
            SET cl.empresa_id = o.empresa_id
            WHERE cl.empresa_id IS NULL
              AND o.empresa_id IS NOT NULL
        ");
    }
};
