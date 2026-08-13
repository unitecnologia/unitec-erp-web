<?php

use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use App\Models\VendasInternasOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orcamentos', function (Blueprint $table): void {
            if (! Schema::hasColumn('orcamentos', 'hora')) {
                $table->time('hora')->nullable()->after('data');
            }

            if (! Schema::hasColumn('orcamentos', 'plataforma')) {
                $table->string('plataforma', 20)->nullable()->after('status');
                $table->index('plataforma');
            }
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('orcamentos', function (Blueprint $table): void {
            if (Schema::hasColumn('orcamentos', 'plataforma')) {
                $table->dropIndex(['plataforma']);
                $table->dropColumn('plataforma');
            }

            if (Schema::hasColumn('orcamentos', 'hora')) {
                $table->dropColumn('hora');
            }
        });
    }

    private function backfill(): void
    {
        DB::table('orcamentos')
            ->whereNull('hora')
            ->update(['hora' => DB::raw('TIME(created_at)')]);

        ForcaVendasOrder::query()
            ->whereNotNull('orcamento_id')
            ->select(['id', 'orcamento_id', 'client_created_at'])
            ->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    $updates = ['plataforma' => Orcamento::PLATAFORMA_FV];

                    if ($order->client_created_at) {
                        $updates['hora'] = $order->client_created_at->format('H:i:s');
                    }

                    Orcamento::query()
                        ->whereKey($order->orcamento_id)
                        ->where(function ($query): void {
                            $query->whereNull('plataforma')
                                ->orWhere('plataforma', '!=', Orcamento::PLATAFORMA_FV);
                        })
                        ->update($updates);
                }
            });

        VendasInternasOrder::query()
            ->whereNotNull('orcamento_id')
            ->select(['id', 'orcamento_id', 'client_created_at'])
            ->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    $updates = ['plataforma' => Orcamento::PLATAFORMA_VI];

                    if ($order->client_created_at) {
                        $updates['hora'] = $order->client_created_at->format('H:i:s');
                    }

                    Orcamento::query()
                        ->whereKey($order->orcamento_id)
                        ->where(function ($query): void {
                            $query->whereNull('plataforma')
                                ->orWhere('plataforma', '!=', Orcamento::PLATAFORMA_VI);
                        })
                        ->update($updates);
                }
            });

        Orcamento::query()
            ->whereNull('plataforma')
            ->update(['plataforma' => Orcamento::PLATAFORMA_ERP]);
    }
};
