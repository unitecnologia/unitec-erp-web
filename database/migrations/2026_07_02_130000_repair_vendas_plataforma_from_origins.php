<?php

use App\Models\ForcaVendasOrder;
use App\Models\PdvVenda;
use App\Models\Venda;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PdvVenda::query()
            ->whereNotNull('venda_id')
            ->select(['id', 'venda_id'])
            ->chunkById(200, function ($cupons): void {
                $ids = $cupons->pluck('venda_id')->filter()->unique()->all();

                if ($ids === []) {
                    return;
                }

                Venda::query()
                    ->whereIn('id', $ids)
                    ->update(['plataforma' => Venda::PLATAFORMA_PDV]);
            });

        ForcaVendasOrder::query()
            ->whereNotNull('venda_id')
            ->select(['id', 'venda_id'])
            ->chunkById(200, function ($orders): void {
                $ids = $orders->pluck('venda_id')->filter()->unique()->all();

                if ($ids === []) {
                    return;
                }

                Venda::query()
                    ->whereIn('id', $ids)
                    ->update(['plataforma' => Venda::PLATAFORMA_MOBILE]);
            });
    }

    public function down(): void
    {
        // Reparo idempotente; não reverte dados corrigidos.
    }
};
