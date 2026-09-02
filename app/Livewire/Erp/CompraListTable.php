<?php

namespace App\Livewire\Erp;

use App\Support\Erp\CompraListRowFormatter;
use App\Support\Erp\ErpTableSort;
use App\Support\Erp\Queries\CompraListQueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class CompraListTable extends Component
{
    use WithPagination;

    public string $statusFilter = 'todas';

    public string $searchColumn = 'fornecedor';

    public string $localSearch = '';

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    public int $perPage = 50;

    public ?string $sortColumn = null;

    public string $sortDirection = 'desc';

    #[On('erp-compra-list-refresh')]
    public function refreshFromParent(
        string $statusFilter,
        string $searchColumn,
        string $localSearch,
        string $localSearchDe = '',
        string $localSearchAte = '',
        ?int $perPage = null,
        bool $resetSort = false,
    ): void {
        $this->statusFilter = $statusFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
        $this->localSearchDe = $localSearchDe;
        $this->localSearchAte = $localSearchAte;

        if ($perPage !== null && $perPage > 0) {
            $this->perPage = $perPage;
        }

        if ($resetSort) {
            $this->sortColumn = null;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = in_array($column, ['numero', 'data_emissao'], true) ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.erp.compra-list-table', [
            'records' => $this->records(),
            'formatter' => app(CompraListRowFormatter::class),
        ]);
    }

    protected function records(): LengthAwarePaginator
    {
        $query = (new CompraListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            localSearchDe: $this->localSearchDe,
            localSearchAte: $this->localSearchAte,
            applyDefaultOrder: false,
        ))->buildForList();

        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    protected function applySort(Builder $query): void
    {
        if ($this->sortColumn === null) {
            ErpTableSort::orderByCodigoNumerico($query, 'desc', 'numero');

            return;
        }

        $dir = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $allowed = ['numero', 'data_emissao', 'data_entrada'];

        if (! in_array($this->sortColumn, $allowed, true)) {
            ErpTableSort::orderByCodigoNumerico($query, 'desc', 'numero');

            return;
        }

        if ($this->sortColumn === 'numero') {
            ErpTableSort::orderByCodigoNumerico($query, $dir, 'numero');

            return;
        }

        $query->orderBy($this->sortColumn, $dir);
    }
}
