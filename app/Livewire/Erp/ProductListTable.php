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

    public string $viewFilter = 'produtos';

    public int $perPage = 50;

    public ?string $sortColumn = null;

    public string $sortDirection = 'asc';

    #[On('erp-product-list-refresh')]
    public function refreshFromParent(
        string $statusFilter,
        string $searchColumn,
        string $localSearch,
        string $viewFilter,
        ?int $perPage = null,
        bool $resetSort = false,
    ): void {
        $this->statusFilter = $statusFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
        $this->viewFilter = $viewFilter;

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
            ))->build();
        }

        return (new ProductListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            empresa: $this->currentEmpresa(),
            applyDefaultOrder: false,
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

        if ($this->sortColumn === null) {
            $query->orderBy('codigo');

            return;
        }

        $dir = $this->sortDirection === 'desc' ? 'desc' : 'asc';

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
