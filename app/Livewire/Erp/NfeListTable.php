<?php

namespace App\Livewire\Erp;

use App\Models\Nfe;
use App\Support\Erp\ErpTableSort;
use App\Support\Erp\Nfe\NfeListRowFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class NfeListTable extends Component
{
    use WithPagination;

    private const PARENT_NAME = 'app.filament.resources.nfe-resource.pages.list-nfes';

    public string $statusFilter = 'todas';

    public string $searchColumn = 'cliente';

    /** @var list<string> */
    public array $searchFieldsActive = ['cliente', 'data_emissao'];

    /** @var array<string, string> */
    public array $localSearchByField = [];

    public string $clienteFilter = 'todos';

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    public bool $skipLocalClienteSearch = false;

    public int $perPage = 50;

    public ?string $sortColumn = null;

    public string $sortDirection = 'desc';

    public ?int $highlightedRecordId = null;

    /**
     * Troca de aba: 1 request só na grade (+ totais via JS). Sem re-render do Filament pai.
     */
    public function setStatusFilter(string $filter): void
    {
        $normalized = $this->normalizeStatusFilter($filter);

        if ($normalized === $this->statusFilter) {
            $this->skipRender();

            return;
        }

        $this->statusFilter = $normalized;
        $this->highlightedRecordId = null;
        $this->sortColumn = null;
        $this->sortDirection = 'desc';
        $this->resetPage();

        $bruto = $this->computeFilteredTotal();
        $canceladas = $this->computeFilteredTotalCanceladas();
        $liquido = round($bruto - $canceladas, 2);

        $this->js(sprintf(
            '(() => {
                const status = %s;
                const brutoLabel = %s;
                const canceladasLabel = %s;
                const liquidoLabel = %s;
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) {
                    parent.set("statusFilter", status, false);
                    try { parent.set("highlightedRecordId", null, false); } catch (e) {}
                }
                try {
                    const url = new URL(window.location.href);
                    if (status === "todas") {
                        url.searchParams.delete("status");
                    } else {
                        url.searchParams.set("status", status);
                    }
                    window.history.replaceState({}, "", url);
                } catch (e) {}
                const page = document.querySelector(".erp-nfe-page");
                if (!page) return;
                const brutoEl = page.querySelector(".erp-nfe__total-value--bruto");
                if (brutoEl) brutoEl.textContent = brutoLabel;
                const cancelEl = page.querySelector(".erp-nfe__total-value--canceladas");
                if (cancelEl) cancelEl.textContent = canceladasLabel;
                const liquidoEl = page.querySelector(".erp-nfe__total-value--liquido");
                if (liquidoEl) liquidoEl.textContent = liquidoLabel;
                page.querySelectorAll(".fi-ta-row.erp-row-selected").forEach((row) => {
                    row.classList.remove("erp-row-selected");
                });
                page.querySelectorAll(".erp-nfe__select-check").forEach((el) => {
                    el.checked = false;
                });
            })()',
            json_encode($normalized, JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($bruto, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($canceladas, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($liquido, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode(self::PARENT_NAME, JSON_UNESCAPED_UNICODE),
        ));
    }

    /**
     * @param  list<string>  $searchFieldsActive
     * @param  array<string, string>  $localSearchByField
     */
    #[On('erp-nfe-list-refresh')]
    public function refreshFromParent(
        string $statusFilter,
        string $searchColumn,
        array $searchFieldsActive = [],
        array $localSearchByField = [],
        string $clienteFilter = 'todos',
        string $localSearchDe = '',
        string $localSearchAte = '',
        bool $skipLocalClienteSearch = false,
        ?int $perPage = null,
        bool $resetSort = false,
        ?int $highlightedRecordId = null,
    ): void {
        $this->statusFilter = $this->normalizeStatusFilter($statusFilter);
        $this->searchColumn = $searchColumn;
        $this->searchFieldsActive = array_values(array_filter(
            $searchFieldsActive,
            static fn (mixed $column): bool => is_string($column) && $column !== '',
        ));
        $this->localSearchByField = $localSearchByField;
        $this->clienteFilter = $clienteFilter;
        $this->localSearchDe = $localSearchDe;
        $this->localSearchAte = $localSearchAte;
        $this->skipLocalClienteSearch = $skipLocalClienteSearch;
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
        if (! in_array($column, ['numero', 'data_emissao'], true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = $column === 'numero' ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function toggleSelecionado(int $id): void
    {
        $this->highlightedRecordId = ((int) $this->highlightedRecordId === $id) ? null : $id;

        $this->js(sprintf(
            '(() => {
                const id = %d;
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) {
                    try { parent.set("highlightedRecordId", id > 0 ? id : null, false); } catch (e) {}
                }
                const page = document.querySelector(".erp-nfe-page");
                if (!page) return;
                page.querySelectorAll(".fi-ta-row.erp-row-selected").forEach((row) => {
                    row.classList.remove("erp-row-selected");
                });
                if (id > 0) {
                    const row = page.querySelector(\'.fi-ta-row[data-record-key="\' + String(id) + \'"]\');
                    if (row) row.classList.add("erp-row-selected");
                }
            })()',
            (int) ($this->highlightedRecordId ?? 0),
            json_encode(self::PARENT_NAME, JSON_UNESCAPED_UNICODE),
        ));
    }

    public function openHistorico(int $id): void
    {
        $this->js(sprintf(
            '(() => {
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) parent.call("openNfeHistorico", %d);
            })()',
            json_encode(self::PARENT_NAME, JSON_UNESCAPED_UNICODE),
            $id,
        ));
    }

    public function render(): View
    {
        return view('livewire.erp.nfe-list-table', [
            'records' => $this->records(),
            'formatter' => app(NfeListRowFormatter::class),
        ]);
    }

    protected function records(): LengthAwarePaginator
    {
        $query = $this->buildListQuery();
        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    protected function computeFilteredTotal(): float
    {
        return (float) $this->buildFilterQuery()->sum('total');
    }

    protected function computeFilteredTotalCanceladas(): float
    {
        return (float) $this->buildFilterQuery()
            ->where('status', Nfe::STATUS_CANCELADA)
            ->sum('total');
    }

    /**
     * @return Builder<Nfe>
     */
    protected function buildFilterQuery(): Builder
    {
        $table = (new Nfe)->getTable();
        $query = Nfe::query();

        $empresaId = \App\Support\Erp\ErpContext::currentEmpresaId();

        if ($empresaId !== null) {
            $query->where("{$table}.empresa_id", $empresaId);
        }

        if ($this->statusFilter !== 'todas') {
            $query->where("{$table}.status", $this->statusFilter);
        }

        foreach ($this->normalizedSearchFieldsActive() as $column) {
            if ($this->isDateSearchColumn($column)) {
                $this->applyLocalSearchDateRange($query, $column);

                continue;
            }

            if ($column === 'cliente') {
                if ($this->clienteFilter !== 'todos' && is_numeric($this->clienteFilter)) {
                    $query->where("{$table}.cliente_id", (int) $this->clienteFilter);

                    continue;
                }

                if ($this->skipLocalClienteSearch) {
                    continue;
                }
            }

            $term = trim((string) ($this->localSearchByField[$column] ?? ''));

            if ($term === '') {
                continue;
            }

            $this->applyLocalSearchForColumn($query, $term, $column);
        }

        return $query;
    }

    /**
     * @return Builder<Nfe>
     */
    protected function buildListQuery(): Builder
    {
        $table = (new Nfe)->getTable();

        return $this->buildFilterQuery()
            ->select([
                "{$table}.id",
                "{$table}.empresa_id",
                "{$table}.cliente_id",
                "{$table}.venda_id",
                "{$table}.numero",
                "{$table}.npedido",
                "{$table}.data_emissao",
                "{$table}.data_saida",
                "{$table}.chave",
                "{$table}.protocolo",
                "{$table}.finalidade",
                "{$table}.status",
                "{$table}.total",
                "{$table}.obs_contribuinte",
            ])
            ->with([
                'cliente' => static fn ($q) => $q->select(['id', 'nome_razao']),
                'venda' => static fn ($q) => $q->select(['id', 'numero']),
            ]);
    }

    /**
     * @param  Builder<Nfe>  $query
     */
    protected function applySort(Builder $query): void
    {
        $dir = $this->sortDirection === 'asc' ? 'asc' : 'desc';
        $table = $query->getModel()->getTable();

        if ($this->sortColumn === 'data_emissao') {
            $query->orderBy("{$table}.data_emissao", $dir)->orderByDesc("{$table}.id");

            return;
        }

        if ($this->sortColumn === 'numero') {
            ErpTableSort::orderByCodigoNumerico($query, $dir, 'numero', desempateIdDesc: true);

            return;
        }

        ErpTableSort::orderByCodigoNumerico($query, 'desc', 'numero', desempateIdDesc: true);
    }

    /**
     * @return list<string>
     */
    protected function localSearchColumns(): array
    {
        return ['numero', 'data_emissao', 'data_saida', 'cliente', 'chave', 'protocolo', 'total'];
    }

    /**
     * @return list<string>
     */
    protected function normalizedSearchFieldsActive(): array
    {
        $allowed = $this->localSearchColumns();
        $active = array_values(array_filter(
            $this->searchFieldsActive,
            fn (mixed $column): bool => is_string($column) && in_array($column, $allowed, true),
        ));

        if ($active === []) {
            $fallback = in_array($this->searchColumn, $allowed, true) ? $this->searchColumn : 'cliente';

            return [$fallback];
        }

        return array_values(array_unique($active));
    }

    protected function isDateSearchColumn(string $column): bool
    {
        return in_array($column, ['data_emissao', 'data_saida'], true);
    }

    /**
     * @param  Builder<Nfe>  $query
     */
    protected function applyLocalSearchDateRange(Builder $query, string $column): void
    {
        if (! filled($this->localSearchDe) && ! filled($this->localSearchAte)) {
            return;
        }

        if (filled($this->localSearchDe)) {
            $query->whereDate($column, '>=', $this->localSearchDe);
        }

        if (filled($this->localSearchAte)) {
            $query->whereDate($column, '<=', $this->localSearchAte);
        }
    }

    /**
     * @param  Builder<Nfe>  $query
     */
    protected function applyLocalSearchForColumn(Builder $query, string $term, string $column): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $like),
            'cliente' => $query->whereHas(
                'cliente',
                fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $like),
            ),
            'chave' => $query->where('chave', 'like', '%'.(preg_replace('/\D/', '', $term) ?? '').'%'),
            'protocolo' => $query->where('protocolo', 'like', $like),
            'total' => $this->applyLocalSearchByTotal($query, $term),
            default => null,
        };
    }

    /**
     * @param  Builder<Nfe>  $query
     */
    protected function applyLocalSearchByTotal(Builder $query, string $term): void
    {
        $normalized = str_replace(['R$', ' '], '', $term);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            if ($query->getConnection()->getDriverName() === 'sqlite') {
                $query->whereRaw('CAST(total AS TEXT) LIKE ?', ['%'.$normalized.'%']);

                return;
            }

            $query->where('total', 'like', '%'.$normalized.'%');

            return;
        }

        if ($query->getConnection()->getDriverName() === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', total), '.', ',') LIKE ?", ['%'.$term.'%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT(total, 2), '.', ',') LIKE ?", ['%'.$term.'%']);
    }

    protected function normalizeStatusFilter(string $filter): string
    {
        $allowed = [
            'todas',
            Nfe::STATUS_ABERTA,
            Nfe::STATUS_TRANSMITIDA,
            Nfe::STATUS_CANCELADA,
            Nfe::STATUS_DUPLICIDADE,
            Nfe::STATUS_INUTILIZADA,
            Nfe::STATUS_DENEGADA,
            Nfe::STATUS_CONTINGENCIA,
        ];

        return in_array($filter, $allowed, true) ? $filter : Nfe::STATUS_ABERTA;
    }
}
