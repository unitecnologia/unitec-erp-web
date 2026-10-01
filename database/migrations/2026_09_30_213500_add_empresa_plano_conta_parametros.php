<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return array<string, array{codigo: int, dc: string}>
     */
    private function columns(): array
    {
        return [
            'param_plano_conta_venda_id' => ['codigo' => 101, 'dc' => 'C'],
            'param_plano_conta_compra_id' => ['codigo' => 201, 'dc' => 'D'],
            'param_plano_conta_taxa_cartao_id' => ['codigo' => 209, 'dc' => 'D'],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('empresas') || ! Schema::hasTable('planos_contas')) {
            return;
        }

        foreach (array_keys($this->columns()) as $column) {
            if (Schema::hasColumn('empresas', $column)) {
                continue;
            }

            Schema::table('empresas', function (Blueprint $table) use ($column): void {
                $table->foreignId($column)
                    ->nullable()
                    ->constrained('planos_contas')
                    ->nullOnDelete();
            });
        }

        foreach ($this->columns() as $column => $match) {
            if (! Schema::hasColumn('empresas', $column)) {
                continue;
            }

            $planoId = DB::table('planos_contas')
                ->where('codigo', $match['codigo'])
                ->where('dc', $match['dc'])
                ->where('ativo', true)
                ->value('id');

            if (! $planoId) {
                continue;
            }

            DB::table('empresas')
                ->whereNull($column)
                ->update([$column => $planoId]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        foreach (array_keys($this->columns()) as $column) {
            if (! Schema::hasColumn('empresas', $column)) {
                continue;
            }

            Schema::table('empresas', function (Blueprint $table) use ($column): void {
                $table->dropConstrainedForeignId($column);
            });
        }
    }
};
