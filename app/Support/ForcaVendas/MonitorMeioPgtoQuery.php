<?php

namespace App\Support\ForcaVendas;

use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Support\Erp\ErpSchema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtro Meio Pgto do Monitor: prioriza pagamentos reais (PDV / payload.pagamentos),
 * com fallback ao cabeçalho só quando não há detalhe. Multi-seleção = OR (pelo menos uma).
 *
 * Desempenho: um EXISTS de PDV + um EXISTS de JSON + um fallback — não repete por forma.
 */
final class MonitorMeioPgtoQuery
{
    /**
     * @param  list<string>  $valores  IDs do cadastro (ex.: CARTAO CREDITO|DINHEIRO já parseados)
     */
    public static function apply(Builder $query, array $valores): void
    {
        $valores = array_values(array_filter(array_map(
            static fn (mixed $v): string => trim((string) $v),
            $valores,
        ), static fn (string $v): bool => $v !== '' && $v !== 'todos'));

        if ($valores === []) {
            return;
        }

        $driver = $query->getConnection()->getDriverName();
        $hasPdvPagamentos = ErpSchema::hasTable((new PdvVendaPagamento)->getTable())
            && ErpSchema::hasTable((new PdvVenda)->getTable());

        $query->where(function (Builder $outer) use ($valores, $driver, $hasPdvPagamentos): void {
            if ($hasPdvPagamentos) {
                self::orWherePdvPagamentosMatchAny($outer, $valores);
            }

            self::orWherePayloadPagamentosMatchAny($outer, $valores, $driver);

            $outer->orWhere(function (Builder $fallback) use ($valores, $driver, $hasPdvPagamentos): void {
                self::whereSemPagamentosDetalhados($fallback, $driver, $hasPdvPagamentos);
                $fallback->where(function (Builder $cab) use ($valores, $driver): void {
                    $formaPayloadExpr = $driver === 'sqlite'
                        ? "json_extract(payload, '$.forma_pagamento')"
                        : "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.forma_pagamento'))";
                    self::whereColumnMatchesAnyForma($cab, $formaPayloadExpr, $valores);
                    $cab->orWhereHas('pedido', function (Builder $pedido) use ($valores): void {
                        self::whereColumnMatchesAnyForma($pedido, 'forma_pagamento', $valores);
                    });
                });
            });
        });
    }

    /**
     * PHP: a forma persistida corresponde ao item selecionado no filtro?
     */
    public static function formaCorresponde(string $formaPersistida, string $filtroSelecionado): bool
    {
        $forma = self::normalizarChave($formaPersistida);
        if ($forma === '') {
            return false;
        }

        foreach (self::partesFormaComposta($formaPersistida) as $parte) {
            if (self::chaveCorrespondeFiltro(self::normalizarChave($parte), $filtroSelecionado)) {
                return true;
            }
        }

        return self::chaveCorrespondeFiltro($forma, $filtroSelecionado);
    }

    /**
     * @return list<string>
     */
    public static function variantesBusca(string $valor): array
    {
        $valor = trim($valor);
        if ($valor === '') {
            return [];
        }

        $lower = mb_strtolower($valor, 'UTF-8');
        $ascii = mb_strtolower(self::normalizarChave($valor), 'UTF-8');

        return array_values(array_unique(array_filter([$lower, $ascii], static fn (string $v): bool => $v !== '')));
    }

    public static function normalizarChave(string $descricao): string
    {
        $chave = mb_strtoupper(trim($descricao), 'UTF-8');

        return str_replace(
            ['Á', 'À', 'Ã', 'Â', 'É', 'Ê', 'Í', 'Ó', 'Ô', 'Õ', 'Ú', 'Ç'],
            ['A', 'A', 'A', 'A', 'E', 'E', 'I', 'O', 'O', 'O', 'U', 'C'],
            $chave,
        );
    }

