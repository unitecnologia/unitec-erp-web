<?php

namespace App\Livewire\Erp;

use App\Models\Empresa;
use App\Models\Product;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Support\Erp\ProductListRowFormatter;
use App\Support\Erp\Queries\ProductListQueryBuilder;
use App\Support\Erp\Queries\ProductSerialListQueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ProductListTable extends Component
{
    use WithPagination;

    public string $statusFilter = 'ativos';

    public string $searchColumn = 'descricao';

    public string $localSearch = '';

    /** @var list<string> */
    public array $searchFieldsActive = [];

    public string $viewFilter = 'produtos';

    public int $perPage = 50;

    public ?string $sortColumn = null;

    public string $sortDirection = 'asc';

    public function setViewFilter(string $view): void
    {
        if (! in_array($view, ['produtos', 'seriais'], true)) {
            return;
        }

        $this->viewFilter = $view;
        $this->searchColumn = $view === 'seriais' ? 'descricao' : $this->searchColumn;
        if ($view !== 'seriais' && ! in_array($this->searchColumn, [
            'codigo', 'referencia', 'codigo_barras', 'descricao', 'grupo',
            'preco_venda', 'estoque', 'localizacao',
        ], true)) {
            $this->searchColumn = 'descricao';
        }
        $this->searchFieldsActive = [$this->searchColumn];
        $this->localSearch = '';
        $this->sortColumn = null;
        $this->sortDirection = 'asc';
        $this->resetPage();

        \App\Support\Erp\ErpScreen::set($view === 'seriais' ? 'Seriais' : 'Produtos');

        $this->js(sprintf(
            '(() => {
                const view = %s;
                const searchColumn = %s;
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) {
                    parent.set("viewFilter", view, false);
                    parent.set("searchColumn", searchColumn, false);
                    parent.set("searchFieldsActive", [searchColumn], false);
                    parent.set("searchFieldsQuery", "", false);
                    parent.set("localSearch", "", false);
                    try { parent.set("highlightedRecordId", null, false); } catch (e) {}
                    parent.call("persistProductSearchFields");
                }
                try {
                    const url = new URL(window.location.href);
                    if (view === "produtos") {
                        url.searchParams.delete("view");
                    } else {
                        url.searchParams.set("view", view);
                    }
                    url.searchParams.delete("campos");
                    url.searchParams.delete("q");
                    window.history.replaceState({}, "", url);
                } catch (e) {}
            })()',
            json_encode($view, JSON_UNESCAPED_UNICODE),
            json_encode($this->searchColumn, JSON_UNESCAPED_UNICODE),
            json_encode('app.filament.resources.product-resource.pages.list-products', JSON_UNESCAPED_UNICODE),
        ));
    }

    public function setStatusFilter(string $filter): void
    {
        if ($this->isSeriaisView()) {
            return;
        }

        if (! in_array($filter, ['ativos', 'inativos', 'todos'], true)) {
            return;
        }

        $this->statusFilter = $filter;
        $this->resetPage();

        $this->js(sprintf(
            '(() => {
                const status = %s;
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) {
                    parent.set("statusFilter", status, false);
                    try { parent.set("highlightedRecordId", null, false); } catch (e) {}
                }
                try {
                    const url = new URL(window.location.href);
                    if (status === "ativos") {
                        url.searchParams.delete("status");
                    } else {
                        url.searchParams.set("status", status);
                    }
                    window.history.replaceState({}, "", url);
                } catch (e) {}
            })()',
            json_encode($filter, JSON_UNESCAPED_UNICODE),
            json_encode('app.filament.resources.product-resource.pages.list-products', JSON_UNESCAPED_UNICODE),
        ));
    }

    #[On('erp-product-list-refresh')]
    public function refreshFromParent(
        string $statusFilter,
        string $searchColumn,
        string $localSearch,
        string $viewFilter,
        ?int $perPage = null,
        bool $resetSort = false,
        array $searchFieldsActive = [],
    ): void {
        $this->statusFilter = $statusFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
        $this->viewFilter = $viewFilter;
        $this->searchFieldsActive = $searchFieldsActive;

        if ($perPage !== null && $perPage > 0) {
            $this->perPage = $perPage;
        }

        if ($resetSort) {
            $this->sortColumn = null;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.erp.product-list-table', [
            'records' => $this->records(),
            'formatter' => app(ProductListRowFormatter::class),
            'isSeriais' => $this->isSeriaisView(),
        ]);
    }

    protected function records(): LengthAwarePaginator
    {
        $query = $this->buildQuery();
        $this->applySort($query);

        $empresaId = (int) ($this->currentEmpresa()?->id ?? 0);

        if ($empresaId > 0 && ! $this->isSeriaisView()) {
            $query->with(['empresaPrecos' => static function ($precos) use ($empresaId): void {
                $precos->where('empresa_id', $empresaId);
            }]);
        }

        return $query->paginate($this->perPage);
    }

    protected function buildQuery(): Builder
    {
        if ($this->isSeriaisView()) {
            return (new ProductSerialListQueryBuilder(
                searchColumn: $this->searchColumn,
                localSearch: $this->localSearch,
                empresa: $this->currentEmpresa(),
                searchFieldsActive: $this->searchFieldsActive,
            ))->build();
        }

        return (new ProductListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            empresa: $this->currentEmpresa(),
            applyDefaultOrder: false,
            searchFieldsActive: $this->searchFieldsActive,
        ))->build();
    }

    protected function applySort(Builder $query): void
    {
        if ($this->isSeriaisView()) {
            $dir = $this->sortDirection === 'desc' ? 'desc' : 'asc';
            $serialsTable = $query->getModel()->getTable();

            if ($this->sortColumn === 'descricao') {
                $productsTable = (new Product)->getTable();
                $query
                    ->leftJoin("{$productsTable} as erp_serial_product", 'erp_serial_product.id', '=', "{$serialsTable}.product_id")
                    ->orderBy('erp_serial_product.descricao', $dir)
                    ->select("{$serialsTable}.*");

                return;
            }

            $query->orderBy("{$serialsTable}.numero_serie", $dir);

            return;
        }

        $dir = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        if ($this->sortColumn === null) {
            if ($this->searchColumn === 'codigo') {
                ProductListQueryBuilder::orderByCodigoNumerico($query, $dir);

                return;
            }

            $query->orderBy('codigo');

            return;
        }

        if ($this->sortColumn === 'validade') {
            $query->orderByRaw("validade IS NULL ASC, validade {$dir}");

            return;
        }

        if ($this->sortColumn === 'estoque') {
            $empresaId = (int) ($this->currentEmpresa()?->id ?? 0);
            $estoqueService = app(ProductEstoqueSaldoService::class);

            if ($estoqueService->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null)) {
                $query->orderBy('estoque_empresa_atual', $dir);

                return;
            }

            $query->orderBy('estoque', $dir);

            return;
        }

        $allowed = ['codigo', 'descricao', 'grupo', 'preco_venda', 'lote'];

        if ($this->sortColumn === 'codigo') {
            ProductListQueryBuilder::orderByCodigoNumerico($query, $dir);

            return;
        }

        if (in_array($this->sortColumn, $allowed, true)) {
            $query->orderBy($this->sortColumn, $dir);
        }
    }

    protected function isSeriaisView(): bool
    {
        return $this->viewFilter === 'seriais';
    }

    protected function currentEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }
}
