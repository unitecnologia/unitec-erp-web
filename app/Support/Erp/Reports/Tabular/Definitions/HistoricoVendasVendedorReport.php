<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Filament\Pages\ForcaVendasTelaVendaPage;
use App\Filament\Resources\OrdemServicoResource;
use App\Filament\Resources\VendaResource;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\ExpandableTabularReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HistoricoVendasVendedorReport extends AbstractTabularReport implements ExpandableTabularReport
{
    private const DETAILS_PER_PAGE = 50;

    public function slug(): string
    {
        return 'historico-vendas-vendedor';
    }

    public function title(): string
    {
        return 'HISTÓRICO DE VENDAS POR VENDEDOR';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'empresa' => 'EMPRESA',
            'vendedor' => 'VENDEDOR',
            'qtd' => 'QTD VENDAS',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return ['vendedor', 'qtd', 'total'];
    }

    public function numericColumns(): array
    {
        return ['qtd', 'total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
            ...$this->periodFilterFields(),
            [
                'key' => 'vendedor',
                'label' => 'Vendedor',
                'type' => 'select',
                'options' => $this->vendedorOptions(),
            ],
        ]));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: '',
        );
        $multi = $this->isMultiEmpresaScope($request);
        $hasEmpresa = Schema::hasColumn((new Venda)->getTable(), 'empresa_id');
        $vendedorId = $this->vendedorIdFromRequest($request);
        $interactive = ! $request->boolean('pdf') && ! $request->boolean('csv');

        $query = $this->baseSalesQuery($request, $de->toDateString(), $ate->toDateString(), $hasEmpresa)
            ->leftJoin('vendedores', 'vendedores.id', '=', 'vendas.vendedor_id');

        if ($vendedorId > 0) {
            $query->where('vendas.vendedor_id', $vendedorId);
        }

        $vendedorExpr = $this->vendedorLabelSql();

        if ($multi && $hasEmpresa) {
            $query->groupBy('vendas.empresa_id', 'vendas.vendedor_id', 'vendedores.nome', 'vendas.vendedor_nome')
                ->orderBy('vendas.empresa_id')
                ->orderByDesc(DB::raw('SUM('.static::sqlTable('vendas').'.total)'))
                ->select([
                    'vendas.empresa_id',
                    'vendas.vendedor_id',
                    DB::raw($vendedorExpr.' as vendedor'),
                    DB::raw('COUNT(*) as qtd'),
                    DB::raw('SUM('.static::sqlTable('vendas').'.total) as total'),
                ]);
        } else {
            $query->groupBy('vendas.vendedor_id', 'vendedores.nome', 'vendas.vendedor_nome')
                ->orderByDesc(DB::raw('SUM('.static::sqlTable('vendas').'.total)'))
                ->select([
                    'vendas.vendedor_id',
                    DB::raw($vendedorExpr.' as vendedor'),
                    DB::raw('COUNT(*) as qtd'),
                    DB::raw('SUM('.static::sqlTable('vendas').'.total) as total'),
                ]);
        }

        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $rows = $query->limit(5000)->get()->map(function ($row) use ($multi, $labels, $interactive): array {
            $mapped = [
                'vendedor' => (string) $row->vendedor,
                'qtd' => static::formatQuantity((float) $row->qtd),
                'total' => static::formatMoney((float) $row->total),
            ];

            if ($multi) {
                $mapped['empresa'] = $labels[(int) ($row->empresa_id ?? 0)]
                    ?? ReportEmpresaScope::labelEmpresa(null);
            }

            if ($interactive) {
                $mapped['_expand'] = [
                    'vendedor_id' => $row->vendedor_id !== null ? (int) $row->vendedor_id : 0,
                    'empresa_id' => $multi ? (int) ($row->empresa_id ?? 0) : 0,
                    'vendedor' => (string) $row->vendedor,
                ];
            }

            return $mapped;
        })->all();

        $summary = ['PERÍODO: '.$this->periodLabel($de, $ate)];
        if ($vendedorId > 0) {
            $summary[] = 'VENDEDOR: '.$this->vendedorFilterLabel($vendedorId);
        }

        return $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'vendedor' => $vendedorId > 0 ? (string) $vendedorId : 'todos',
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary($summary, $request),
        );
    }

    public function expandDetails(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $hasEmpresa = Schema::hasColumn((new Venda)->getTable(), 'empresa_id');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = self::DETAILS_PER_PAGE;

        $expandVendedorId = (int) $request->query('vendedor_id', 0);
        $empresaId = (int) $request->query('empresa_id', 0);
        // Rótulo da linha (COALESCE) — distinto do filtro da sidebar (`vendedor`).
        $vendedorLabel = trim((string) $request->query('vendedor_label', ''));

        $query = $this->baseSalesQuery($request, $de->toDateString(), $ate->toDateString(), $hasEmpresa)
            ->leftJoin('vendedores', 'vendedores.id', '=', 'vendas.vendedor_id');

        // Mesmo agrupamento do relatório: id + rótulo COALESCE (evita misturar linhas).
        if ($expandVendedorId > 0) {
            $query->where('vendas.vendedor_id', $expandVendedorId);
        } else {
            $query->whereNull('vendas.vendedor_id');
        }

        if ($vendedorLabel !== '') {
            $query->whereRaw($this->vendedorLabelSql().' = ?', [$vendedorLabel]);
        }

        if ($hasEmpresa && $empresaId > 0) {
            $query->where('vendas.empresa_id', $empresaId);
        }

        $filterVendedorId = $this->vendedorIdFromRequest($request);
        if ($filterVendedorId > 0) {
            $query->where('vendas.vendedor_id', $filterVendedorId);
        }

        $totalsQuery = (clone $query)
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM('.static::sqlTable('vendas').'.total), 0) as total');
        $totals = $totalsQuery->first();
        $totalCount = (int) ($totals->qtd ?? 0);
        $sumTotal = (float) ($totals->total ?? 0);

        $vendaIds = (clone $query)
            ->orderByDesc('vendas.data')
            ->orderByDesc('vendas.id')
            ->forPage($page, $perPage)
            ->pluck('vendas.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $vendasById = $vendaIds === []
            ? collect()
            : Venda::query()
                ->with([
                    'cliente:id,nome_razao',
                    'forcaVendasOrder:id,venda_id',
                    'ordemServico:id,venda_id',
                ])
                ->whereIn('id', $vendaIds)
                ->get()
                ->keyBy('id');

        $rows = [];
        foreach ($vendaIds as $vendaId) {
            /** @var Venda|null $venda */
            $venda = $vendasById->get($vendaId);
            if ($venda === null) {
                continue;
            }

            $rows[] = [
                'id' => (int) $venda->id,
                'numero' => (string) $venda->numero,
                'data' => static::formatDate($venda->data),
                'cliente' => (string) ($venda->cliente?->nome_razao ?? ''),
                'total' => static::formatMoney((float) $venda->total),
                'situacao' => Venda::statusLabels()[$venda->status] ?? (string) $venda->status,
                'url' => $this->resolveVendaOpenUrl($venda),
            ];
        }

        $lastPage = max(1, (int) ceil($totalCount / $perPage));

        return [
            'rows' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage,
                'sum_total' => static::formatMoney($sumTotal),
            ],
        ];
    }

    /**
     * Critérios idênticos ao agregador do relatório (status fechado + período + empresa).
     */
    private function baseSalesQuery(Request $request, string $de, string $ate, bool $hasEmpresa): Builder
    {
        $query = Venda::query()
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereBetween('vendas.data', [$de, $ate]);

        // build() já faz leftJoin; expandDetails também. Aqui só filtros comuns.
        if ($hasEmpresa) {
            ReportEmpresaScope::applyToQuery($query, $request, 'vendas.empresa_id');
        }

        return $query;
    }

    private function vendedorLabelSql(): string
    {
        return 'COALESCE(NULLIF('.static::sqlTable('vendedores').'.nome, \'\'), NULLIF('
            .static::sqlTable('vendas').'.vendedor_nome, \'\'), \'SEM VENDEDOR\')';
    }

    private function vendedorIdFromRequest(Request $request): int
    {
        $raw = trim((string) $request->query('vendedor', 'todos'));

        if ($raw === '' || $raw === 'todos') {
            return 0;
        }

        return max(0, (int) $raw);
    }

    /**
     * @return array<string, string>
     */
    private function vendedorOptions(): array
    {
        $options = ['todos' => 'Todos os vendedores'];
        $empresaId = ErpContext::currentEmpresaId();

        $query = Vendedor::query()
            ->where('ativo', true)
            ->where('efetua_venda', true)
            ->whereHas('rhFuncionario')
            ->with('rhFuncionario');

        if ($empresaId) {
            $query->whereHas(
                'empresas',
                fn ($q) => $q->where('empresas.id', (int) $empresaId)
            );
        }

        $query
            ->get(['id', 'codigo', 'nome'])
            ->sortBy(fn (Vendedor $vendedor): int => (int) preg_replace('/\D/', '', (string) ($vendedor->rhFuncionario?->codigo ?? '0')))
            ->each(function (Vendedor $vendedor) use (&$options): void {
                $rh = $vendedor->rhFuncionario;
                $codigo = trim((string) ($rh?->codigo ?? $vendedor->codigo ?? ''));
                $nome = trim((string) ($rh?->nome ?? $vendedor->nome ?? ''));
                $label = trim(($codigo !== '' ? $codigo.' - ' : '').$nome);
                $options[(string) $vendedor->id] = $label !== '' ? $label : (string) ($vendedor->nome ?? '');
            });

        return $options;
    }

    private function vendedorFilterLabel(int $vendedorId): string
    {
        $options = $this->vendedorOptions();

        return $options[(string) $vendedorId] ?? ('#'.$vendedorId);
    }

    private function resolveVendaOpenUrl(Venda $venda): string
    {
        $os = $venda->ordemServico;
        if ($os !== null) {
            return OrdemServicoResource::getUrl('edit', ['record' => $os->getKey()]);
        }

        $order = $venda->forcaVendasOrder;
        if ($order !== null) {
            return ForcaVendasTelaVendaPage::getUrl(['pedido' => $order->getKey()]);
        }

        return VendaResource::getUrl('index');
    }
}
