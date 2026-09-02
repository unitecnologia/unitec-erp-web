<?php

namespace App\Livewire\Erp;

use App\Support\Erp\CaixaListRowFormatter;
use App\Support\Erp\ErpTableSort;
use App\Support\Erp\Queries\CaixaListQueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class CaixaListTable extends Component
{
    use WithPagination;

    public string $contaFilter = 'todas';

    public string $searchColumn = 'codigo';

    public string $localSearch = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public int $perPage = 50;

    public ?string $sortColumn = null;

    public string $sortDirection = 'desc';

    #[On('erp-caixa-list-refresh')]
    public function refreshFromParent(
        string $contaFilter,
        string $searchColumn,
        string $localSearch,
        string $periodoDeApplied = '',
        string $periodoAteApplied = '',
        ?int $perPage = null,
        bool $resetSort = false,
    ): void {
        $this->contaFilter = $contaFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
        $this->periodoDeApplied = $periodoDeApplied;
        $this->periodoAteApplied = $periodoAteApplied;

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
            $this->sortDirection = in_array($column, ['codigo', 'emissao', 'entrada', 'saida'], true) ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.erp.caixa-list-table', [
            'records' => $this->records(),
            'formatter' => app(CaixaListRowFormatter::class),
        ]);
    }

    protected function records(): LengthAwarePaginator
    {
        $query = (new CaixaListQueryBuilder(
            contaFilter: $this->contaFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
            applyDefaultOrder: false,
        ))->buildForList();

        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    protected function applySort(Builder $query): void
    {
        if ($this->sortColumn === null) {
            $query->orderByDesc('codigo');

            return;
        }

        $dir = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $allowed = ['codigo', 'emissao', 'entrada', 'saida'];

        if (! in_array($this->sortColumn, $allowed, true)) {
            $query->orderByDesc('codigo');

            return;
        }

        if ($this->sortColumn === 'codigo') {
            ErpTableSort::orderByCodigoNumerico($query, $dir, 'codigo');

            return;
        }

        $query->orderBy($this->sortColumn, $dir);
    }
}
