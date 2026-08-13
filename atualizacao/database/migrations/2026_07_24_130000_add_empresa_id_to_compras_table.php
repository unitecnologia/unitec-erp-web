<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('compras', 'empresa_id')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('empresas')
                    ->nullOnDelete();

                $table->index('empresa_id');
            });
        }

        $this->backfill();
    }

    private function backfill(): void
    {
        // 1) A partir da nota do fornecedor vinculada (compra_id -> empresa_id).
        if (Schema::hasTable('notas_fornecedores')) {
            $notas = DB::table('notas_fornecedores')
                ->whereNotNull('compra_id')
                ->whereNotNull('empresa_id')
                ->get(['compra_id', 'empresa_id']);

            foreach ($notas as $nota) {
                DB::table('compras')
                    ->where('id', $nota->compra_id)
                    ->whereNull('empresa_id')
                    ->update(['empresa_id' => $nota->empresa_id]);
            }
        }

        // 2) Se só existe uma empresa, o restante pertence a ela.
        $empresaIds = DB::table('empresas')->pluck('id');

        if ($empresaIds->count() === 1) {
            DB::table('compras')
                ->whereNull('empresa_id')
                ->update(['empresa_id' => (int) $empresaIds->first()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('compras', 'empresa_id')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->dropConstrainedForeignId('empresa_id');
            });
        }
    }
};
