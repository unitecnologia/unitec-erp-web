<?php

namespace App\Livewire\Erp;

use App\Models\Compra;
use App\Support\Erp\CompraListRowFormatter;
use App\Support\Erp\Compras\CompraDevolucaoIndicadorService;
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

    /** @var list<string> */
    public array $searchFieldsActive = [];

    /** @var array<string, string> */
    public array $localSearchByField = [];

    public int $perPage = 50;

    public ?string $sortColumn = null;

    public string $sortDirection = 'desc';

    /**
     * Troca de aba de status: 1 request (grade + total + URL).
     */
    public function setStatusFilter(string $filter): void
    {
        $allowed = [
            'todas',
            Compra::STATUS_ABERTA,
            Compra::STATUS_FECHADA,
            Compra::STATUS_CANCELADA,
        ];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->statusFilter = $filter;
        $this->sortColumn = null;
        $this->sortDirection = 'desc';
        $this->resetPage();

        $builder = $this->listQueryBuilder();
        $total = $builder->sumFilteredTotal();
        $totalDevolvido = $builder->sumFilteredValorDevolvido();
        $totalLiquido = round($total - $totalDevolvido, 2);

        $this->js(sprintf(
            '(() => {
                const status = %s;
                const totalLabel = %s;
                const totalDevLabel = %s;
                const totalLiqLabel = %s;
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
                const el = document.querySelector(".erp-compras__total-value--compras");
                if (el) el.textContent = totalLabel;
                const elDev = document.querySelector(".erp-compras__total-value--devolvido");
                if (elDev) elDev.textContent = totalDevLabel;
                const elLiq = document.querySelector(".erp-compras__total-value--liquido");
                if (elLiq) elLiq.textContent = totalLiqLabel;
            })()',
            json_encode($filter, JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($total, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($totalDevolvido, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($totalLiquido, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode('app.filament.resources.compra-resource.pages.list-compras', JSON_UNESCAPED_UNICODE),
        ));
    }

    #[On('erp-compra-list-refresh')]
    public function refreshFromParent(
        string $statusFilter,
        string $searchColumn,
        string $localSearch,
        string $localSearchDe = '',
        string $localSearchAte = '',
        array $searchFieldsActive = [],
        array $localSearchByField = [],
        ?int $perPage = null,
        bool $resetSort = false,
    ): void {
        $this->statusFilter = $statusFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
        $this->localSearchDe = $localSearchDe;
        $this->localSearchAte = $localSearchAte;
        $this->searchFieldsActive = array_values(array_filter(
            $searchFieldsActive,
            fn (mixed $column): bool => is_string($column) && $column !== '',
        ));
        $this->localSearchByField = collect($localSearchByField)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && (is_string($value) || is_numeric($value)))
            ->map(fn (mixed $value): string => (string) $value)
            ->all();

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

    protected function listQueryBuilder(): CompraListQueryBuilder
    {
        return new CompraListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            localSearchDe: $this->localSearchDe,
            localSearchAte: $this->localSearchAte,
            searchFieldsActive: $this->searchFieldsActive,
            localSearchByField: $this->localSearchByField,
            applyDefaultOrder: false,
        );
    }

    protected function records(): LengthAwarePaginator
    {
        $query = $this->listQueryBuilder()->buildForList();

        $this->applySort($query);

        $paginator = $query->paginate($this->perPage);
        $ids = $paginator->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $valores = app(CompraDevolucaoIndicadorService::class)->valoresDevolvidosEmLote($ids);

        $paginator->setCollection(
            $paginator->getCollection()->map(function (Compra $compra) use ($valores): Compra {
                $valor = (float) ($valores[(int) $compra->id] ?? 0);
                $compra->setAttribute('valor_devolvido', $valor);
                $compra->setAttribute('tem_devolucao_finalizada', $valor > 0.00005);

                return $compra;
            }),
        );

        return $paginator;
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
