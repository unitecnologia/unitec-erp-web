<?php

namespace App\Livewire\Erp;

use App\Support\Erp\ErpTableSort;
use App\Support\Erp\Queries\VendaListQueryBuilder;
use App\Support\Erp\VendaListRowFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class VendaListTable extends Component
{
    use WithPagination;

    public string $statusFilter = 'todos';

    public string $tipoFilter = 'todos';

    public string $searchColumn = 'data';

    public string $localSearch = '';

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    public string $localSearchHoraDe = '';

    public string $localSearchHoraAte = '';

    public int $perPage = 50;

    public ?string $sortColumn = null;

    public string $sortDirection = 'desc';

    #[On('erp-venda-list-refresh')]
    public function refreshFromParent(
        string $statusFilter,
        string $tipoFilter,
        string $searchColumn,
        string $localSearch,
        string $localSearchDe = '',
        string $localSearchAte = '',
        string $localSearchHoraDe = '',
        string $localSearchHoraAte = '',
        ?int $perPage = null,
        bool $resetSort = false,
    ): void {
        $this->statusFilter = $statusFilter;
        $this->tipoFilter = $tipoFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
        $this->localSearchDe = $localSearchDe;
        $this->localSearchAte = $localSearchAte;
        $this->localSearchHoraDe = $localSearchHoraDe;
        $this->localSearchHoraAte = $localSearchHoraAte;

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
            $this->sortDirection = in_array($column, ['numero', 'data', 'hora', 'hora_abertura'], true) ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.erp.venda-list-table', [
            'records' => $this->records(),
            'formatter' => app(VendaListRowFormatter::class),
        ]);
    }

    protected function records(): LengthAwarePaginator
    {
        $query = (new VendaListQueryBuilder(
            statusFilter: $this->statusFilter,
            tipoFilter: $this->tipoFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            localSearchDe: $this->localSearchDe,
            localSearchAte: $this->localSearchAte,
            localSearchHoraDe: $this->localSearchHoraDe,
            localSearchHoraAte: $this->localSearchHoraAte,
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
        $allowed = ['numero', 'data', 'hora_abertura', 'hora', 'total'];

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
