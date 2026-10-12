<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tab. Preço simplificada: itens = habilitação Varejo/Atacado/Especial.
 * Remove Valor/Fator; desativa tabelas extras; preserva FKs de clientes/vendedores.
 *
 * Nota: DROP COLUMN no MySQL faz commit implícito — DDL fica fora da transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('price_tables') || ! Schema::hasTable('product_price_table_items')) {
            return;
        }

        $this->ensurePadrao();

        $padrao = $this->mapPadraoIds();
        $varejoId = (int) ($padrao['varejo'] ?? 0);
        $atacadoId = (int) ($padrao['atacado'] ?? 0);
        $especialId = (int) ($padrao['especial'] ?? 0);

        if ($varejoId <= 0 || $atacadoId <= 0 || $especialId <= 0) {
            throw new RuntimeException(
                'Migration Tab. Preço: não foi possível garantir tabelas 1/2/3 (Varejo/Atacado/Especial).'
            );
        }

        $padraoIds = [$varejoId, $atacadoId, $especialId];

        // Dados + remapeamento (DML) em transação.
        DB::transaction(function () use ($padrao, $padraoIds, $varejoId): void {
            $this->remapForeignKeys($padraoIds, $varejoId, $padrao);

            $desired = [];

            foreach (DB::table('product_price_table_items')->get(['product_id', 'price_table_id']) as $item) {
                $productId = (int) $item->product_id;
                $nivel = $this->nivelDoItem((int) $item->price_table_id, $padrao) ?? 'varejo';
                $tableId = (int) ($padrao[$nivel] ?? $varejoId);
                $desired[$productId][$tableId] = true;
            }

            if (Schema::hasColumn('products', 'usa_tab_preco')) {
                foreach (DB::table('products')->where('usa_tab_preco', true)->pluck('id') as $productId) {
                    $productId = (int) $productId;

                    if (! isset($desired[$productId]) || $desired[$productId] === []) {
                        $desired[$productId] = [$varejoId => true];
                    }
                }
            }

            $hasValor = Schema::hasColumn('product_price_table_items', 'valor');
            $hasFator = Schema::hasColumn('product_price_table_items', 'fator');
            $now = now();

            foreach (DB::table('product_price_table_items')->get(['id', 'product_id', 'price_table_id']) as $row) {
                $productId = (int) $row->product_id;
                $tableId = (int) $row->price_table_id;

                if (! isset($desired[$productId][$tableId])) {
                    DB::table('product_price_table_items')->where('id', $row->id)->delete();
                }
            }

            foreach ($desired as $productId => $tables) {
                foreach (array_keys($tables) as $tableId) {
                    $exists = DB::table('product_price_table_items')
                        ->where('product_id', $productId)
                        ->where('price_table_id', $tableId)
                        ->exists();

                    if ($exists) {
                        if ($hasValor || $hasFator) {
                            $update = ['updated_at' => $now];

                            if ($hasValor) {
                                $update['valor'] = 0;
                            }

                            if ($hasFator) {
                                $update['fator'] = 0;
                            }

                            DB::table('product_price_table_items')
                                ->where('product_id', $productId)
                                ->where('price_table_id', $tableId)
                                ->update($update);
                        }

                        continue;
                    }

                    $insert = [
                        'product_id' => $productId,
                        'price_table_id' => $tableId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if ($hasValor) {
                        $insert['valor'] = 0;
                    }

                    if ($hasFator) {
                        $insert['fator'] = 0;
                    }

                    DB::table('product_price_table_items')->insert($insert);
                }
            }

            $now = now();

            DB::table('price_tables')
                ->whereNotIn('id', $padraoIds)
                ->update(['ativo' => false, 'updated_at' => $now]);

            DB::table('price_tables')
                ->whereIn('id', $padraoIds)
                ->update(['ativo' => true, 'updated_at' => $now]);
        });

        // DDL fora da transaction (MySQL commit implícito em DROP COLUMN).
        if (Schema::hasColumn('product_price_table_items', 'valor')) {
            Schema::table('product_price_table_items', function (Blueprint $table): void {
                $table->dropColumn('valor');
            });
        }

        if (Schema::hasColumn('product_price_table_items', 'fator')) {
            Schema::table('product_price_table_items', function (Blueprint $table): void {
                $table->dropColumn('fator');
            });
        }

        Cache::forget('erp.price_tables.padrao_ids');
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_price_table_items')) {
            return;
        }

        Schema::table('product_price_table_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_price_table_items', 'valor')) {
                $table->decimal('valor', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('product_price_table_items', 'fator')) {
                $table->decimal('fator', 12, 3)->default(0);
            }
        });
    }

    private function ensurePadrao(): void
    {
        $padrao = [
            ['codigo' => '1', 'descricao' => 'VAREJO'],
            ['codigo' => '2', 'descricao' => 'ATACADO'],
            ['codigo' => '3', 'descricao' => 'ESPECIAL'],
        ];

        foreach ($padrao as $meta) {
            $exists = DB::table('price_tables')->where('codigo', $meta['codigo'])->exists();

            if ($exists) {
                DB::table('price_tables')
                    ->where('codigo', $meta['codigo'])
                    ->update([
                        'descricao' => $meta['descricao'],
                        'ativo' => true,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('price_tables')->insert([
                'codigo' => $meta['codigo'],
                'descricao' => $meta['descricao'],
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array{varejo?: int, atacado?: int, especial?: int}
     */
    private function mapPadraoIds(): array
    {
        $map = [];

        foreach ([
            '1' => 'varejo',
            '2' => 'atacado',
            '3' => 'especial',
        ] as $codigo => $nivel) {
            $id = DB::table('price_tables')->where('codigo', $codigo)->value('id');

            if ($id) {
                $map[$nivel] = (int) $id;
            }
        }

        if (count($map) === 3) {
            return $map;
        }

        foreach (DB::table('price_tables')->orderBy('id')->get(['id', 'codigo', 'descricao']) as $row) {
            $nivel = $this->detectNivel((string) $row->codigo, (string) $row->descricao);

            if ($nivel !== null && ! isset($map[$nivel])) {
                $map[$nivel] = (int) $row->id;
            }
        }

        return $map;
    }

    /**
     * @param  list<int>  $padraoIds
     * @param  array{varejo?: int, atacado?: int, especial?: int}  $padrao
     */
    private function remapForeignKeys(array $padraoIds, int $varejoId, array $padrao): void
    {
        if (Schema::hasColumn('people', 'price_table_id')) {
            foreach (DB::table('people')
                ->whereNotNull('price_table_id')
                ->whereNotIn('price_table_id', $padraoIds)
                ->get(['id', 'price_table_id']) as $row) {
                $nivel = $this->nivelDoItem((int) $row->price_table_id, $padrao) ?? 'varejo';
                $novo = (int) ($padrao[$nivel] ?? $varejoId);
                DB::table('people')->where('id', $row->id)->update(['price_table_id' => $novo]);
            }

            DB::table('people')
                ->whereNotNull('price_table_id')
                ->whereNotIn('price_table_id', $padraoIds)
                ->update(['price_table_id' => $varejoId]);
        }

        if (Schema::hasColumn('vendedores', 'tabela_venda_id')) {
            foreach (DB::table('vendedores')
                ->whereNotNull('tabela_venda_id')
                ->whereNotIn('tabela_venda_id', $padraoIds)
                ->get(['id', 'tabela_venda_id']) as $row) {
                $nivel = $this->nivelDoItem((int) $row->tabela_venda_id, $padrao) ?? 'varejo';
                $novo = (int) ($padrao[$nivel] ?? $varejoId);
                DB::table('vendedores')->where('id', $row->id)->update(['tabela_venda_id' => $novo]);
            }

            DB::table('vendedores')
                ->whereNotNull('tabela_venda_id')
                ->whereNotIn('tabela_venda_id', $padraoIds)
                ->update(['tabela_venda_id' => $varejoId]);
        }
    }

    /**
     * @param  array{varejo?: int, atacado?: int, especial?: int}  $padrao
     */
    private function nivelDoItem(int $priceTableId, array $padrao): ?string
    {
        foreach ($padrao as $nivel => $id) {
            if ((int) $id === $priceTableId) {
                return $nivel;
            }
        }

        $row = DB::table('price_tables')->where('id', $priceTableId)->first(['codigo', 'descricao']);

        if (! $row) {
            return null;
        }

        return $this->detectNivel((string) $row->codigo, (string) $row->descricao);
    }

    private function detectNivel(string $codigo, string $descricao): ?string
    {
        $blob = mb_strtoupper(trim($codigo.' '.$descricao), 'UTF-8');

        if (str_contains($blob, 'ESPECIAL') || $codigo === '3') {
            return 'especial';
        }

        if (str_contains($blob, 'ATACADO') || $codigo === '2') {
            return 'atacado';
        }

        if (
            str_contains($blob, 'VAREJO')
            || str_contains($blob, 'PADRAO')
            || str_contains($blob, 'PADRÃO')
            || $codigo === '1'
        ) {
            return 'varejo';
        }

        return null;
    }
};
