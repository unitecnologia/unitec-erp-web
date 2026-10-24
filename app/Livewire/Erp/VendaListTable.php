<?php

namespace App\Livewire\Erp;

use App\Models\Venda;
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

    /** @var list<string> */
    public array $searchFieldsActive = ['data'];

    public int $perPage = 50;

    public ?string $sortColumn = null;

    public string $sortDirection = 'desc';

    /**
     * Troca de aba de status: 1 request (grade + total + URL).
     */
    public function setStatusFilter(string $filter): void
    {
        $allowed = [
            'todos',
            Venda::STATUS_ABERTO,
            Venda::STATUS_GRAVADO,
            Venda::STATUS_FECHADO,
            Venda::STATUS_CANCELADO,
        ];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->statusFilter = $filter;
        $this->resetPage();

        $ui = $this->queryBuilder()->indicatorsUi();

        $this->js(sprintf(
            '(() => {
                const status = %s;
                const indicators = %s;
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) {
                    parent.set("statusFilter", status, false);
                    try { parent.set("highlightedRecordId", null, false); } catch (e) {}
                }
                try {
                    const url = new URL(window.location.href);
                    if (status === "todos") {
                        url.searchParams.delete("status");
                    } else {
                        url.searchParams.set("status", status);
                    }
                    window.history.replaceState({}, "", url);
                } catch (e) {}
                window.ErpVendasIndicators && window.ErpVendasIndicators.apply(indicators);
                window.ErpVendasStatusFilter && window.ErpVendasStatusFilter.syncUI(status);
            })()',
            json_encode($filter, JSON_UNESCAPED_UNICODE),
            json_encode($ui, JSON_UNESCAPED_UNICODE),
            json_encode('app.filament.resources.venda-resource.pages.list-vendas', JSON_UNESCAPED_UNICODE),
        ));
    }

    public function setTipoFilter(string $filter): void
    {
        $allowed = VendaListQueryBuilder::documentTabKeys();

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->tipoFilter = $filter;
        $this->resetPage();

        $ui = $this->queryBuilder()->indicatorsUi();

        $this->js(sprintf(
            '(() => {
                const tipo = %s;
                const indicators = %s;
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) {
                    parent.set("tipoFilter", tipo, false);
                    try { parent.set("highlightedRecordId", null, false); } catch (e) {}
                }
                try {
                    const url = new URL(window.location.href);
                    if (tipo === "todos") {
                        url.searchParams.delete("tipo");
                    } else {
                        url.searchParams.set("tipo", tipo);
                    }
                    window.history.replaceState({}, "", url);
                } catch (e) {}
                window.ErpVendasIndicators && window.ErpVendasIndicators.apply(indicators);
            })()',
            json_encode($filter, JSON_UNESCAPED_UNICODE),
            json_encode($ui, JSON_UNESCAPED_UNICODE),
            json_encode('app.filament.resources.venda-resource.pages.list-vendas', JSON_UNESCAPED_UNICODE),
        ));
    }

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
        array $searchFieldsActive = [],
    ): void {
        $this->statusFilter = $statusFilter;
        $this->tipoFilter = $tipoFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
        $this->localSearchDe = $localSearchDe;
        $this->localSearchAte = $localSearchAte;
        $this->localSearchHoraDe = $localSearchHoraDe;
        $this->localSearchHoraAte = $localSearchHoraAte;
        $this->searchFieldsActive = array_values(array_filter(
            $searchFieldsActive,
            fn (mixed $column): bool => is_string($column) && $column !== '',
        ));

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

    protected function queryBuilder(bool $applyDefaultOrder = true): VendaListQueryBuilder
    {
        return new VendaListQueryBuilder(
            statusFilter: $this->statusFilter,
            tipoFilter: $this->tipoFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            localSearchDe: $this->localSearchDe,
            localSearchAte: $this->localSearchAte,
            localSearchHoraDe: $this->localSearchHoraDe,
            localSearchHoraAte: $this->localSearchHoraAte,
            applyDefaultOrder: $applyDefaultOrder,
            searchFieldsActive: $this->searchFieldsActive,
        );
    }

    protected function records(): LengthAwarePaginator
    {
        $query = $this->queryBuilder(applyDefaultOrder: false)->buildForList();

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
