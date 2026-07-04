<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Padroniza o vínculo de vendedor do orçamento.
 *
 * Antes, "orcamentos.vendedor_id" apontava para a tabela "people", enquanto
 * vendas, PDV, Força de Vendas e o usuário logado (users.vendedor_id) usam a
 * tabela "vendedores". Isso gerava vendedor errado/divergente no orçamento e
 * impedia herdar o vendedor do usuário logado. Aqui:
 *   1) troca a FK de people -> vendedores;
 *   2) backfill dos orçamentos do app a partir de forca_vendas_orders (fonte
 *      confiável: já está em "vendedores");
 *   3) backfill dos orçamentos do ERP convertendo o id de "people" para o
 *      "vendedores" equivalente (casando por CPF e, em último caso, por nome).
 *
 * Idempotente: usa Schema/query builder (respeitam o prefixo de tabela).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Remove a FK antiga (orcamentos.vendedor_id -> people).
        $this->dropForeignByReference('orcamentos', 'vendedor_id', 'people');

        // 2) Backfill ERP: converte ids de "people" para o "vendedores" equivalente.
        $this->backfillFromPeople();

        // 3) Backfill App: forca_vendas_orders.vendedor_id já é da tabela "vendedores".
        DB::table('forca_vendas_orders')
            ->whereNotNull('orcamento_id')
            ->whereNotNull('vendedor_id')
            ->orderBy('id')
            ->chunk(200, function ($orders): void {
                foreach ($orders as $order) {
                    DB::table('orcamentos')
                        ->where('id', $order->orcamento_id)
                        ->update(['vendedor_id' => $order->vendedor_id]);
                }
            });

        // 4) Zera ids que não existam em "vendedores" (segurança antes da FK).
        DB::table('orcamentos')
            ->whereNotNull('vendedor_id')
            ->whereNotIn('vendedor_id', fn ($q) => $q->select('id')->from('vendedores'))
            ->update(['vendedor_id' => null]);

        // 5) Nova FK -> vendedores.
        if (! $this->hasForeignReference('orcamentos', 'vendedor_id', 'vendedores')) {
            Schema::table('orcamentos', function (Blueprint $table): void {
                $table->foreign('vendedor_id')->references('id')->on('vendedores')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->dropForeignByReference('orcamentos', 'vendedor_id', 'vendedores');

        // Os ids agora são de "vendedores"; o caminho de volta não tem
        // correspondência confiável, então zeramos antes de religar a FK.
        DB::table('orcamentos')
            ->whereNotNull('vendedor_id')
            ->whereNotIn('vendedor_id', fn ($q) => $q->select('id')->from('people'))
            ->update(['vendedor_id' => null]);

        if (! $this->hasForeignReference('orcamentos', 'vendedor_id', 'people')) {
            Schema::table('orcamentos', function (Blueprint $table): void {
                $table->foreign('vendedor_id')->references('id')->on('people')->nullOnDelete();
            });
        }
    }

    /**
     * Converte os vendedor_id atuais (ids de "people") para o "vendedores"
     * equivalente, casando primeiro por CPF e depois por nome.
     */
    private function backfillFromPeople(): void
    {
        $peopleIds = DB::table('orcamentos')
            ->whereNotNull('vendedor_id')
            ->distinct()
            ->pluck('vendedor_id');

        if ($peopleIds->isEmpty()) {
            return;
        }

        $people = DB::table('people')
            ->whereIn('id', $peopleIds)
            ->get(['id', 'cpf_cnpj', 'nome_razao']);

        $vendedores = DB::table('vendedores')->get(['id', 'cpf', 'nome']);

        $byCpf = [];
        $byNome = [];
        foreach ($vendedores as $vendedor) {
            $cpf = preg_replace('/\D/', '', (string) $vendedor->cpf);
            if ($cpf !== '') {
                $byCpf[$cpf] ??= $vendedor->id;
            }
            $nome = mb_strtoupper(trim((string) $vendedor->nome), 'UTF-8');
            if ($nome !== '') {
                $byNome[$nome] ??= $vendedor->id;
            }
        }

        // people.id => vendedores.id
        $map = [];
        foreach ($people as $person) {
            $cpf = preg_replace('/\D/', '', (string) $person->cpf_cnpj);
            $nome = mb_strtoupper(trim((string) $person->nome_razao), 'UTF-8');

            $vendedorId = ($cpf !== '' ? ($byCpf[$cpf] ?? null) : null)
                ?? ($nome !== '' ? ($byNome[$nome] ?? null) : null);

            if ($vendedorId !== null) {
                $map[$person->id] = $vendedorId;
            }
        }

        if ($map === []) {
            return;
        }

        // Usa snapshot por linha para evitar remapeamento em cascata.
        DB::table('orcamentos')
            ->whereNotNull('vendedor_id')
            ->orderBy('id')
            ->chunk(200, function ($orcamentos) use ($map): void {
                foreach ($orcamentos as $orcamento) {
                    if (isset($map[$orcamento->vendedor_id])) {
                        DB::table('orcamentos')
                            ->where('id', $orcamento->id)
                            ->update(['vendedor_id' => $map[$orcamento->vendedor_id]]);
                    }
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
