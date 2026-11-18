<?php

namespace App\Support\Erp\Dre;

use App\Models\Product;
use App\Support\Erp\ErpSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D2.6 — Snapshot de custo no fechamento da venda.
 *
 * Prioridade: pep.preco_custo → products.preco_custo → pep/products.preco_compra.
 * Não usa e_medio. Não inventa zero: custo desconhecido = NULL + situacao indisponivel.
 */
final class VendaItemCustoHistorico
{
    public const ORIGEM_PEP = 'pep';

    public const ORIGEM_PRODUTO = 'produto';

    public const ORIGEM_COMPRA = 'compra';

    public const ORIGEM_INDISPONIVEL = 'indisponivel';

    public const ORIGEM_NAO_APLICAVEL = 'nao_aplicavel';

    public const SITUACAO_OK = 'ok';

    public const SITUACAO_INDISPONIVEL = 'indisponivel';

    public const SITUACAO_NAO_APLICAVEL = 'nao_aplicavel';

    /**
     * @return array{
     *   custo_unitario: ?string,
     *   custo_total: ?string,
     *   custo_origem: string,
     *   custo_situacao: string
     * }
     */
    public static function snapshot(int $productId, float $quantidade, ?int $empresaId = null, ?Product $product = null): array
    {
        if ($productId <= 0) {
            return self::indisponivel();
        }

        $product ??= Product::query()
            ->whereKey($productId)
            ->first(['id', 'is_servico', 'preco_custo', 'preco_compra']);

        if ($product === null) {
            return self::indisponivel();
        }

        if ((bool) ($product->is_servico ?? false)) {
            return self::naoAplicavel();
        }

        $map = self::resolverLote([$productId], $empresaId);
        $row = $map[$productId] ?? null;

        if ($row === null || $row['unitario'] === null) {
            return self::indisponivel();
        }

        $qtd = abs($quantidade);
        $unit = (float) $row['unitario'];
        $total = round($unit * $qtd, 2);

        return [
            'custo_unitario' => number_format($unit, 4, '.', ''),
            'custo_total' => number_format($total, 2, '.', ''),
            'custo_origem' => $row['origem'],
            'custo_situacao' => self::SITUACAO_OK,
        ];
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, array{unitario: ?float, origem: string}>
     */
    public static function resolverLote(array $productIds, ?int $empresaId = null): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $productIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === [] || ! Schema::hasTable('products')) {
            return [];
        }

        $empresaId = (int) ($empresaId ?? 0);
        $query = DB::table('products as p')
            ->whereIn('p.id', $ids)
            ->select(['p.id', 'p.preco_custo', 'p.preco_compra', 'p.is_servico']);

        if ($empresaId > 0 && Schema::hasTable('product_empresa_precos')) {
            $query
                ->leftJoin('product_empresa_precos as pep', function ($join) use ($empresaId): void {
                    $join->on('pep.product_id', '=', 'p.id')
                        ->where('pep.empresa_id', '=', $empresaId);
                })
                ->addSelect([
                    'pep.preco_custo as pep_custo',
                    'pep.preco_compra as pep_compra',
                ]);
        }

        $out = [];
        foreach ($query->get() as $row) {
            if ((bool) ($row->is_servico ?? false)) {
                $out[(int) $row->id] = ['unitario' => null, 'origem' => self::ORIGEM_NAO_APLICAVEL];

                continue;
            }

            $pepCusto = $empresaId > 0 ? (float) ($row->pep_custo ?? 0) : 0.0;
            if ($pepCusto > 0) {
                $out[(int) $row->id] = ['unitario' => round($pepCusto, 4), 'origem' => self::ORIGEM_PEP];

                continue;
            }

            $prodCusto = (float) ($row->preco_custo ?? 0);
            if ($prodCusto > 0) {
                $out[(int) $row->id] = ['unitario' => round($prodCusto, 4), 'origem' => self::ORIGEM_PRODUTO];

                continue;
            }

            $pepCompra = $empresaId > 0 ? (float) ($row->pep_compra ?? 0) : 0.0;
            if ($pepCompra > 0) {
                $out[(int) $row->id] = ['unitario' => round($pepCompra, 4), 'origem' => self::ORIGEM_COMPRA];

                continue;
            }

            $prodCompra = (float) ($row->preco_compra ?? 0);
            if ($prodCompra > 0) {
                $out[(int) $row->id] = ['unitario' => round($prodCompra, 4), 'origem' => self::ORIGEM_COMPRA];

                continue;
            }

            $out[(int) $row->id] = ['unitario' => null, 'origem' => self::ORIGEM_INDISPONIVEL];
        }

