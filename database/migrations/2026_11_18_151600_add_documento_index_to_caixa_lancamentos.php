<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice em caixa_lancamentos.documento para idempotência do faturamento (FV-*).
 * Equality + LIKE 'prefix/%' passam a usar range no índice (sem full scan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caixa_lancamentos')) {
            return;
        }

        if ($this->indexExists('caixa_lancamentos', 'caixa_lancamentos_documento_index')) {
            return;
        }

        Schema::table('caixa_lancamentos', function (Blueprint $table): void {
            $table->index('documento', 'caixa_lancamentos_documento_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('caixa_lancamentos')) {
            return;
        }

        if (! $this->indexExists('caixa_lancamentos', 'caixa_lancamentos_documento_index')) {
            return;
        }

        Schema::table('caixa_lancamentos', function (Blueprint $table): void {
            $table->dropIndex('caixa_lancamentos_documento_index');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $row) {
            if (($row['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
