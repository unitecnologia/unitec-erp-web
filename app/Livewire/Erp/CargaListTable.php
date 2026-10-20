<?php

namespace App\Livewire\Erp;

use App\Filament\Resources\CargaResource;
use App\Models\Carga;
use App\Models\Transportadora;
use App\Models\User;
use App\Models\Veiculo;
use App\Support\Erp\Carga\CargaListPreferences;
use App\Support\Erp\Carga\CargaListRowFormatter;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpSearchFieldSelection;
use App\Support\Erp\ErpTableSort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class CargaListTable extends Component
{
    use WithPagination;

    private const PARENT_NAME = 'app.filament.resources.carga-resource.pages.list-cargas';

    public string $statusFilter = 'todos';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public string $localSearch = '';

    /** @var list<string> */
    public array $searchFieldsActive = ['numero'];

    public int $perPage = 50;

    public ?string $sortColumn = 'numero';

    public string $sortDirection = 'desc';

    /** @var list<string> */
    public array $selecionados = [];

    public ?int $highlightedRecordId = null;

    /**
     * @param  list<string>  $searchFieldsActive
     * @param  list<string>  $selecionados
     */
    #[On('erp-carga-list-refresh')]
    public function refreshFromParent(
        string $statusFilter,
        string $periodoDeApplied,
        string $periodoAteApplied,
        string $localSearch = '',
        array $searchFieldsActive = ['numero'],
        ?int $perPage = null,
        bool $resetSort = false,
        ?string $sortColumn = null,
        ?string $sortDirection = null,
        array $selecionados = [],
        ?int $highlightedRecordId = null,
        bool $resetPage = true,
    ): void {
        $this->statusFilter = $this->normalizeStatusFilter($statusFilter);
        $this->periodoDeApplied = $periodoDeApplied;
        $this->periodoAteApplied = $periodoAteApplied;
        $this->localSearch = $localSearch;
        $this->searchFieldsActive = ErpSearchFieldSelection::normalize(
            $searchFieldsActive,
            CargaListPreferences::SEARCH_COLUMNS,
            'numero',
        );
        $this->selecionados = array_values(array_unique(array_map(
            static fn (mixed $id): string => (string) (int) $id,
            $selecionados,
        )));
        $this->highlightedRecordId = $highlightedRecordId;

        if ($perPage !== null && $perPage > 0) {
            $this->perPage = $perPage;
        }

        if ($resetSort) {
            $this->sortColumn = 'numero';
            $this->sortDirection = 'desc';
        } elseif ($sortColumn !== null && in_array($sortColumn, CargaListPreferences::SORT_COLUMNS, true)) {
            $this->sortColumn = $sortColumn;
            $this->sortDirection = $sortDirection === 'asc' ? 'asc' : 'desc';
        }

        if ($resetPage) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, CargaListPreferences::SORT_COLUMNS, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = in_array($column, ['numero', 'data', 'pedidos_count', 'valor_total'], true)
                ? 'desc'
                : 'asc';
        }

        $this->js(sprintf(
            '(() => {
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) parent.call("persistSortPreference", %s, %s);
            })()',
            json_encode(self::PARENT_NAME, JSON_UNESCAPED_UNICODE),
            json_encode($this->sortColumn, JSON_UNESCAPED_UNICODE),
            json_encode($this->sortDirection, JSON_UNESCAPED_UNICODE),
        ));

        $this->resetPage();
    }

    public function syncSelecionado(int $id, bool $checked): void
    {
        $key = (string) (int) $id;
        $this->selecionados = array_values(array_unique(array_map(
            static fn (mixed $item): string => (string) (int) $item,
            $this->selecionados,
        )));

        $has = in_array($key, $this->selecionados, true);

        if ($checked && ! $has) {
            $this->selecionados[] = $key;
        } elseif (! $checked && $has) {
            $this->selecionados = array_values(array_filter(
                $this->selecionados,
                static fn (string $item): bool => $item !== $key,
            ));
        }

        $this->js(sprintf(
            '(() => {
                const parents = window.Livewire?.getByName?.(%s) || [];
                const parent = parents[0] || null;
                if (parent) parent.call("syncSelecionado", %d, %s);
            })()',
            json_encode(self::PARENT_NAME, JSON_UNESCAPED_UNICODE),
            (int) $id,
            $checked ? 'true' : 'false',
        ));

        $this->skipRender();
    }

    public function render(): View
    {
        return view('livewire.erp.carga-list-table', [
            'records' => $this->records(),
            'formatter' => app(CargaListRowFormatter::class),
        ]);
    }

    protected function records(): LengthAwarePaginator
    {
        $query = $this->buildListQuery();
        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    /**
     * @return Builder<Carga>
     */
    protected function buildListQuery(): Builder
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $table = (new Carga)->getTable();

        $query = Carga::query()
            ->select([
                "{$table}.id",
                "{$table}.empresa_id",
                "{$table}.numero",
                "{$table}.data",
                "{$table}.motorista_id",
                "{$table}.entregador_user_id",
                "{$table}.veiculo_id",
                "{$table}.status",
            ])
            ->with([
                'motorista:id,proprietario,apelido',
                'entregador:id,name',
                'veiculo:id,placa,descricao',
            ])
            ->withCount('pedidos')
            ->withSum('pedidos', 'total');

        if ($empresaId > 0) {
            $query->where("{$table}.empresa_id", $empresaId);
        }

        if ($this->statusFilter !== 'todos') {
            $query->where("{$table}.status", $this->statusFilter);
        }

        if (filled($this->periodoDeApplied)) {
            $query->whereDate("{$table}.data", '>=', $this->periodoDeApplied);
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereDate("{$table}.data", '<=', $this->periodoAteApplied);
        }

        $this->applyLocalSearch($query);

        return $query;
    }

    /**
     * @param  Builder<Carga>  $query
     */
    protected function applyLocalSearch(Builder $query): void
    {
        $term = trim($this->localSearch);

        if ($term === '') {
            return;
        }

        $columns = ErpSearchFieldSelection::normalize(
            $this->searchFieldsActive,
            CargaListPreferences::SEARCH_COLUMNS,
            'numero',
        );

        ErpSearchFieldSelection::applyOr(
            $query,
            $columns,
            function (Builder $inner, string $column) use ($term): void {
                $this->applySearchOnColumn($inner, $column, $term);
            },
        );
    }

    /**
     * @param  Builder<Carga>  $query
     */
    protected function applySearchOnColumn(Builder $query, string $column, string $term): void
    {
        $table = $query->getModel()->getTable();
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        match ($column) {
            'numero' => $query->where("{$table}.numero", 'like', '%'.(ltrim($term, '0') ?: '0').'%'),
            'data' => $this->applyDataSearch($query, $table, $term),
            'motorista' => $query->whereHas(
                'motorista',
                fn (Builder $q): Builder => $q->where('proprietario', 'like', $like)
                    ->orWhere('apelido', 'like', $like),
            ),
            'entregador' => $query->whereHas(
                'entregador',
                fn (Builder $q): Builder => $q->where('name', 'like', $like),
            ),
            'veiculo' => $query->whereHas(
                'veiculo',
                fn (Builder $q): Builder => $q->where('placa', 'like', $like)
                    ->orWhere('descricao', 'like', $like),
            ),
            'pedidos_count' => $this->applyPedidosCountSearch($query, $term),
            'valor_total' => $this->applyValorTotalSearch($query, $term),
            'status' => $this->applyStatusSearch($query, $table, $term),
            default => $query->where("{$table}.numero", 'like', $like),
        };
    }

    /**
     * @param  Builder<Carga>  $query
     */
    protected function applyDataSearch(Builder $query, string $table, string $term): void
    {
        $normalized = trim($term);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
            $query->whereDate("{$table}.data", $normalized);

            return;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $normalized, $m)) {
            $year = strlen($m[3]) === 2 ? (int) ('20'.$m[3]) : (int) $m[3];
            $date = sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1]);

            try {
                Carbon::parse($date);
                $query->whereDate("{$table}.data", $date);

                return;
            } catch (\Throwable) {
                // fall through
            }
        }

        // Parcial: dia/mês (ex.: 19/10) ou trecho ISO.
        $query->where("{$table}.data", 'like', '%'.str_replace('/', '-', $normalized).'%');
    }

    /**
     * @param  Builder<Carga>  $query
     */
    protected function applyPedidosCountSearch(Builder $query, string $term): void
    {
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        if ($digits === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->has('pedidos', '=', (int) $digits);
    }

    /**
     * @param  Builder<Carga>  $query
     */
    protected function applyValorTotalSearch(Builder $query, string $term): void
    {
        $valor = ErpMoney::tryParseBr($term);

        if ($valor === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $prefix = $query->getConnection()->getTablePrefix();
        $pedidosTable = $prefix.(new Carga)->pedidos()->getRelated()->getTable();
        $pivot = $prefix.(new Carga)->pedidos()->getTable();
        $cargaTable = $prefix.$query->getModel()->getTable();

        $query->whereRaw(
            '(select coalesce(sum(`'.$pedidosTable.'`.`total`), 0) from `'.$pedidosTable.'` '
            .'inner join `'.$pivot.'` on `'.$pedidosTable.'`.`id` = `'.$pivot.'`.`pedido_id` '
            .'where `'.$cargaTable.'`.`id` = `'.$pivot.'`.`carga_id`) = ?',
            [$valor],
        );
    }

    /**
     * @param  Builder<Carga>  $query
     */
    protected function applyStatusSearch(Builder $query, string $table, string $term): void
    {
        $normalized = mb_strtolower(trim($term), 'UTF-8');
        $keys = [];

        foreach (Carga::statusLabels() as $key => $label) {
            if (
                $normalized === $key
                || $normalized === mb_strtolower($label, 'UTF-8')
                || str_contains(mb_strtolower($label, 'UTF-8'), $normalized)
                || str_contains($key, $normalized)
            ) {
                $keys[] = $key;
            }
        }

        if ($keys === []) {
            $query->where("{$table}.status", 'like', '%'.$term.'%');

            return;
        }

        $query->whereIn("{$table}.status", array_values(array_unique($keys)));
    }

    /**
     * @param  Builder<Carga>  $query
     */
    protected function applySort(Builder $query): void
    {
        $dir = $this->sortDirection === 'asc' ? 'asc' : 'desc';
        $column = $this->sortColumn ?: 'numero';

        match ($column) {
            'numero' => ErpTableSort::orderByCodigoNumerico($query, $dir, 'numero', desempateIdDesc: true),
            'data' => CargaResource::orderByColumn($query, 'data', $dir),
            'motorista' => CargaResource::orderByRelationColumn(
                $query,
                Transportadora::query()->getModel()->getTable(),
                'proprietario',
                'motorista_id',
                $dir,
            ),
            'entregador' => CargaResource::orderByRelationColumn(
                $query,
                (new User)->getTable(),
                'name',
                'entregador_user_id',
                $dir,
            ),
            'veiculo' => CargaResource::orderByRelationColumn(
                $query,
                (new Veiculo)->getTable(),
                'placa',
                'veiculo_id',
                $dir,
            ),
            'pedidos_count' => CargaResource::orderByColumn($query, 'pedidos_count', $dir),
            'valor_total' => CargaResource::orderByColumn($query, 'pedidos_sum_total', $dir),
            'status' => CargaResource::orderByStatus($query, $dir),
            default => ErpTableSort::orderByCodigoNumerico($query, 'desc', 'numero', desempateIdDesc: true),
        };
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        $allowed = ['todos', Carga::STATUS_ABERTA, Carga::STATUS_FECHADA, Carga::STATUS_CANCELADA];

        return in_array($value, $allowed, true) ? (string) $value : 'todos';
    }
}
