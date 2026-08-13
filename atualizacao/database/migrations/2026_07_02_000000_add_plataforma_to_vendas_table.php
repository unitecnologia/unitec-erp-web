<?php

use App\Models\ForcaVendasOrder;
use App\Models\PdvVenda;
use App\Models\Venda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendas', 'plataforma')) {
                $table->string('plataforma', 20)->nullable()->after('tipo');
                $table->index('plataforma');
            }
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table): void {
            if (Schema::hasColumn('vendas', 'plataforma')) {
                $table->dropIndex(['plataforma']);
                $table->dropColumn('plataforma');
            }
        });
    }

    private function backfill(): void
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

        Venda::query()
            ->whereNull('plataforma')
            ->update(['plataforma' => Venda::PLATAFORMA_ERP]);
    }
};