    public static function familiaFiltro(string $filtroSelecionado): string
    {
        $chave = self::normalizarChave($filtroSelecionado);

        if ($chave === 'PIX' || str_starts_with($chave, 'PIX ')) {
            return 'pix';
        }

        if ($chave === 'DINHEIRO' || str_starts_with($chave, 'DINHEIRO ')) {
            return 'dinheiro';
        }

        if ($chave === 'TROCA' || str_contains($chave, 'TROCA')) {
            return 'troca';
        }

        if (str_contains($chave, 'DEBITO')) {
            return 'debito';
        }

        if (str_contains($chave, 'CREDITO') && ! str_contains($chave, 'CREDIARIO')) {
            return 'credito';
        }

        if ($chave === 'CREDIARIO' || str_contains($chave, 'CREDIARIO')) {
            return 'crediario';
        }

        return 'outro';
    }

    private static function chaveCorrespondeFiltro(string $chaveForma, string $filtroSelecionado): bool
    {
        if ($chaveForma === '') {
            return false;
        }

        $familia = self::familiaFiltro($filtroSelecionado);
        $chaveFiltro = self::normalizarChave($filtroSelecionado);

        return match ($familia) {
            'credito' => self::ehCredito($chaveForma),
            'debito' => self::ehDebito($chaveForma),
            'dinheiro' => $chaveForma === 'DINHEIRO' || str_starts_with($chaveForma, 'DINHEIRO '),
            'pix' => $chaveForma === 'PIX' || str_starts_with($chaveForma, 'PIX '),
            'troca' => $chaveForma === 'TROCA' || str_contains($chaveForma, 'TROCA'),
            'crediario' => str_contains($chaveForma, 'CREDIARIO'),
            default => $chaveForma === $chaveFiltro
                || str_contains($chaveForma, $chaveFiltro)
                || str_contains($chaveFiltro, $chaveForma),
        };
    }

    private static function ehCredito(string $chave): bool
    {
        if (str_contains($chave, 'CREDIARIO')) {
            return false;
        }

        if ($chave === 'CREDITO') {
            return true;
        }

        return str_contains($chave, 'CREDITO')
            && (str_contains($chave, 'CARTAO') || str_contains($chave, 'POS'));
    }

    private static function ehDebito(string $chave): bool
    {
        if ($chave === 'DEBITO') {
            return true;
        }

        return str_contains($chave, 'DEBITO')
            && (str_contains($chave, 'CARTAO') || str_contains($chave, 'POS'));
    }

    /**
     * @return list<string>
     */
    private static function partesFormaComposta(string $forma): array
    {
        $partes = preg_split('/\s*(?:\/|\+|,)\s*/u', trim($forma)) ?: [];

        return array_values(array_filter(array_map(
            static fn (mixed $p): string => trim((string) $p),
            $partes,
        ), static fn (string $p): bool => $p !== ''));
    }

