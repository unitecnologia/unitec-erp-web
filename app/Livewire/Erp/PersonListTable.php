<?php

namespace App\Livewire\Erp;

use App\Support\Erp\ErpScreen;
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

    /** @var list<string> */
    public array $searchFieldsActive = [];

    public int $perPage = 50;

    public ?string $sortColumn = null;

    public string $sortDirection = 'asc';

    public function setTipoFilter(string $tipo): void
    {
        if (! in_array($tipo, ['clientes', 'funcionarios', 'fornecedores', 'administradoras', 'parceiros', 'todos'], true)) {
            return;
        }

        $this->tipoFilter = $tipo;
        $this->localSearch = '';
        $this->sortColumn = null;
        $this->sortDirection = 'asc';
        $this->resetPage();

        ErpScreen::set(match ($tipo) {
            'ccf_spc' => 'Lista SPC/CCF',
            'todos' => 'Contatos',
            default => 'Pessoas',
        });

        $this->js(sprintf(
            '(() => {
                const tipo = %s;
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) {
                    parent.set("tipoFilter", tipo, false);
                    parent.set("localSearch", "", false);
                    try { parent.set("highlightedRecordId", null, false); } catch (e) {}
                }
                try {
                    const url = new URL(window.location.href);
                    if (tipo === "clientes") {
                        url.searchParams.delete("tipo");
                    } else {
                        url.searchParams.set("tipo", tipo);
                    }
                    window.history.replaceState({}, "", url);
                } catch (e) {}
            })()',
            json_encode($tipo, JSON_UNESCAPED_UNICODE),
            json_encode('app.filament.resources.person-resource.pages.list-people', JSON_UNESCAPED_UNICODE),
        ));
    }

    public function setStatusFilter(string $filter): void
    {
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
            json_encode('app.filament.resources.person-resource.pages.list-people', JSON_UNESCAPED_UNICODE),
        ));
    }

    #[On('erp-person-list-refresh')]
    public function refreshFromParent(
        string $statusFilter,
        string $tipoFilter,
        string $searchColumn,
        string $localSearch,
        ?int $perPage = null,
        bool $resetSort = false,
        array $searchFieldsActive = [],
    ): void {
        $this->statusFilter = $statusFilter;
        $this->tipoFilter = $tipoFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
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
            searchFieldsActive: $this->searchFieldsActive,
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
