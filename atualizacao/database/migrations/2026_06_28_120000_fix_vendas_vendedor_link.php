<?php

use App\Models\ForcaVendasOrder;
use App\Models\PdvVenda;
use App\Models\Venda;
use App\Models\Vendedor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige o vínculo de vendedor da tabela "vendas".
 *
 * A tabela "vendas" só é populada pelo PDV e pela Força de Vendas, e ambos
 * usam a tabela "vendedores". Porém a FK apontava para "people", fazendo a
 * relação Venda::vendedor() resolver o id de vendedor contra a tabela de
 * pessoas (aparecia um cliente como vendedor). Aqui:
 *   1) trocamos a FK de people -> vendedores;
 *   2) guardamos um snapshot (vendedor_nome) e a forma de pagamento;
 *   3) fazemos o backfill dos dados já gravados (FV e PDV).
 *
 * Idempotente: usa o query builder/Schema (que respeitam o prefixo de tabela).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendas', 'vendedor_nome')) {
                $table->string('vendedor_nome')->nullable()->after('vendedor_id');
            }

            if (! Schema::hasColumn('vendas', 'forma_pagamento')) {
                $table->string('forma_pagamento')->nullable()->after('total');
            }
        });

        $this->dropForeignByReference('vendas', 'vendedor_id', 'people');

        // Zera ids que não existam na tabela de vendedores (segurança antes da FK).
        DB::table('vendas')
            ->whereNotNull('vendedor_id')
            ->whereNotIn('vendedor_id', fn ($q) => $q->select('id')->from('vendedores'))
            ->update(['vendedor_id' => null]);

        if (! $this->hasForeignReference('vendas', 'vendedor_id', 'vendedores')) {
            Schema::table('vendas', function (Blueprint $table): void {
                $table->foreign('vendedor_id')->references('id')->on('vendedores')->nullOnDelete();
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        $this->dropForeignByReference('vendas', 'vendedor_id', 'vendedores');

        Schema::table('vendas', function (Blueprint $table): void {
            if (Schema::hasColumn('vendas', 'vendedor_nome')) {
                $table->dropColumn('vendedor_nome');
            }

            if (Schema::hasColumn('vendas', 'forma_pagamento')) {
                $table->dropColumn('forma_pagamento');
            }
        });

        DB::table('vendas')
            ->whereNotNull('vendedor_id')
            ->whereNotIn('vendedor_id', fn ($q) => $q->select('id')->from('people'))
            ->update(['vendedor_id' => null]);

        if (! $this->hasForeignReference('vendas', 'vendedor_id', 'people')) {
            Schema::table('vendas', function (Blueprint $table): void {
                $table->foreign('vendedor_id')->references('id')->on('people')->nullOnDelete();
            });
        }
    }

    private function backfill(): void
    {
        // Força de Vendas: forma vem do payload do pedido; nome do vendedor da tabela vendedores.
        ForcaVendasOrder::query()
            ->whereNotNull('venda_id')
            ->with('vendedor')
            ->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    $venda = Venda::query()->find($order->venda_id);

                    if (! $venda) {
                        continue;
                    }

                    $venda->forma_pagamento ??= $order->payload['forma_pagamento'] ?? null;
                    $venda->vendedor_nome ??= $order->vendedor?->nome;
                    $venda->save();
                }
            });

        // PDV: vendedor e forma vêm do cupom espelhado.
        PdvVenda::query()
            ->whereNotNull('venda_id')
            ->chunkById(200, function ($cupons): void {
                foreach ($cupons as $cupom) {
                    $venda = Venda::query()->find($cupom->venda_id);

                    if (! $venda) {
                        continue;
                    }

                    $venda->vendedor_id ??= $cupom->vendedor_id;
                    $venda->vendedor_nome ??= $cupom->vendedor_nome;
                    $venda->forma_pagamento ??= $cupom->forma_pagamento;
                    $venda->save();
                }
            });

        // Completa o snapshot de nome a partir do cadastro de vendedores.
        Venda::query()
            ->whereNotNull('vendedor_id')
            ->whereNull('vendedor_nome')
            ->chunkById(200, function ($vendas): void {
                $nomes = Vendedor::query()->pluck('nome', 'id');

                foreach ($vendas as $venda) {
                    $venda->vendedor_nome = $nomes[$venda->vendedor_id] ?? null;
                    $venda->save();
                }
            });
    }

    private function hasForeignReference(string $table, string $column, string $referenced): bool
    {
        $prefixed = DB::getTablePrefix() . $table;
        $referencedPrefixed = DB::getTablePrefix() . $referenced;

        $rows = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?',
            [$prefixed, $column, $referencedPrefixed],
        );

        return ! empty($rows);
    }

    private function dropForeignByReference(string $table, string $column, string $referenced): void
    {
        $prefixed = DB::getTablePrefix() . $table;
        $referencedPrefixed = DB::getTablePrefix() . $referenced;

        $rows = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?',
            [$prefixed, $column, $referencedPrefixed],
        );

        foreach ($rows as $row) {
            Schema::table($table, function (Blueprint $blueprint) use ($row): void {
                $blueprint->dropForeign($row->CONSTRAINT_NAME);
            });
        }
    }
};
