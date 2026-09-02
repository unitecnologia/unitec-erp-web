<?php

namespace App\Livewire\Erp;

use App\Support\Erp\ErpTableSort;
use App\Support\Erp\PersonListRowFormatter;
use App\Support\Erp\Queries\PersonListQueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class PersonListTable extends Component
{
    use WithPagination;

    public string $statusFilter = 'ativos';

    public string $tipoFilter = 'clientes';

    public string $searchColumn = 'nome_razao';

    public string $localSearch = '';

    public int $perPage = 50;

    public ?string $sortColumn = null;

    public string $sortDirection = 'asc';

    #[On('erp-person-list-refresh')]
    public function refreshFromParent(
        string $statusFilter,
        string $tipoFilter,
        string $searchColumn,
        string $localSearch,
        ?int $perPage = null,
        bool $resetSort = false,
    ): void {
        $this->statusFilter = $statusFilter;
        $this->tipoFilter = $tipoFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;

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
        return view('livewire.erp.person-list-table', [
            'records' => $this->records(),
            'formatter' => app(PersonListRowFormatter::class),
        ]);
    }

    protected function records(): LengthAwarePaginator
    {
        $query = (new PersonListQueryBuilder(
            statusFilter: $this->statusFilter,
            tipoFilter: $this->tipoFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            applyDefaultOrder: false,
        ))->buildForList();

        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    protected function applySort(Builder $query): void
    {
        if ($this->sortColumn === null) {
            ErpTableSort::orderByCodigoNumerico($query);

            return;
        }

        $dir = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        if ($this->sortColumn === 'codigo') {
            ErpTableSort::orderByCodigoNumerico($query, $dir);

            return;
        }

        $allowed = ['nome_razao', 'apelido_fantasia', 'cpf_cnpj'];

        if (in_array($this->sortColumn, $allowed, true)) {
            $query->orderBy($this->sortColumn, $dir);
        }
    }
}
