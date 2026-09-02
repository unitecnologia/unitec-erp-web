<?php

namespace App\Livewire\Erp;

use App\Support\Erp\ContaReceberListRowFormatter;
use App\Support\Erp\Queries\ContaReceberListQueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ContaReceberListTable extends Component
{
    use WithPagination;

    public string $situacaoFilter = 'todos';

    public string $formaFilter = 'todos';

    public string $clienteFilter = 'todos';

    public string $searchColumn = 'cliente';

    public string $localSearch = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public bool $skipLocalSearch = false;

    public int $perPage = 50;

    /** @var array<int, int|string> */
    public array $selecionadosParaBaixa = [];

    public ?string $sortColumn = null;

    public string $sortDirection = 'desc';

    #[On('erp-receber-list-refresh')]
    public function refreshFromParent(
        string $situacaoFilter,
        string $formaFilter,
        string $clienteFilter,
        string $searchColumn,
        string $localSearch,
        string $periodoDeApplied = '',
        string $periodoAteApplied = '',
        bool $skipLocalSearch = false,
        ?int $perPage = null,
        array $selecionadosParaBaixa = [],
        bool $resetSort = false,
    ): void {
        $this->situacaoFilter = $situacaoFilter;
        $this->formaFilter = $formaFilter;
        $this->clienteFilter = $clienteFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
        $this->periodoDeApplied = $periodoDeApplied;
        $this->periodoAteApplied = $periodoAteApplied;
        $this->skipLocalSearch = $skipLocalSearch;
        $this->selecionadosParaBaixa = $selecionadosParaBaixa;

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
            $this->sortDirection = in_array($column, ['numero', 'emissao', 'vencimento'], true) ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.erp.conta-receber-list-table', [
            'records' => $this->records(),
            'formatter' => app(ContaReceberListRowFormatter::class),
        ]);
    }

    protected function records(): LengthAwarePaginator
    {
        $query = (new ContaReceberListQueryBuilder(
            situacaoFilter: $this->situacaoFilter,
            formaFilter: $this->formaFilter,
            clienteFilter: $this->clienteFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDe: $this->periodoDeApplied,
            periodoAte: $this->periodoAteApplied,
            skipLocalSearch: $this->skipLocalSearch,
            applyDefaultOrder: false,
        ))->buildForList();

        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    protected function applySort(Builder $query): void
    {
        if ($this->sortColumn === null) {
            $query->orderByDesc('emissao')->orderByDesc('numero');

            return;
        }

        $dir = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $allowed = ['numero', 'emissao', 'vencimento'];

        if (! in_array($this->sortColumn, $allowed, true)) {
            $query->orderByDesc('emissao')->orderByDesc('numero');

            return;
        }

        $query->orderBy($this->sortColumn, $dir);

        if ($this->sortColumn !== 'numero') {
            $query->orderByDesc('numero');
        }
    }
}
