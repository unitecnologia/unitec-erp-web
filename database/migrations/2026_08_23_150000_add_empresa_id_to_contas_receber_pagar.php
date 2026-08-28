<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addEmpresaId('contas_receber');
        $this->addEmpresaId('contas_pagar');
        $this->backfillContasPagar();
        $this->backfillSingleEmpresa('contas_receber');
        $this->backfillSingleEmpresa('contas_pagar');
    }

    public function down(): void
    {
        $this->dropEmpresaId('contas_pagar');
        $this->dropEmpresaId('contas_receber');
    }

    private function addEmpresaId(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'empresa_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $blueprint->foreignId('empresa_id')
                ->nullable()
                ->after('id')
                ->constrained('empresas')
                ->nullOnDelete();
            $blueprint->index('empresa_id');
        });
    }

    private function dropEmpresaId(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'empresa_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropConstrainedForeignId('empresa_id');
        });
    }

    private function backfillContasPagar(): void
    {
        if (! Schema::hasTable('contas_pagar')
            || ! Schema::hasColumn('contas_pagar', 'empresa_id')
            || ! Schema::hasTable('compras')
            || ! Schema::hasColumn('compras', 'empresa_id')
            || ! Schema::hasColumn('contas_pagar', 'compra_id')) {
            return;
        }

        $prefix = DB::getTablePrefix();
        $pagar = $prefix.'contas_pagar';
        $compras = $prefix.'compras';

        DB::statement(
            "UPDATE {$pagar} cp
             INNER JOIN {$compras} c ON c.id = cp.compra_id
             SET cp.empresa_id = c.empresa_id
             WHERE cp.empresa_id IS NULL
               AND c.empresa_id IS NOT NULL"
        );
    }

    private function backfillSingleEmpresa(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'empresa_id')) {
            return;
        }

        $empresaIds = DB::table('empresas')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if (count($empresaIds) !== 1) {
            return;
        }

        DB::table($table)
            ->whereNull('empresa_id')
            ->update(['empresa_id' => $empresaIds[0]]);
    }
};
