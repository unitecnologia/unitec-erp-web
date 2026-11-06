<?php

namespace App\Livewire\Erp;

use App\Models\OrdemServico;
use App\Support\Erp\OsListRowFormatter;
use App\Support\Erp\Queries\OsListQueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class OsListTable extends Component
{
    use WithPagination;

    public string $statusFilter = 'todos';

    public string $searchColumn = 'cliente';

    public string $localSearch = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public int $perPage = 50;

    public ?int $highlightedRecordId = null;

    public ?string $sortColumn = null;

    public string $sortDirection = 'desc';

    /**
     * Troca de aba de status: atualiza a grade e sincroniza o pai via JS (sem remount do pai).
     */
    public function setStatusFilter(string $filter): void
    {
        $allowed = [
            'todos',
            OrdemServico::SITUACAO_ABERTA,
            OrdemServico::SITUACAO_FINALIZADA,
            OrdemServico::SITUACAO_CANCELADA,
        ];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->statusFilter = $filter;
        $this->highlightedRecordId = null;
        $this->resetPage();

        $total = (new OsListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
        ))->sumFilteredTotal();

        $this->js(sprintf(
            '(() => {
                const status = %s;
                const totalLabel = %s;
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) {
                    parent.set("statusFilter", status, false);
                    try { parent.set("highlightedRecordId", null, false); } catch (e) {}
                    try { parent.set("selectedOsMeta", null, false); } catch (e) {}
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
                const el = document.querySelector(".erp-os-page .erp-orcamentos__total-value");
                if (el) el.textContent = totalLabel;
            })()',
            json_encode($filter, JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($total, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode('app.filament.resources.ordem-servico-resource.pages.list-ordens-servico', JSON_UNESCAPED_UNICODE),
        ));
    }

    #[On('erp-os-list-refresh')]
    public function refreshFromParent(
        string $statusFilter,
        string $searchColumn,
        string $localSearch,
        string $periodoDeApplied = '',
        string $periodoAteApplied = '',
        ?int $perPage = null,
        bool $resetSort = false,
        ?int $highlightedRecordId = null,
    ): void {
        $this->statusFilter = $statusFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
        $this->periodoDeApplied = $periodoDeApplied;
        $this->periodoAteApplied = $periodoAteApplied;
        $this->highlightedRecordId = $highlightedRecordId;

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
        $allowed = ['numero', 'data'];

        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.erp.os-list-table', [
            'records' => $this->records(),
            'formatter' => app(OsListRowFormatter::class),
        ]);
    }

    protected function records(): LengthAwarePaginator
    {
        $query = (new OsListQueryBuilder(
            statusFilter: $this->statusFilter,
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
        $dir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        if ($this->sortColumn === 'numero') {
            $query->orderBy('numero', $dir)->orderBy('id', $dir);

            return;
        }

        if ($this->sortColumn === 'data') {
            $query->orderBy('data_inicio', $dir)->orderBy('id', $dir);

            return;
        }

        $query->orderByDesc('data_inicio')->orderByDesc('id');
    }
}