    /**
     * EXISTS direto (evita whereHas triplo) — caminho principal SCV/PDV.
     *
     * @param  list<string>  $valores
     */
    private static function orWherePdvPagamentosMatchAny(Builder $query, array $valores): void
    {
        $prefix = $query->getConnection()->getTablePrefix();
        $orders = $prefix.$query->getModel()->getTable();
        $pdv = $prefix.(new PdvVenda)->getTable();
        $pag = $prefix.(new PdvVendaPagamento)->getTable();
        $reg = PdvVenda::ORIGEM_REGULARIZACAO_FISCAL;

        $bindings = [];
        $matchSql = self::sqlMatchAnyClauses('LOWER(pp.forma)', $valores, $bindings);

        $query->orWhereRaw(
            "EXISTS (
                SELECT 1
                FROM {$pag} AS pp
                INNER JOIN {$pdv} AS pv ON pv.id = pp.pdv_venda_id
                WHERE pv.venda_id = {$orders}.venda_id
                  AND pp.valor > 0
                  AND (pv.origem IS NULL OR pv.origem != ?)
                  AND ({$matchSql})
            )",
            array_merge([$reg], $bindings),
        );
    }

    /**
     * @param  list<string>  $valores
     */
    private static function orWherePayloadPagamentosMatchAny(Builder $query, array $valores, string $driver): void
    {
        $bindings = [];
        $matchSql = self::sqlMatchAnyClauses(
            "LOWER(COALESCE(jt.descricao, jt.forma_pagamento, jt.forma, ''))",
            $valores,
            $bindings,
        );

        if ($driver === 'sqlite') {
            $bindings = [];
            $matchSql = self::sqlMatchAnyClauses(
                "LOWER(COALESCE(
                    json_extract(j.value, '$.descricao'),
                    json_extract(j.value, '$.forma_pagamento'),
                    json_extract(j.value, '$.forma'),
                    ''
                ))",
                $valores,
                $bindings,
            );

            $query->orWhereRaw(
                "EXISTS (
                    SELECT 1 FROM json_each(json_extract(payload, '$.pagamentos')) AS j
                    WHERE COALESCE(json_extract(j.value, '$.valor'), 1) > 0.009
                      AND ({$matchSql})
                )",
                $bindings,
            );

            return;
        }

        $query->orWhereRaw(
            "JSON_EXTRACT(payload, '$.pagamentos') IS NOT NULL
             AND JSON_LENGTH(JSON_EXTRACT(payload, '$.pagamentos')) > 0
             AND EXISTS (
                SELECT 1 FROM JSON_TABLE(
                    payload,
                    '$.pagamentos[*]' COLUMNS (
                        descricao VARCHAR(120) CHARACTER SET utf8mb4 PATH '$.descricao',
                        forma_pagamento VARCHAR(120) CHARACTER SET utf8mb4 PATH '$.forma_pagamento',
                        forma VARCHAR(120) CHARACTER SET utf8mb4 PATH '$.forma',
                        valor DECIMAL(15,2) PATH '$.valor'
                    )
                ) AS jt
                WHERE (jt.valor IS NULL OR jt.valor > 0.009)
                  AND ({$matchSql})
             )",
            $bindings,
        );
    }

    private static function whereSemPagamentosDetalhados(
        Builder $query,
        string $driver,
        bool $hasPdvPagamentos,
    ): void {
        if ($driver === 'sqlite') {
            $query->whereRaw(
                "(json_extract(payload, '$.pagamentos') IS NULL
                  OR json_array_length(json_extract(payload, '$.pagamentos')) = 0
                  OR json_type(json_extract(payload, '$.pagamentos')) != 'array')"
            );
        } else {
            $query->whereRaw(
                "(JSON_EXTRACT(payload, '$.pagamentos') IS NULL
                  OR JSON_LENGTH(JSON_EXTRACT(payload, '$.pagamentos')) = 0)"
            );
        }

        if ($hasPdvPagamentos) {
            $prefix = $query->getConnection()->getTablePrefix();
            $orders = $prefix.$query->getModel()->getTable();
            $pdv = $prefix.(new PdvVenda)->getTable();
            $pag = $prefix.(new PdvVendaPagamento)->getTable();

            $query->whereRaw(
                "NOT EXISTS (
                    SELECT 1
                    FROM {$pag} AS pp
                    INNER JOIN {$pdv} AS pv ON pv.id = pp.pdv_venda_id
                    WHERE pv.venda_id = {$orders}.venda_id
                      AND pp.valor > 0
                )"
            );
        }
    }

    /**
     * @param  list<string>  $valores
     */
    private static function whereColumnMatchesAnyForma(Builder $query, string $sqlExpr, array $valores): void
    {
        $bindings = [];
        $sql = self::sqlMatchAnyClauses('LOWER('.$sqlExpr.')', $valores, $bindings);
        $query->whereRaw('('.$sql.')', $bindings);
    }

    /**
     * @param  list<string>  $valores
     * @param  list<mixed>  $bindings
     */
    private static function sqlMatchAnyClauses(string $lowerExpr, array $valores, ?array &$bindings = null): string
    {
        $bindings = [];
        $parts = [];

        foreach ($valores as $valor) {
            $local = [];
            $parts[] = '('.self::sqlMatchClauses($lowerExpr, $valor, $local).')';
            array_push($bindings, ...$local);
        }

        return $parts === [] ? '0 = 1' : implode(' OR ', $parts);
    }

    /**
     * @param  list<mixed>  $bindings
     */
    private static function sqlMatchClauses(string $lowerExpr, string $valor, ?array &$bindings = null): string
    {
        $bindings = [];
        $familia = self::familiaFiltro($valor);

        return match ($familia) {
            'credito' => self::sqlFamiliaCreditoDebito($lowerExpr, 'credito', $bindings),
            'debito' => self::sqlFamiliaCreditoDebito($lowerExpr, 'debito', $bindings),
            'dinheiro' => self::sqlFamiliaSimples($lowerExpr, 'dinheiro', $bindings),
            'pix' => self::sqlFamiliaSimples($lowerExpr, 'pix', $bindings),
            'troca' => self::sqlFamiliaSimples($lowerExpr, 'troca', $bindings),
            'crediario' => self::sqlLikeAny($lowerExpr, ['%crediario%'], $bindings),
            default => self::sqlLikeAny(
                $lowerExpr,
                array_map(
                    static fn (string $n): string => '%'.addcslashes($n, '%_\\').'%',
                    self::variantesBusca($valor),
                ),
                $bindings,
            ),
        };
    }

    /**
     * @param  list<mixed>  $bindings
     */
    private static function sqlFamiliaCreditoDebito(string $lowerExpr, string $alvo, ?array &$bindings): string
    {
        // Exact SCV ("crédito"/"débito") + compostos + nomes do cadastro/TV/PDV.
        // Não usa LIKE '%credito%' solto (pegaria CREDIÁRIO).
        // Forma acentuada explícita: SQLite não dobra acento como utf8mb4_unicode_ci.
        $acento = $alvo === 'credito' ? 'crédito' : 'débito';
        $parts = ["{$lowerExpr} IN (?, ?, ?)"];
        $bindings = [$alvo, $acento, 'cartao '.$alvo];

        foreach ([
            '%cartao '.$alvo.'%',
            '%pos '.$alvo.'%',
            $alvo.' +%',
            $acento.' +%',
            '%+ '.$alvo,
            '%+ '.$acento,
            $alvo.' /%',
            $acento.' /%',
            '%/ '.$alvo,
            '%/ '.$acento,
        ] as $like) {
            $parts[] = "{$lowerExpr} LIKE ?";
            $bindings[] = $like;
        }

        return implode(' OR ', $parts);
    }

    /**
     * @param  list<mixed>  $bindings
     */
    private static function sqlFamiliaSimples(string $lowerExpr, string $alvo, ?array &$bindings): string
    {
        $bindings = [];
        $parts = [
            "{$lowerExpr} = ?",
            "{$lowerExpr} LIKE ?",
            "{$lowerExpr} LIKE ?",
            "{$lowerExpr} LIKE ?",
            "{$lowerExpr} LIKE ?",
        ];
        $bindings[] = $alvo;
        $bindings[] = $alvo.' +%';
        $bindings[] = '%+ '.$alvo;
        $bindings[] = $alvo.' /%';
        $bindings[] = '%/ '.$alvo;

        return implode(' OR ', $parts);
    }

    /**
     * @param  list<string>  $likes
     * @param  list<mixed>  $bindings
     */
    private static function sqlLikeAny(string $lowerExpr, array $likes, ?array &$bindings): string
    {
        $bindings = [];
        if ($likes === []) {
            return '0 = 1';
        }

        $parts = [];
        foreach ($likes as $like) {
            $parts[] = "{$lowerExpr} LIKE ?";
            $bindings[] = $like;
        }

        return implode(' OR ', $parts);
    }
}
