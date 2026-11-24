<?php

namespace App\Support\Erp\Reports;

use App\Models\FormaPagamento;
use App\Models\ForcaVendasOrder;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\Venda;
use App\Support\Erp\ErpEmpresaScopeFilter;
use App\Support\Erp\ErpSchema;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Totais por forma real: mix (DINHEIRO + PIX) soma em cada forma, sem linha combinada.
 * Parcelas: pdv_venda_pagamentos (PDV) ou payload.pagamentos do FV (monitor/app).
 * É faturamento por forma — não confundir com recebimento no caixa.
 */
final class VendasPorFormaPagamentoAggregator
{
    /**
     * @param  int|list<int>|null  $empresaScope
     * @return list<array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>
     */
    public static function aggregate(
        CarbonInterface $de,
        CarbonInterface $ate,
        int|array|null $empresaScope = null,
        string $formaFiltro = '',
        bool $groupByEmpresa = false,
    ): array {
        return self::aggregateDetailed($de, $ate, $empresaScope, $formaFiltro, $groupByEmpresa)['rows'];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{
     *     rows: list<array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>,
     *     vendas_unicas: int,
     *     total: float
     * }
     */
    public static function aggregateDetailed(
        CarbonInterface $de,
        CarbonInterface $ate,
        int|array|null $empresaScope = null,
        string $formaFiltro = '',
        bool $groupByEmpresa = false,
    ): array {
        if (! ErpSchema::hasTable((new Venda)->getTable())) {
            return ['rows' => [], 'vendas_unicas' => 0, 'total' => 0.0];
        }

        $hasEmpresa = ErpSchema::hasColumn((new Venda)->getTable(), 'empresa_id');
        $scope = $hasEmpresa ? $empresaScope : null;

        if (self::scopeBlocksAll($scope)) {
            return ['rows' => [], 'vendas_unicas' => 0, 'total' => 0.0];
        }

        $cadastro = self::formasCadastro();
        $aggregated = [];
        $vendaIds = [];
        $deStr = $de->toDateString();
        $ateStr = $ate->toDateString();
        $groupEmpresa = $groupByEmpresa && $hasEmpresa;

        self::somarPagamentos($aggregated, $vendaIds, $deStr, $ateStr, $scope, $groupEmpresa, $cadastro);
        self::somarCabecalhosSemPagamento($aggregated, $vendaIds, $deStr, $ateStr, $scope, $groupEmpresa, $cadastro);

        $rows = self::finalizeRows($aggregated, $formaFiltro);
        $total = round(array_sum(array_map(static fn (array $r): float => (float) $r['total'], $rows)), 2);

        return [
            'rows' => $rows,
            'vendas_unicas' => count($vendaIds),
            'total' => $total,
        ];
    }

    /**
     * Agrega formas apenas para o universo de vendas já filtrado (sem duplicar venda mista no total comercial).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $vendaIdsQuery  SELECT vendas.id
     * @return list<array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>
     */
    public static function aggregateForVendaIdsQuery(mixed $vendaIdsQuery, string $formaFiltro = ''): array
    {
        if (! ErpSchema::hasTable((new Venda)->getTable())) {
            return [];
        }

        $idsQuery = $vendaIdsQuery instanceof \Illuminate\Database\Eloquent\Builder
            ? $vendaIdsQuery->clone()->select('vendas.id')->toBase()
            : $vendaIdsQuery;

        $cadastro = self::formasCadastro();
        $aggregated = [];
        $vendaIds = [];

        self::somarPagamentosNoUniverso($aggregated, $vendaIds, $idsQuery, $cadastro);
        self::somarCabecalhosSemPagamentoNoUniverso($aggregated, $vendaIds, $idsQuery, $cadastro);

        return self::finalizeRows($aggregated, $formaFiltro);
    }

    public static function formaBase(string $forma): string
    {
        $base = trim($forma);
        $pos = mb_strpos($base, ' (');

        if ($pos !== false) {
            $base = trim(mb_substr($base, 0, $pos));
        }

        return $base;
    }

    public static function isFormaMista(string $forma): bool
    {
        return str_contains($forma, ' / ');
    }

    /**
     * @param  Collection<int, FormaPagamento>|null  $cadastro
     */
    public static function rotuloFormaCadastro(string $formaBruta, ?Collection $cadastro = null): string
    {
        $base = trim($formaBruta);

        if ($base === '') {
            return 'NÃO INFORMADA';
        }

        $upper = mb_strtoupper($base, 'UTF-8');
        $cadastro ??= self::formasCadastro();

        foreach ($cadastro as $forma) {
            $descricao = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');

            if ($descricao !== '' && $descricao === $upper) {
                return $descricao;
            }
        }

        return $upper;
    }

    /**
     * @param  Collection<int, FormaPagamento>  $cadastro
     */
    public static function codigoFormaCadastro(string $formaBruta, Collection $cadastro): int
    {
        $upper = mb_strtoupper(trim($formaBruta), 'UTF-8');

        foreach ($cadastro as $forma) {
            $descricao = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');

            if ($descricao !== '' && $descricao === $upper) {
                return (int) ($forma->codigo ?: $forma->id);
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * @return Collection<int, FormaPagamento>
     */
    public static function formasCadastro(): Collection
    {
        if (! ErpSchema::hasTable((new FormaPagamento)->getTable())) {
            return collect();
        }

        return FormaPagamento::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descricao']);
    }

    /**
     * Extrai parcelas reais do payload FV (monitor/app). Ignora forma combinada.
     *
     * @param  array<string, mixed>|null  $payload
     * @return list<array{forma: string, valor: float}>
     */
    public static function parcelasDoPayloadFv(?array $payload): array
    {
        if ($payload === null) {
            return [];
        }

        $pags = $payload['pagamentos'] ?? null;
        if (! is_array($pags) || $pags === []) {
            return [];
        }

        $out = [];
        foreach ($pags as $pag) {
            if (! is_array($pag)) {
                continue;
            }

            $valor = round((float) ($pag['valor'] ?? 0), 2);
            if ($valor <= 0.009) {
                continue;
            }

            $forma = trim((string) (
                $pag['forma_pagamento']
                ?? $pag['forma']
                ?? $pag['descricao']
                ?? ''
            ));
            if ($forma === '' || self::isFormaMista($forma)) {
                continue;
            }

            $out[] = [
                'forma' => $forma,
                'valor' => $valor,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @return list<array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>
     */
    private static function finalizeRows(array $aggregated, string $formaFiltro): array
    {
        $filtro = mb_strtoupper(trim($formaFiltro), 'UTF-8');
        $rows = array_values($aggregated);

        if ($filtro !== '' && $filtro !== 'TODOS') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => mb_strtoupper((string) $row['forma'], 'UTF-8') === $filtro,
            ));
        }

        usort($rows, static function (array $a, array $b): int {
            $byCodigo = ((int) $a['codigo']) <=> ((int) $b['codigo']);
            if ($byCodigo !== 0) {
                return $byCodigo;
            }

            return strcmp((string) $a['forma'], (string) $b['forma']);
        });

        return $rows;
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  array<int, true>  $vendaIds
     * @param  Collection<int, FormaPagamento>  $cadastro
     */
    private static function somarPagamentosNoUniverso(
        array &$aggregated,
        array &$vendaIds,
        mixed $vendaIdsQuery,
        Collection $cadastro,
    ): void {
        if (! ErpSchema::hasTable((new PdvVendaPagamento)->getTable())
            || ! ErpSchema::hasTable((new PdvVenda)->getTable())
        ) {
            return;
        }

        $formaCol = self::sqlCol('pdv_venda_pagamentos', 'forma');
        $valorCol = self::sqlCol('pdv_venda_pagamentos', 'valor');

        $query = PdvVendaPagamento::query()
            ->join('pdv_vendas', 'pdv_vendas.id', '=', 'pdv_venda_pagamentos.pdv_venda_id')
            ->whereIn('pdv_vendas.venda_id', $vendaIdsQuery)
            ->where('pdv_venda_pagamentos.valor', '>', 0);
        self::excluirOrigemRegularizacaoFiscal($query);

        foreach ($query->clone()
            ->groupBy('pdv_venda_pagamentos.forma')
            ->select([
                DB::raw("{$formaCol} as forma"),
                DB::raw('COUNT(*) as qtd'),
                DB::raw("SUM({$valorCol}) as total"),
            ])
            ->get() as $row
        ) {
            self::somar(
                $aggregated,
                (string) ($row->forma ?? ''),
                (float) $row->qtd,
                (float) $row->total,
                0,
                false,
                $cadastro,
            );
        }

        foreach ($query->clone()->distinct()->pluck('pdv_vendas.venda_id') as $vid) {
            $id = (int) $vid;
            if ($id > 0) {
                $vendaIds[$id] = true;
            }
        }
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  array<int, true>  $vendaIds
     * @param  Collection<int, FormaPagamento>  $cadastro
     */
    private static function somarCabecalhosSemPagamentoNoUniverso(
        array &$aggregated,
        array &$vendaIds,
        mixed $vendaIdsQuery,
        Collection $cadastro,
    ): void {
        $query = Venda::query()
            ->whereIn('vendas.id', $vendaIdsQuery);

        self::excluirComPagamentoPdv($query);

        $vendas = $query->get(['vendas.id', 'vendas.empresa_id', 'vendas.forma_pagamento', 'vendas.total']);
        self::somarVendasSemPdv($aggregated, $vendaIds, $vendas, false, $cadastro);
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  array<int, true>  $vendaIds
     * @param  int|list<int>|null  $empresaScope
     * @param  Collection<int, FormaPagamento>  $cadastro
     */
    private static function somarPagamentos(
        array &$aggregated,
        array &$vendaIds,
        string $de,
        string $ate,
        int|array|null $empresaScope,
        bool $groupByEmpresa,
        Collection $cadastro,
    ): void {
        if (! ErpSchema::hasTable((new PdvVendaPagamento)->getTable())
            || ! ErpSchema::hasTable((new PdvVenda)->getTable())
        ) {
            return;
        }

        $query = PdvVendaPagamento::query()
            ->join('pdv_vendas', 'pdv_vendas.id', '=', 'pdv_venda_pagamentos.pdv_venda_id')
            ->join('vendas', 'vendas.id', '=', 'pdv_vendas.venda_id')
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereDate('vendas.data', '>=', $de)
            ->whereDate('vendas.data', '<=', $ate)
            ->whereNotNull('pdv_vendas.venda_id')
            ->where('pdv_venda_pagamentos.valor', '>', 0);
        self::excluirOrigemRegularizacaoFiscal($query);

        ErpEmpresaScopeFilter::applyColumn($query, 'vendas', $empresaScope);

        $formaCol = self::sqlCol('pdv_venda_pagamentos', 'forma');
        $valorCol = self::sqlCol('pdv_venda_pagamentos', 'valor');
        $select = [
            DB::raw("{$formaCol} as forma"),
            DB::raw('COUNT(*) as qtd'),
            DB::raw("SUM({$valorCol}) as total"),
        ];

        if ($groupByEmpresa) {
            $select[] = 'vendas.empresa_id';
            $query->groupBy('vendas.empresa_id', 'pdv_venda_pagamentos.forma');
        } else {
            $query->groupBy('pdv_venda_pagamentos.forma');
        }

        foreach ($query->clone()->select($select)->get() as $row) {
            self::somar(
                $aggregated,
                (string) ($row->forma ?? ''),
                (float) $row->qtd,
                (float) $row->total,
                $groupByEmpresa ? (int) ($row->empresa_id ?? 0) : 0,
                $groupByEmpresa,
                $cadastro,
            );
        }

        $idsQuery = PdvVendaPagamento::query()
            ->join('pdv_vendas', 'pdv_vendas.id', '=', 'pdv_venda_pagamentos.pdv_venda_id')
            ->join('vendas', 'vendas.id', '=', 'pdv_vendas.venda_id')
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereDate('vendas.data', '>=', $de)
            ->whereDate('vendas.data', '<=', $ate)
            ->whereNotNull('pdv_vendas.venda_id')
            ->where('pdv_venda_pagamentos.valor', '>', 0);
        self::excluirOrigemRegularizacaoFiscal($idsQuery);
        ErpEmpresaScopeFilter::applyColumn($idsQuery, 'vendas', $empresaScope);

        foreach ($idsQuery->distinct()->pluck('pdv_vendas.venda_id') as $vid) {
            $id = (int) $vid;
            if ($id > 0) {
                $vendaIds[$id] = true;
            }
        }
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  array<int, true>  $vendaIds
     * @param  int|list<int>|null  $empresaScope
     * @param  Collection<int, FormaPagamento>  $cadastro
     */
    private static function somarCabecalhosSemPagamento(
        array &$aggregated,
        array &$vendaIds,
        string $de,
        string $ate,
        int|array|null $empresaScope,
        bool $groupByEmpresa,
        Collection $cadastro,
    ): void {
        $query = Venda::query()
            ->where('status', Venda::STATUS_FECHADO)
            ->whereDate('data', '>=', $de)
            ->whereDate('data', '<=', $ate);

        ErpEmpresaScopeFilter::applyColumn($query, (new Venda)->getTable(), $empresaScope);
        self::excluirComPagamentoPdv($query);

        $cols = ['id', 'forma_pagamento', 'total'];
        if (ErpSchema::hasColumn((new Venda)->getTable(), 'empresa_id')) {
            $cols[] = 'empresa_id';
        }

        $vendas = $query->get($cols);
        self::somarVendasSemPdv($aggregated, $vendaIds, $vendas, $groupByEmpresa, $cadastro);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    private static function excluirComPagamentoPdv(mixed $query): void
    {
        if (! ErpSchema::hasTable((new PdvVendaPagamento)->getTable())
            || ! ErpSchema::hasTable((new PdvVenda)->getTable())
        ) {
            return;
        }

        $query->whereNotExists(function ($exists): void {
            $exists->selectRaw('1')
                ->from('pdv_vendas')
                ->join('pdv_venda_pagamentos', 'pdv_venda_pagamentos.pdv_venda_id', '=', 'pdv_vendas.id')
                ->whereColumn('pdv_vendas.venda_id', 'vendas.id');
            self::excluirOrigemRegularizacaoFiscal($exists);
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    private static function excluirOrigemRegularizacaoFiscal(mixed $query): void
    {
        if (! ErpSchema::hasColumn((new PdvVenda)->getTable(), 'origem')) {
            return;
        }

        $query->where(function ($o): void {
            $o->whereNull('pdv_vendas.origem')
                ->orWhere('pdv_vendas.origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
        });
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  array<int, true>  $vendaIds
     * @param  Collection<int, object>  $vendas
     * @param  Collection<int, FormaPagamento>  $cadastro
     */
    private static function somarVendasSemPdv(
        array &$aggregated,
        array &$vendaIds,
        Collection $vendas,
        bool $groupByEmpresa,
        Collection $cadastro,
    ): void {
        if ($vendas->isEmpty()) {
            return;
        }

        $parcelasPorVenda = self::parcelasFvPorVendaIds(
            $vendas->pluck('id')->map(static fn ($id): int => (int) $id)->all()
        );

        foreach ($vendas as $venda) {
            $vendaId = (int) ($venda->id ?? 0);
            $empresaId = $groupByEmpresa ? (int) ($venda->empresa_id ?? 0) : 0;
            $parcelas = $parcelasPorVenda[$vendaId] ?? [];

            if ($parcelas !== []) {
                foreach ($parcelas as $parcela) {
                    self::somar(
                        $aggregated,
                        $parcela['forma'],
                        1.0,
                        $parcela['valor'],
                        $empresaId,
                        $groupByEmpresa,
                        $cadastro,
                    );
                }
                if ($vendaId > 0) {
                    $vendaIds[$vendaId] = true;
                }

                continue;
            }

            $formaCab = (string) ($venda->forma_pagamento ?? '');
            if (self::isFormaMista($formaCab)) {
                // Sem parcelas reais (PDV/FV): não inventa rateio nem linha combinada.
                continue;
            }

            self::somar(
                $aggregated,
                $formaCab,
                1.0,
                (float) ($venda->total ?? 0),
                $empresaId,
                $groupByEmpresa,
                $cadastro,
            );
            if ($vendaId > 0) {
                $vendaIds[$vendaId] = true;
            }
        }
    }

    /**
     * @param  list<int>  $vendaIds
     * @return array<int, list<array{forma: string, valor: float}>>
     */
    private static function parcelasFvPorVendaIds(array $vendaIds): array
    {
        $vendaIds = array_values(array_filter(array_map('intval', $vendaIds), static fn (int $id): bool => $id > 0));
        if ($vendaIds === [] || ! ErpSchema::hasTable((new ForcaVendasOrder)->getTable())) {
            return [];
        }

        $orders = ForcaVendasOrder::query()
            ->whereIn('venda_id', $vendaIds)
            ->orderBy('id')
            ->get(['id', 'venda_id', 'payload']);

        $out = [];
        foreach ($orders as $order) {
            $vid = (int) $order->venda_id;
            $parcelas = self::parcelasDoPayloadFv(is_array($order->payload) ? $order->payload : null);
            if ($parcelas === []) {
                continue;
            }
            // Último pedido FV com parcelas vence (edição/reimportação).
            $out[$vid] = $parcelas;
        }

        return $out;
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  Collection<int, FormaPagamento>  $cadastro
     */
    private static function somar(
        array &$aggregated,
        string $formaBruta,
        float $qtd,
        float $total,
        int $empresaId,
        bool $groupByEmpresa,
        Collection $cadastro,
    ): void {
        $base = self::formaBase($formaBruta);

        if (self::isFormaMista($base)) {
            return;
        }

        $label = self::rotuloFormaCadastro($base, $cadastro);
        $key = ($groupByEmpresa ? $empresaId.'|' : '').mb_strtoupper($label, 'UTF-8');
        $codigo = self::codigoFormaCadastro($base, $cadastro);

        if (! isset($aggregated[$key])) {
            $aggregated[$key] = [
                'empresa_id' => $groupByEmpresa ? $empresaId : 0,
                'forma' => $label,
                'qtd' => 0.0,
                'total' => 0.0,
                'codigo' => $codigo,
            ];
        }

        $aggregated[$key]['qtd'] += $qtd;
        $aggregated[$key]['total'] += $total;

        if ($codigo < $aggregated[$key]['codigo']) {
            $aggregated[$key]['codigo'] = $codigo;
            $aggregated[$key]['forma'] = $label;
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function scopeBlocksAll(int|array|null $empresaScope): bool
    {
        if (! is_array($empresaScope)) {
            return false;
        }

        $ids = array_values(array_filter(array_map('intval', $empresaScope), static fn (int $id): bool => $id > 0));

        return $ids === [];
    }

    private static function sqlCol(string $table, string $column): string
    {
        $table = str_replace('`', '``', DB::getTablePrefix().$table);
        $column = str_replace('`', '``', $column);

        return "`{$table}`.`{$column}`";
    }
}
