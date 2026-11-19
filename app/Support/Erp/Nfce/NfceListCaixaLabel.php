<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Support\Erp\ErpSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rótulo da coluna Caixa na listagem NFC-e:
 * - PDV: terminal da sessão;
 * - histórico SCV: CAIXA GERAL;
 * - regularização fiscal (Monitor/F9): conta financeira da venda original (payload.caixa_conta_id).
 */
final class NfceListCaixaLabel
{
    /** @var array<int, string>|null venda_id comercial => nome da conta */
    private static ?array $cachePorVendaId = null;

    /**
     * Pré-carrega nomes de caixa financeiro só para a página (evita N+1).
     *
     * @param  iterable<int, PdvVendaNfce>  $records
     * @return array<int, string> venda_id => nome conta
     */
    public static function aquecer(iterable $records): array
    {
        $vendaIds = [];

        foreach ($records as $record) {
            if (! $record instanceof PdvVendaNfce) {
                continue;
            }

            $venda = $record->pdvVenda;
            if ($venda === null) {
                continue;
            }

            if ((string) ($venda->origem ?? '') !== PdvVenda::ORIGEM_REGULARIZACAO_FISCAL) {
                continue;
            }

            $vendaId = (int) ($venda->venda_id ?? 0);
            if ($vendaId > 0) {
                $vendaIds[$vendaId] = $vendaId;
            }
        }

        if ($vendaIds === []) {
            self::$cachePorVendaId = self::$cachePorVendaId ?? [];

            return [];
        }

        $carregados = self::buscarPorVendaIds(array_values($vendaIds));
        self::$cachePorVendaId = (self::$cachePorVendaId ?? []) + $carregados;

        return $carregados;
    }

    /**
     * @param  list<int>  $vendaIds
     * @return array<int, string>
     */
    public static function buscarPorVendaIds(array $vendaIds): array
    {
        $vendaIds = array_values(array_unique(array_filter(
            array_map('intval', $vendaIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($vendaIds === [] || ! ErpSchema::hasTable('forca_vendas_orders') || ! ErpSchema::hasTable('caixa_contas')) {
            return [];
        }

        $rows = DB::table('forca_vendas_orders')
            ->whereIn('venda_id', $vendaIds)
            ->orderByDesc('id')
            ->get([
                'venda_id',
                DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.caixa_conta_id')) AS UNSIGNED) as caixa_conta_id"),
                DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.caixa_id')) AS UNSIGNED) as caixa_id"),
            ]);

        /** @var array<int, int> $vendaParaConta */
        $vendaParaConta = [];

        foreach ($rows as $row) {
            $vendaId = (int) ($row->venda_id ?? 0);
            if ($vendaId <= 0 || isset($vendaParaConta[$vendaId])) {
                continue;
            }

            $contaId = (int) ($row->caixa_conta_id ?: $row->caixa_id);
            if ($contaId > 0) {
                $vendaParaConta[$vendaId] = $contaId;
            }
        }

        if ($vendaParaConta === []) {
            return [];
        }

        $nomes = DB::table('caixa_contas')
            ->whereIn('id', array_values(array_unique($vendaParaConta)))
            ->pluck('nome', 'id');

        $map = [];

        foreach ($vendaParaConta as $vendaId => $contaId) {
            $nome = trim((string) ($nomes[$contaId] ?? ''));
            if ($nome !== '') {
                $map[$vendaId] = $nome;
            }
        }

        return $map;
    }

    /**
     * Texto da célula Caixa.
     *
     * @param  array<int, string>|null  $caixaFinanceiroPorVendaId
     */
    public static function texto(PdvVendaNfce $record, ?array $caixaFinanceiroPorVendaId = null): string
    {
        if ((string) $record->operacao === NfceListDavNumero::OPERACAO_SCV_HIST) {
            return 'CAIXA GERAL';
        }

        $venda = $record->pdvVenda;
        $terminal = trim((string) ($venda?->sessao?->terminal?->nome ?? ''));
        if ($terminal !== '') {
            return $terminal;
        }

        if ($venda !== null && (string) ($venda->origem ?? '') === PdvVenda::ORIGEM_REGULARIZACAO_FISCAL) {
            $vendaId = (int) ($venda->venda_id ?? 0);
            $map = $caixaFinanceiroPorVendaId ?? self::$cachePorVendaId ?? [];
            $nome = $vendaId > 0 ? trim((string) ($map[$vendaId] ?? '')) : '';

            if ($nome !== '') {
                return $nome;
            }
        }

        return '—';
    }

    /**
     * Filtro da busca por coluna Caixa (terminal PDV + SCV + conta financeira da regularização).
     *
     * @param  Builder<PdvVendaNfce>  $query
     */
    public static function aplicarBusca(Builder $query, string $like): void
    {
        $table = $query->getModel()->getTable();
        $termCaixa = mb_strtoupper(trim($like, '%'), 'UTF-8');

        $query->where(function (Builder $outer) use ($like, $table, $termCaixa): void {
            $outer->whereHas(
                'pdvVenda.sessao.terminal',
                static fn (Builder $terminal): Builder => $terminal->where('nome', 'like', $like),
            );

            if ($termCaixa !== '' && str_contains('CAIXA GERAL', $termCaixa)) {
                $outer->orWhere("{$table}.operacao", NfceListDavNumero::OPERACAO_SCV_HIST);
            }

            if (! ErpSchema::hasTable('forca_vendas_orders') || ! ErpSchema::hasTable('caixa_contas')) {
                return;
            }

            $contaIds = DB::table('caixa_contas')
                ->where('nome', 'like', $like)
                ->limit(50)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            if ($contaIds === []) {
                return;
            }

            $placeholders = implode(',', array_fill(0, count($contaIds), '?'));
            $vendaIds = DB::table('forca_vendas_orders')
                ->where(function ($conta) use ($contaIds, $placeholders): void {
                    $conta->whereRaw(
                        "CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.caixa_conta_id')) AS UNSIGNED) IN ({$placeholders})",
                        $contaIds,
                    )->orWhereRaw(
                        "CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.caixa_id')) AS UNSIGNED) IN ({$placeholders})",
                        $contaIds,
                    );
                })
                ->whereNotNull('venda_id')
                ->limit(1000)
                ->pluck('venda_id')
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($vendaIds === []) {
                return;
            }

            $outer->orWhereHas('pdvVenda', static function (Builder $venda) use ($vendaIds): void {
                $venda->where('origem', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL)
                    ->whereIn('venda_id', $vendaIds);
            });
        });
    }

    public static function limparCache(): void
    {
        self::$cachePorVendaId = null;
    }
}