        return $out;
    }

    /**
     * Offline: usa custo enviado no retorno (momento da venda no terminal) quando válido;
     * senão snapshot no servidor no momento da sincronização (melhor esforço — a carga
     * atual do PDV offline não envia preco_custo no item).
     *
     * @param  array<string, mixed>  $itemPayload
     * @return array{
     *   custo_unitario: ?string,
     *   custo_total: ?string,
     *   custo_origem: string,
     *   custo_situacao: string
     * }
     */
    public static function dePayloadOfflineOuSnapshot(
        array $itemPayload,
        int $productId,
        float $quantidade,
        ?int $empresaId = null,
        ?Product $product = null,
    ): array {
        $doPayload = self::copiarDe($itemPayload);
        if ($doPayload !== null) {
            if (($doPayload['custo_situacao'] ?? '') === self::SITUACAO_OK
                && $doPayload['custo_unitario'] !== null
                && (float) $doPayload['custo_unitario'] > 0
            ) {
                $unit = (float) $doPayload['custo_unitario'];
                $doPayload['custo_unitario'] = number_format($unit, 4, '.', '');
                $doPayload['custo_total'] = number_format(round($unit * abs($quantidade), 2), 2, '.', '');

                return $doPayload;
            }

            // Payload marcou indisponível/não aplicável — respeita (não inventa no sync).
            if (in_array($doPayload['custo_situacao'] ?? '', [self::SITUACAO_INDISPONIVEL, self::SITUACAO_NAO_APLICAVEL], true)) {
                return [
                    'custo_unitario' => null,
                    'custo_total' => null,
                    'custo_origem' => (string) ($doPayload['custo_origem'] ?: self::ORIGEM_INDISPONIVEL),
                    'custo_situacao' => (string) $doPayload['custo_situacao'],
                ];
            }
        }

        // Sem captura no payload: resolve pelo cadastro no instante do sync.
        return self::snapshot($productId, $quantidade, $empresaId, $product);
    }

    /**
     * Copia snapshot já gravado (PDV → venda, ou venda → devolução).
     *
     * @param  object|array<string, mixed>  $fonte
     * @return array<string, mixed>|null  null se a fonte ainda não tem captura
     */
    public static function copiarDe(object|array $fonte): ?array
    {
        $get = static function (object|array $src, string $key): mixed {
            if (is_array($src)) {
                return $src[$key] ?? null;
            }

            return $src->{$key} ?? null;
        };

        $situacao = $get($fonte, 'custo_situacao');
        if ($situacao === null || $situacao === '') {
            return null;
        }

        $unit = $get($fonte, 'custo_unitario');
        $total = $get($fonte, 'custo_total');

        return [
            'custo_unitario' => $unit === null || $unit === '' ? null : (string) $unit,
            'custo_total' => $total === null || $total === '' ? null : (string) $total,
            'custo_origem' => (string) ($get($fonte, 'custo_origem') ?: self::ORIGEM_INDISPONIVEL),
            'custo_situacao' => (string) $situacao,
        ];
    }

    /**
     * Preserva snapshots antes de delete+recreate (FV/OS/mirror).
     *
     * @return array<int, array<string, mixed>> product_id => attrs (primeiro item ok por produto)
     */
    public static function mapaPreservarPorProduto(int $vendaId): array
    {
        if ($vendaId <= 0 || ! Schema::hasTable('venda_itens') || ! ErpSchema::hasColumn('venda_itens', 'custo_situacao')) {
            return [];
        }

        $mapa = [];
        foreach (DB::table('venda_itens')
            ->where('venda_id', $vendaId)
            ->whereNotNull('custo_situacao')
            ->orderBy('id')
            ->get(['product_id', 'custo_unitario', 'custo_total', 'custo_origem', 'custo_situacao', 'quantidade']) as $row
        ) {
            $pid = (int) $row->product_id;
            if ($pid <= 0 || isset($mapa[$pid])) {
                continue;
            }
            $copiado = self::copiarDe($row);
            if ($copiado !== null) {
                $mapa[$pid] = $copiado;
            }
        }

        return $mapa;
    }

    /**
     * @param  array<int, array<string, mixed>>  $preservado
     * @return array<string, mixed>
     */
    public static function dePreservadoOuResolver(
        int $productId,
        float $quantidade,
        ?int $empresaId,
        array $preservado,
        ?Product $product = null,
    ): array {
        if (isset($preservado[$productId])) {
            $base = $preservado[$productId];
            // Recalcula total pela qtd atual se unitário conhecido.
            if (($base['custo_situacao'] ?? '') === self::SITUACAO_OK && $base['custo_unitario'] !== null) {
                $unit = (float) $base['custo_unitario'];
                $base['custo_unitario'] = number_format($unit, 4, '.', '');
                $base['custo_total'] = number_format(round($unit * abs($quantidade), 2), 2, '.', '');
            }

            return $base;
        }

        return self::snapshot($productId, $quantidade, $empresaId, $product);
    }

    public static function tabelaTemCusto(string $table): bool
    {
        return Schema::hasTable($table) && ErpSchema::hasColumn($table, 'custo_situacao');
    }

    /**
     * @return array{custo_unitario: null, custo_total: null, custo_origem: string, custo_situacao: string}
     */
    private static function indisponivel(): array
    {
        return [
            'custo_unitario' => null,
            'custo_total' => null,
            'custo_origem' => self::ORIGEM_INDISPONIVEL,
            'custo_situacao' => self::SITUACAO_INDISPONIVEL,
        ];
    }

    /**
     * @return array{custo_unitario: null, custo_total: null, custo_origem: string, custo_situacao: string}
     */
    private static function naoAplicavel(): array
    {
        return [
            'custo_unitario' => null,
            'custo_total' => null,
            'custo_origem' => self::ORIGEM_NAO_APLICAVEL,
            'custo_situacao' => self::SITUACAO_NAO_APLICAVEL,
        ];
    }
}
