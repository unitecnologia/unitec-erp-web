<?php

namespace App\Livewire\Erp;

use App\Models\ContaReceber;
use App\Support\Erp\ContaReceberListRowFormatter;
use App\Support\Erp\ContaReceberPedidoExibicao;
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

    /** @var list<string> */
    public array $searchFieldsActive = [];

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public bool $skipLocalSearch = false;

    public int $perPage = 50;

    /** @var array<int, int|string> */
    public array $selecionadosParaBaixa = [];

    public ?string $sortColumn = null;

    public string $sortDirection = 'desc';

    public function setSituacaoFilter(string $filter): void
    {
        $allowed = ['todos', 'a_receber', 'atrasadas', 'recebidas'];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->situacaoFilter = $filter;
        $this->selecionadosParaBaixa = [];
        $this->resetPage();
        $this->patchParentAndTotals('situacaoFilter', $filter, 'situacao', 'todos');
    }

    public function setFormaFilter(string $filter): void
    {
        $allowed = [
            'todos',
            ContaReceber::FORMA_CARTEIRA,
            ContaReceber::FORMA_CHEQUE,
            ContaReceber::FORMA_CARTAO,
            ContaReceber::FORMA_BOLETO,
        ];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->formaFilter = $filter;
        $this->selecionadosParaBaixa = [];
        $this->resetPage();
        $this->patchParentAndTotals('formaFilter', $filter, 'forma', 'todos');
    }

    protected function patchParentAndTotals(string $parentProp, string $value, string $urlParam, string $default): void
    {
        $builder = new ContaReceberListQueryBuilder(
            situacaoFilter: $this->situacaoFilter,
            formaFilter: $this->formaFilter,
            clienteFilter: $this->clienteFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDe: $this->periodoDeApplied,
            periodoAte: $this->periodoAteApplied,
            skipLocalSearch: $this->skipLocalSearch,
            searchFieldsActive: $this->searchFieldsActive,
        );

        $totalAReceber = $builder->sumSaldoFiltered();
        $totalRecebido = $builder->sumValorRecebidoFiltered();
        $totalAtrasado = $builder->sumSaldoAtrasado();
        $contagens = $builder->contarPorSituacao();

        $this->js(sprintf(
            '(() => {
                const value = %s;
                const parentProp = %s;
                const urlParam = %s;
                const defaultValue = %s;
                const totalAReceber = %s;
                const totalRecebido = %s;
                const totalAtrasado = %s;
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) {
                    parent.set(parentProp, value, false);
                    parent.set("selecionadosParaBaixa", [], false);
                    try { parent.set("highlightedRecordId", null, false); } catch (e) {}
                }
                try {
                    const url = new URL(window.location.href);
                    if (value === defaultValue) {
                        url.searchParams.delete(urlParam);
                    } else {
                        url.searchParams.set(urlParam, value);
                    }
                    window.history.replaceState({}, "", url);
                } catch (e) {}
                const items = document.querySelectorAll(".erp-receber__totals .erp-receber__total-value");
                if (items[0]) items[0].textContent = totalAReceber;
                if (items[1]) items[1].textContent = totalRecebido;
                const late = document.querySelector(".erp-receber__total-value--late");
                if (late) late.textContent = totalAtrasado;
                const counts = %s;
                document.querySelectorAll(".erp-receber__filter-chip-count").forEach((el) => {
                    const key = el.getAttribute("data-situacao");
                    if (!key || counts[key] === undefined) return;
                    el.textContent = "(" + counts[key] + ")";
                });
                const selected = document.querySelector(".erp-receber__total-item--selected");
                if (selected) selected.remove();
            })()',
            json_encode($value, JSON_UNESCAPED_UNICODE),
            json_encode($parentProp, JSON_UNESCAPED_UNICODE),
            json_encode($urlParam, JSON_UNESCAPED_UNICODE),
            json_encode($default, JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($totalAReceber, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($totalRecebido, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode('R$ '.number_format($totalAtrasado, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode('app.filament.resources.conta-receber-resource.pages.list-contas-receber', JSON_UNESCAPED_UNICODE),
            json_encode($contagens, JSON_UNESCAPED_UNICODE),
        ));
    }

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
        array $searchFieldsActive = [],
    ): void {
        $this->situacaoFilter = $situacaoFilter;
        $this->formaFilter = $formaFilter;
        $this->clienteFilter = $clienteFilter;
        $this->searchColumn = $searchColumn;
        $this->localSearch = $localSearch;
        $this->searchFieldsActive = $searchFieldsActive;
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

    #[On('erp-receber-selection-sync')]
    public function syncSelectionFromParent(array $selecionadosParaBaixa = []): void
    {
        $this->selecionadosParaBaixa = $selecionadosParaBaixa;
        $this->skipRender();
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
        $records = $this->records();
        $formatter = app(ContaReceberListRowFormatter::class);
        $formatter->pedidosMonitor = ContaReceberPedidoExibicao::mapa($records);

        return view('livewire.erp.conta-receber-list-table', [
            'records' => $records,
            'formatter' => $formatter,
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
            searchFieldsActive: $this->searchFieldsActive,
        ))->buildForList();

        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    protected function applySort(Builder $query): void
    {
        if ($this->sortColumn === null) {
            $query->orderBy('vencimento')->orderBy('id');

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
