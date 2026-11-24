<?php

namespace App\Support\Erp\Queries;

use App\Models\ContaPagarPagamento;
use App\Models\ContaReceberPagamento;
use App\Models\FormaPagamento;
use App\Models\ForcaVendasOrder;
use App\Models\OrdemServico;
use App\Models\PdvCaixaMovimento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Filtra lançamentos do Livro Caixa pela forma de pagamento real vinculada
 * (pagamentos CR/CP, payload FV, OS faturada, movimento PDV) — não pelo texto do histórico.
 */
final class CaixaLancamentoMeioPagamentoFiltro
{
    /**
     * @return list<string>
     */
    public static function opcoesSelect(): array
    {
        return FormaPagamento::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->pluck('descricao')
            ->map(fn (mixed $d): string => mb_strtoupper(trim((string) $d), 'UTF-8'))
            ->filter(fn (string $d): bool => $d !== '')
            ->unique()
            ->values()
            ->all();
    }

    public static function apply(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');
        if ($term === '' || $term === 'TODOS') {
            return;
        }

        $formas = self::resolverFormas($term);
        if ($formas['ids'] === [] && $formas['labels'] === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $table = $query->getModel()->getTable();
        $outer = self::tabelaFisica($table);

        $query->where(function (Builder $group) use ($formas, $table, $outer): void {
            $matched = false;

            if (self::aplicarViaColunaForma($group, $formas, $table)) {
                $matched = true;
            }
            if (self::aplicarViaContaReceberPagamentos($group, $formas, $table, $outer)) {
                $matched = true;
            }
            if (self::aplicarViaContaPagarPagamentos($group, $formas, $table, $outer)) {
                $matched = true;
            }
            if (self::aplicarViaForcaVendas($group, $formas, $table, $outer)) {
                $matched = true;
            }
            if (self::aplicarViaOrdensServico($group, $formas, $table, $outer)) {
                $matched = true;
            }
            if (self::aplicarViaPdvMovimentos($group, $formas, $table, $outer)) {
                $matched = true;
            }

            if (! $matched) {
                $group->whereRaw('0 = 1');
            }
        });
    }

    /**
     * @param  array{ids: list<int>, labels: list<string>}  $formas
     */
    private static function aplicarViaColunaForma(Builder $group, array $formas, string $table): bool
    {
        if ($formas['ids'] === [] || ! Schema::hasColumn($table, 'forma_pagamento_id')) {
            return false;
        }

        $group->orWhereIn($table.'.forma_pagamento_id', $formas['ids']);

        return true;
    }

    private static function tabelaFisica(string $logical): string
    {
        return DB::getTablePrefix().$logical;
    }

    /**
     * @return array{ids: list<int>, labels: list<string>}
     */
    private static function resolverFormas(string $term): array
    {
        $rows = FormaPagamento::query()
            ->where(function (Builder $w) use ($term): void {
                $w->whereRaw('UPPER(TRIM(descricao)) = ?', [$term])
                    ->orWhereRaw('UPPER(TRIM(descricao)) LIKE ?', [$term.'%']);
            })
            ->get(['id', 'descricao']);

        $ids = [];
        $labels = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row->id;
            $label = mb_strtoupper(trim((string) $row->descricao), 'UTF-8');
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        if ($term !== '' && ! in_array($term, $labels, true)) {
            $labels[] = $term;
        }

        return [
            'ids' => array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0))),
            'labels' => array_values(array_unique(array_filter($labels))),
        ];
    }

    /**
     * @param  array{ids: list<int>, labels: list<string>}  $formas
     */
    private static function aplicarViaContaReceberPagamentos(
        Builder $group,
        array $formas,
        string $table,
        string $outer,
    ): bool {
        if ($formas['ids'] === [] || ! Schema::hasTable((new ContaReceberPagamento)->getTable())) {
            return false;
        }
        if (! Schema::hasTable('contas_receber')) {
            return false;
        }

        $pagTable = (new ContaReceberPagamento)->getTable();

        $idsList = implode(',', array_map('intval', $formas['ids']));
        $group->orWhereExists(function (QueryBuilder $sub) use ($idsList, $outer, $pagTable): void {
            $sub->selectRaw('1')
                ->from(DB::raw('`'.self::tabelaFisica($pagTable).'` as `p`'))
                ->join(DB::raw('`'.self::tabelaFisica('contas_receber').'` as `cr`'), DB::raw('`cr`.`id`'), '=', DB::raw('`p`.`conta_receber_id`'))
                ->whereRaw('`cr`.`documento` = `'.$outer.'`.`documento`')
                ->whereRaw('`p`.`forma_pagamento_id` IN ('.$idsList.')')
                ->whereRaw(
                    'ABS(COALESCE(`p`.`valor_recebido`, 0) - (COALESCE(`'.$outer.'`.`entrada`, 0) + COALESCE(`'.$outer.'`.`saida`, 0))) < 0.015'
                );
        });

        return true;
    }

    /**
     * @param  array{ids: list<int>, labels: list<string>}  $formas
     */
    private static function aplicarViaContaPagarPagamentos(
        Builder $group,
        array $formas,
        string $table,
        string $outer,
    ): bool {
        if ($formas['ids'] === [] || ! Schema::hasTable((new ContaPagarPagamento)->getTable())) {
            return false;
        }
        if (! Schema::hasTable('contas_pagar')) {
            return false;
        }

        $pagTable = (new ContaPagarPagamento)->getTable();

        $idsList = implode(',', array_map('intval', $formas['ids']));
        $group->orWhereExists(function (QueryBuilder $sub) use ($idsList, $outer, $pagTable): void {
            $sub->selectRaw('1')
                ->from(DB::raw('`'.self::tabelaFisica($pagTable).'` as `p`'))
                ->join(DB::raw('`'.self::tabelaFisica('contas_pagar').'` as `cp`'), DB::raw('`cp`.`id`'), '=', DB::raw('`p`.`conta_pagar_id`'))
                ->whereRaw('`cp`.`documento` = `'.$outer.'`.`documento`')
                ->whereRaw('`p`.`forma_pagamento_id` IN ('.$idsList.')')
                ->whereRaw(
                    'ABS(COALESCE(`p`.`valor_pago`, 0) - (COALESCE(`'.$outer.'`.`entrada`, 0) + COALESCE(`'.$outer.'`.`saida`, 0))) < 0.015'
                );
        });

        return true;
    }

    /**
     * @param  array{ids: list<int>, labels: list<string>}  $formas
     */
    private static function aplicarViaForcaVendas(
        Builder $group,
        array $formas,
        string $table,
        string $outer,
    ): bool {
        if (! Schema::hasTable((new ForcaVendasOrder)->getTable())) {
            return false;
        }

        $fvTable = (new ForcaVendasOrder)->getTable();
        $driver = $group->getConnection()->getDriverName();

        $group->orWhere(function (Builder $fv) use ($formas, $table, $outer, $fvTable, $driver): void {
            $fv->where($table.'.documento', 'like', 'FV-%');

            if ($driver === 'sqlite') {
                $docs = self::documentosFvCompativeisSqlite($formas);
                if ($docs === []) {
                    $fv->whereRaw('0 = 1');

                    return;
                }
                $fv->whereIn($table.'.documento', $docs);

                return;
            }

            $fv->whereExists(function (QueryBuilder $sub) use ($formas, $outer, $fvTable): void {
                $doc = '`'.$outer.'`.documento';
                $sub->selectRaw('1')
                    ->from(DB::raw('`'.self::tabelaFisica($fvTable).'` as `o`'))
                    ->whereRaw(
                        "o.id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(UPPER(TRIM({$doc})), 'ESTORNO ', ''), '/', 1), '-', -1) AS UNSIGNED)"
                    )
                    ->where(function (QueryBuilder $match) use ($formas, $doc): void {
                        $match->where(function (QueryBuilder $misto) use ($formas, $doc): void {
                            $misto->whereRaw("UPPER(TRIM({$doc})) REGEXP '^FV-[0-9]+/P[0-9]+'")
                                ->where(function (QueryBuilder $line) use ($formas, $doc): void {
                                    $idxExpr = "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(UPPER(TRIM({$doc})), '/P', -1), '/', 1) AS UNSIGNED) - 1";
                                    if ($formas['ids'] !== []) {
                                        $idsList = implode(',', array_map('intval', $formas['ids']));
                                        $line->orWhereRaw(
                                            "CAST(JSON_UNQUOTE(JSON_EXTRACT(o.payload, CONCAT('$.pagamentos[', {$idxExpr}, '].forma_pagamento_id'))) AS UNSIGNED) IN ({$idsList})"
                                        );
                                    }
                                    foreach ($formas['labels'] as $label) {
                                        $line->orWhereRaw(
                                            "UPPER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(o.payload, CONCAT('$.pagamentos[', {$idxExpr}, '].forma_pagamento'))))) = ?",
                                            [$label]
                                        );
                                        $line->orWhereRaw(
                                            "UPPER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(o.payload, CONCAT('$.pagamentos[', {$idxExpr}, '].descricao'))))) = ?",
                                            [$label]
                                        );
                                    }
                                });
                        });

                        $match->orWhere(function (QueryBuilder $raiz) use ($formas, $doc): void {
                            $raiz->whereRaw("UPPER(TRIM({$doc})) NOT REGEXP '^FV-[0-9]+/P[0-9]+'")
                                ->where(function (QueryBuilder $any) use ($formas): void {
                                    if ($formas['ids'] !== []) {
                                        $idsList = implode(',', array_map('intval', $formas['ids']));
                                        $any->orWhereRaw(
                                            "CAST(JSON_UNQUOTE(JSON_EXTRACT(o.payload, '$.forma_pagamento_id')) AS UNSIGNED) IN ({$idsList})"
                                        );
                                        foreach ($formas['ids'] as $id) {
                                            $any->orWhereRaw(
                                                "JSON_CONTAINS(JSON_EXTRACT(o.payload, '$.pagamentos'), ?, '$')",
                                                [json_encode(['forma_pagamento_id' => $id], JSON_UNESCAPED_UNICODE)]
                                            );
                                        }
                                    }
                                    foreach ($formas['labels'] as $label) {
                                        $any->orWhereRaw(
                                            "UPPER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(o.payload, '$.forma_pagamento')))) = ?",
                                            [$label]
                                        );
                                    }
                                });
                        });
                    });
            });
        });

        return true;
    }

    /**
     * @param  array{ids: list<int>, labels: list<string>}  $formas
     * @return list<string>
     */
    private static function documentosFvCompativeisSqlite(array $formas): array
    {
        $docs = [];
        $idSet = array_fill_keys($formas['ids'], true);
        $labelSet = array_fill_keys($formas['labels'], true);

        ForcaVendasOrder::query()
            ->orderBy('id')
            ->select(['id', 'payload'])
            ->chunkById(100, function ($rows) use (&$docs, $idSet, $labelSet): void {
                foreach ($rows as $row) {
                    $payload = is_array($row->payload)
                        ? $row->payload
                        : (json_decode((string) $row->payload, true) ?: []);
                    $orderId = (int) $row->id;
                    $base = 'FV-'.$orderId;
                    $pags = is_array($payload['pagamentos'] ?? null) ? $payload['pagamentos'] : [];

                    if ($pags === []) {
                        if (self::payloadFormaCombina($payload, $idSet, $labelSet)) {
                            $docs[] = $base;
                        }

                        continue;
                    }

                    if (count($pags) === 1) {
                        if (self::payloadFormaCombina($pags[0], $idSet, $labelSet)
                            || self::payloadFormaCombina($payload, $idSet, $labelSet)
                        ) {
                            $docs[] = $base;
                        }

                        continue;
                    }

                    foreach ($pags as $i => $pag) {
                        if (! is_array($pag) || ! self::payloadFormaCombina($pag, $idSet, $labelSet)) {
                            continue;
                        }
                        $docs[] = $base.'/P'.($i + 1);
                    }
                }
            });

        return array_values(array_unique($docs));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, true>  $idSet
     * @param  array<string, true>  $labelSet
     */
    private static function payloadFormaCombina(array $payload, array $idSet, array $labelSet): bool
    {
        $id = (int) ($payload['forma_pagamento_id'] ?? $payload['id'] ?? 0);
        if ($id > 0 && isset($idSet[$id])) {
            return true;
        }

        foreach (['forma_pagamento', 'descricao', 'forma'] as $key) {
            $label = mb_strtoupper(trim((string) ($payload[$key] ?? '')), 'UTF-8');
            if ($label !== '' && isset($labelSet[$label])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Snapshot OS: [{forma, valor, parcelas}].
     *
     * @param  array{ids: list<int>, labels: list<string>}  $formas
     */
    private static function aplicarViaOrdensServico(
        Builder $group,
        array $formas,
        string $table,
        string $outer,
    ): bool {
        if ($formas['labels'] === [] || ! Schema::hasTable((new OrdemServico)->getTable())) {
            return false;
        }

        $osTable = (new OrdemServico)->getTable();
        if (! Schema::hasColumn($osTable, 'faturamento_pagamentos')) {
            return false;
        }
        if ($group->getConnection()->getDriverName() === 'sqlite') {
            return false;
        }

        $group->orWhere(function (Builder $osQ) use ($formas, $table, $outer, $osTable): void {
            $osQ->where($table.'.documento', 'like', 'OS-%')
                ->where(function (Builder $byLabel) use ($formas, $outer, $osTable): void {
                    foreach ($formas['labels'] as $label) {
                        $byLabel->orWhereExists(function (QueryBuilder $sub) use ($label, $outer, $osTable): void {
                            $sub->selectRaw('1')
                                ->from(DB::raw('`'.self::tabelaFisica($osTable).'` as `os`'))
                                ->whereRaw(
                                    "CAST(REGEXP_REPLACE(CAST(os.numero AS CHAR), '[^0-9]', '') AS UNSIGNED)
                                     = CAST(REGEXP_REPLACE(`{$outer}`.documento, '[^0-9]', '') AS UNSIGNED)"
                                )
                                ->where(function (QueryBuilder $json) use ($label, $outer): void {
                                    $json->whereRaw(
                                        "JSON_CONTAINS(os.faturamento_pagamentos, JSON_OBJECT('forma', ?, 'valor', ROUND(COALESCE(`{$outer}`.entrada, 0) + COALESCE(`{$outer}`.saida, 0), 2)))",
                                        [$label]
                                    )->orWhereRaw(
                                        "JSON_CONTAINS(os.faturamento_pagamentos, JSON_OBJECT('forma', ?, 'valor', CAST(ROUND(COALESCE(`{$outer}`.entrada, 0) + COALESCE(`{$outer}`.saida, 0), 2) AS CHAR)))",
                                        [$label]
                                    );
                                });
                        });
                    }
                });
        });

        return true;
    }

    /**
     * @param  array{ids: list<int>, labels: list<string>}  $formas
     */
    private static function aplicarViaPdvMovimentos(
        Builder $group,
        array $formas,
        string $table,
        string $outer,
    ): bool {
        if ($formas['labels'] === [] || ! Schema::hasTable((new PdvCaixaMovimento)->getTable())) {
            return false;
        }

        $movTable = (new PdvCaixaMovimento)->getTable();

        $group->orWhere(function (Builder $pdv) use ($formas, $table, $outer, $movTable): void {
            $pdv->where($table.'.id', '<', 0)
                ->whereExists(function (QueryBuilder $sub) use ($formas, $outer, $movTable): void {
                    $sub->selectRaw('1')
                        ->from(DB::raw('`'.self::tabelaFisica($movTable).'` as `m`'))
                        ->whereRaw('m.id = ABS(`'.$outer.'`.id)')
                        ->where(function (QueryBuilder $f) use ($formas): void {
                            foreach ($formas['labels'] as $label) {
                                $f->orWhereRaw('UPPER(TRIM(m.forma_pagamento)) = ?', [$label]);
                                $f->orWhereRaw('UPPER(TRIM(m.forma_pagamento)) LIKE ?', [$label.'%']);
                            }
                        });
                });
        });

        return true;
    }
}
