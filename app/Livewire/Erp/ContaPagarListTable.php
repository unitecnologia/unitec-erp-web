<?php

namespace App\Livewire\Erp;

use App\Support\Erp\ContaPagarListRowFormatter;
use App\Support\Erp\Queries\ContaPagarListQueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ContaPagarListTable extends Component
{
    use WithPagination;

    public string $situacaoFilter = 'todos';

    public string $fornecedorFilter = 'todos';

    /** @var list<string> */
    public array $searchFieldsActive = ['fornecedor', 'vencimento'];

    /** @var array<string, string> */
    public array $localSearchByField = [];

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    public bool $skipFornecedorSearch = false;

    public int $perPage = 50;

    public ?string $sortColumn = null;

    public string $sortDirection = 'asc';

    #[On('erp-pagar-list-refresh')]
    public function refreshFromParent(
        string $situacaoFilter,
        string $fornecedorFilter,
        array $searchFieldsActive,
        array $localSearchByField,
        string $localSearchDe = '',
        string $localSearchAte = '',
        bool $skipFornecedorSearch = false,
        ?int $perPage = null,
        bool $resetSort = false,
    ): void {
        $this->situacaoFilter = $situacaoFilter;
        $this->fornecedorFilter = $fornecedorFilter;
        $this->searchFieldsActive = $searchFieldsActive;
        $this->localSearchByField = $localSearchByField;
        $this->localSearchDe = $localSearchDe;
        $this->localSearchAte = $localSearchAte;
        $this->skipFornecedorSearch = $skipFornecedorSearch;

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
            $this->sortDirection = in_array($column, ['numero', 'emissao'], true) ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.erp.conta-pagar-list-table', [
            'records' => $this->records(),
            'formatter' => app(ContaPagarListRowFormatter::class),
        ]);
    }

    protected function records(): LengthAwarePaginator
    {
        $query = (new ContaPagarListQueryBuilder(
            situacaoFilter: $this->situacaoFilter,
            fornecedorFilter: $this->fornecedorFilter,
            searchFieldsActive: $this->searchFieldsActive,
            localSearchByField: $this->localSearchByField,
            localSearchDe: $this->localSearchDe,
            localSearchAte: $this->localSearchAte,
            skipFornecedorSearch: $this->skipFornecedorSearch,
            applyDefaultOrder: false,
        ))->buildForList();

        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    protected function applySort(Builder $query): void
    {
        if ($this->sortColumn === null) {
            $query->orderBy('vencimento', 'asc');

            return;
        }

        $dir = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $allowed = ['numero', 'emissao', 'vencimento'];

        if (! in_array($this->sortColumn, $allowed, true)) {
            $query->orderBy('vencimento', 'asc');

            return;
        }

        $query->orderBy($this->sortColumn, $dir);
    }
}
