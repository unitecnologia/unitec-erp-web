<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot da % de comissão adicional no item da venda (data da venda).
     * Evita recalcular histórico quando a Precificação do produto mudar.
     */
    public function up(): void
    {
        if (! Schema::hasTable('venda_itens')) {
            return;
        }

        if (! Schema::hasColumn('venda_itens', 'comissao_pct')) {
            Schema::table('venda_itens', function (Blueprint $table): void {
                $table->decimal('comissao_pct', 8, 2)->default(0)->after('total');
            });
        }

        // Congela o valor vigente no momento da migration para itens já existentes.
        if (! Schema::hasColumn('products', 'comissao_pct')
            || ! Schema::hasColumn('products', 'paga_comissao')
        ) {
            return;
        }

        DB::table('products')
            ->where('paga_comissao', true)
            ->where('comissao_pct', '>', 0)
            ->orderBy('id')
            ->select(['id', 'comissao_pct'])
            ->chunkById(500, function ($products): void {
                foreach ($products as $product) {
                    DB::table('venda_itens')
                        ->where('product_id', (int) $product->id)
                        ->update(['comissao_pct' => (float) $product->comissao_pct]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venda_itens') || ! Schema::hasColumn('venda_itens', 'comissao_pct')) {
            return;
        }

        Schema::table('venda_itens', function (Blueprint $table): void {
            $table->dropColumn('comissao_pct');
        });
    }
};
