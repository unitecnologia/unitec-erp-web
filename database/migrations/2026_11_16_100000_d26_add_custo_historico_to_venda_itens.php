<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D2.6 — Snapshot de custo histórico no item da venda (e espelhos PDV/devolução).
 * Não backfill. NULL + situacao=indisponivel quando não houver custo confiável.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addCustoCols('venda_itens');
        $this->addCustoCols('pdv_venda_itens');
        $this->addCustoCols('devolucao_venda_itens');
    }

    public function down(): void
    {
        $this->dropCustoCols('venda_itens');
        $this->dropCustoCols('pdv_venda_itens');
        $this->dropCustoCols('devolucao_venda_itens');
    }

    private function addCustoCols(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'custo_unitario')) {
                $blueprint->decimal('custo_unitario', 15, 4)->nullable();
            }
            if (! Schema::hasColumn($table, 'custo_total')) {
                $blueprint->decimal('custo_total', 15, 2)->nullable();
            }
            if (! Schema::hasColumn($table, 'custo_origem')) {
                $blueprint->string('custo_origem', 20)->nullable();
            }
            if (! Schema::hasColumn($table, 'custo_situacao')) {
                // ok | indisponivel | nao_aplicavel — null = venda antiga sem captura
                $blueprint->string('custo_situacao', 20)->nullable()->index();
            }
        });
    }

    private function dropCustoCols(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            foreach (['custo_unitario', 'custo_total', 'custo_origem', 'custo_situacao'] as $col) {
                if (Schema::hasColumn($table, $col)) {
                    $blueprint->dropColumn($col);
                }
            }
        });
    }
};
