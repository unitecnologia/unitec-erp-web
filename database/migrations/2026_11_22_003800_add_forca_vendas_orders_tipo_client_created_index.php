<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monitor: período (tipo + client_created_at) evita FULL SCAN + MATERIALIZE de pdv_venda_pagamentos
 * quando o filtro Meio Pgto está ativo. Medido em DEV: COUNT ~297ms → ~8ms (10–17/11 + cartões).
 */
return new class extends Migration
{
    private const TABLE = 'forca_vendas_orders';

    private const INDEX = 'fv_orders_tipo_client_created_idx';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->indexExists(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['tipo', 'client_created_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! $this->indexExists(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $definition) {
            if (($definition['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
