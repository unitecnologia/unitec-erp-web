<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendas')) {
            return;
        }

        Schema::table('vendas', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendas', 'empresa_id')) {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('empresas')
                    ->nullOnDelete();
                $table->index('empresa_id');
            }
        });

        $this->backfillEmpresaId();
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendas') || ! Schema::hasColumn('vendas', 'empresa_id')) {
            return;
        }

        Schema::table('vendas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('empresa_id');
        });
    }

    private function backfillEmpresaId(): void
    {
        if (! Schema::hasColumn('vendas', 'empresa_id')) {
            return;
        }

        $prefix = Schema::getConnection()->getTablePrefix();
        $vendas = $prefix.'vendas';

        if (Schema::hasTable('pdv_vendas') && Schema::hasTable('pdv_caixa_sessoes')) {
            DB::update("
                UPDATE {$vendas} v
                INNER JOIN {$prefix}pdv_vendas pv ON pv.venda_id = v.id
                INNER JOIN {$prefix}pdv_caixa_sessoes s ON s.id = pv.pdv_caixa_sessao_id
                SET v.empresa_id = s.empresa_id
                WHERE v.empresa_id IS NULL
            ");
        }

        if (Schema::hasTable('forca_vendas_orders') && Schema::hasColumn('forca_vendas_orders', 'empresa_id')) {
            DB::update("
                UPDATE {$vendas} v
                INNER JOIN {$prefix}forca_vendas_orders o ON o.venda_id = v.id
                SET v.empresa_id = o.empresa_id
                WHERE v.empresa_id IS NULL
                  AND o.empresa_id IS NOT NULL
            ");
        }
    }
};
