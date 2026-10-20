<?php

namespace App\Filament\Resources\CargaResource\Pages;

use App\Filament\Concerns\InteractsWithErpDualSearchFields;
use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\CargaResource;
use App\Filament\Resources\CargaResource\Pages\Concerns\ManagesCargaFormModal;
use App\Livewire\Erp\CargaListTable;
use App\Models\Carga;
use App\Support\Erp\Carga\CargaListPreferences;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

class ListCargas extends ListRecords
{
    use InteractsWithErpDualSearchFields;
    use InteractsWithErpListPage;
    use ManagesCargaFormModal;

    protected static string $resource = CargaResource::class;

    protected static ?string $title = '';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'todos';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'numero';

    public string $sortColumn = 'numero';

    public string $sortDirection = 'desc';

    /** @var array<int, string> */
    public array $selecionados = [];

    /** Sessão temporária: só restaura seleção ao voltar de F4/F6. */
    public const SESSION_SELECAO_RETORNO_IMPRESSAO = 'erp_cargas_selecionados_retorno_impressao';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Carga / Romaneio');

        // Compat: ?numero= antigo vira pesquisa local.
        if ($this->localSearch === '' && request()->filled('numero')) {
            $this->localSearch = trim((string) request()->query('numero'));
        }

        $this->restoreListPreferencesOnMount();
        $this->restoreSelecionadosDoRetornoImpressao();
    }

    /**
     * A grade é renderizada pelo Livewire filho {@see CargaListTable}.
     */
    public function mountInteractsWithTable(): void
    {
    }

    protected function restoreSelecionadosDoRetornoImpressao(): void
    {
        $pending = session()->pull(self::SESSION_SELECAO_RETORNO_IMPRESSAO);

        if (! is_array($pending) || $pending === []) {
            return;
        }

        $this->selecionados = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => (string) (int) $id,
            $pending,
        ))));
    }

    /**
     * Restore user/empresa prefs (1 SELECT). URL status/campo/campos têm prioridade.
     */
    protected function restoreListPreferencesOnMount(): void
    {
        $prefs = CargaListPreferences::load();
        $hoje = ErpTimezone::toLocal();
        $inicioMes = $hoje->copy()->startOfMonth()->format('Y-m-d');
        $hojeStr = $hoje->format('Y-m-d');

        $allowed = CargaListPreferences::SEARCH_COLUMNS;

        if ($prefs !== null) {
            if (! request()->has('perPage') && ! request()->has('tableRecordsPerPage')) {
                $this->tableRecordsPerPage = $prefs['per_page'];
            }

            $this->sortColumn = $prefs['sort_column'];
            $this->sortDirection = $prefs['sort_direction'];

            if ($this->periodoDe === '' && $prefs['periodo_de'] !== null) {
                $this->periodoDe = $prefs['periodo_de'];
            }

            if ($this->periodoAte === '' && $prefs['periodo_ate'] !== null) {
                $this->periodoAte = $prefs['periodo_ate'];
            }

            if (! request()->has('status')) {
                $this->statusFilter = $prefs['status'];
            }
        }

        if ($this->searchFieldsQuery !== '' || request()->has('campo')) {
            $this->erpRestoreDualSearchFields($allowed, 'erp_cargas_search_fields');
        } elseif ($prefs !== null) {
            $this->erpApplyDualSearchFields($prefs['search_fields'], 'erp_cargas_search_fields', false);
        } else {
            $this->erpApplyDualSearchFields(['numero'], 'erp_cargas_search_fields', false);
        }

        if (! in_array((int) $this->tableRecordsPerPage, [25, 50, 100], true)) {
            $this->tableRecordsPerPage = 50;
        }

        if ($this->periodoDe === '') {
            $this->periodoDe = $inicioMes;
        }

        if ($this->periodoAte === '') {
            $this->periodoAte = $hojeStr;
        }

        if ($this->periodoDeApplied === '') {
            $this->periodoDeApplied = $this->periodoDe;
        }

        if ($this->periodoAteApplied === '') {
            $this->periodoAteApplied = $this->periodoAte;
        }

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->searchColumn = in_array($this->searchColumn, $allowed, true) ? $this->searchColumn : 'numero';

        if (! in_array($this->sortColumn, CargaListPreferences::SORT_COLUMNS, true)) {
            $this->sortColumn = 'numero';
        }

        $this->sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';
    }

    protected function persistListPreferences(): void
    {
        $fields = $this->searchFieldsActive !== [] ? $this->searchFieldsActive : [$this->searchColumn];

        CargaListPreferences::save([
            'periodo_de' => $this->periodoDeApplied !== '' ? $this->periodoDeApplied : $this->periodoDe,
            'periodo_ate' => $this->periodoAteApplied !== '' ? $this->periodoAteApplied : $this->periodoAte,
            'status' => $this->statusFilter,
            'per_page' => (int) ($this->tableRecordsPerPage ?: 50),
            'sort_column' => $this->sortColumn,
            'sort_direction' => $this->sortDirection,
            'search_fields' => $fields,
        ]);
    }

    public function setSearchColumn(string $column): void
    {
        $normalized = in_array($column, CargaListPreferences::SEARCH_COLUMNS, true) ? $column : 'numero';

        if (! $this->erpToggleDualSearchField($normalized, CargaListPreferences::SEARCH_COLUMNS, 'erp_cargas_search_fields')) {
            return;
        }

        $this->persistListPreferences();
        $this->clearListSelection();
        $this->pushCargaListRefresh(skipParentRender: false);
    }

    public function persistSortPreference(string $column, string $direction): void
    {
        $column = in_array($column, CargaListPreferences::SORT_COLUMNS, true) ? $column : 'numero';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        if ($this->sortColumn === $column && $this->sortDirection === $direction) {
            $this->skipRender();

            return;
        }

        $this->sortColumn = $column;
        $this->sortDirection = $direction;
        $this->persistListPreferences();
        $this->skipRender();
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-cargas-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma carga';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-cargas__search-text',
            'searchFocusKey' => false,
            'edit' => 'editCarga',
            'extraKeys' => [
                'F2' => ['method' => 'createCarga'],
                'F3' => ['method' => 'editCarga'],
                'F4' => ['method' => 'imprimirRomaneioSelecionado'],
                'F5' => ['method' => 'refreshTable'],
                'F6' => ['method' => 'imprimirPedidosSelecionados'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(CargaResource::table($table));
    }

    /**
     * @return array<int, string>
     */
    protected function erpListRecordClasses(Model $record): array
    {
        if (in_array((string) (int) $record->getKey(), $this->selecionados, true)) {
            return ['erp-cargas-row--checked'];
        }

        return [];
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

        $this->skipRender();
    }

    protected function clearListSelection(): void
    {
        $this->highlightedRecordId = null;
        $this->selecionados = [];
        session()->forget(self::SESSION_SELECAO_RETORNO_IMPRESSAO);
    }

    public function refreshTable(): void
    {
        $this->clearListSelection();
        $this->pushCargaListRefresh(skipParentRender: true);

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    public function resetTable(): void
    {
        $this->pushCargaListRefresh(skipParentRender: false);
    }

    public function applyPeriodFilterAuto(): void
    {
        if ($this->periodoDe === $this->periodoDeApplied && $this->periodoAte === $this->periodoAteApplied) {
            $this->skipRender();

            return;
        }

        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->persistListPreferences();
        $this->clearListSelection();
        $this->pushCargaListRefresh(skipParentRender: false);
    }

    public function updatedStatusFilter(): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->persistListPreferences();
        $this->clearListSelection();
        $this->pushCargaListRefresh(skipParentRender: false);
    }

    public function updatedLocalSearch(): void
    {
        $this->clearListSelection();
        $this->pushCargaListRefresh(skipParentRender: true);
    }

    public function updatedTableRecordsPerPage(): void
    {
        if (! in_array((int) $this->tableRecordsPerPage, [25, 50, 100], true)) {
            $this->tableRecordsPerPage = 50;
        }

        $this->persistListPreferences();
        $this->clearListSelection();
        $this->pushCargaListRefresh(skipParentRender: false);
    }

    /**
     * @return list<string>
     */
    public function activeSearchFields(): array
    {
        return $this->searchFieldsActive !== []
            ? $this->searchFieldsActive
            : [$this->searchColumn ?: 'numero'];
    }

    protected function pushCargaListRefresh(
        bool $resetSort = false,
        bool $skipParentRender = true,
        bool $resetPage = true,
    ): void {
        $this->dispatch(
            'erp-carga-list-refresh',
            statusFilter: $this->statusFilter,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
            localSearch: $this->localSearch,
            searchFieldsActive: $this->activeSearchFields(),
            perPage: (int) ($this->tableRecordsPerPage ?: 50),
            resetSort: $resetSort,
            sortColumn: $this->sortColumn,
            sortDirection: $this->sortDirection,
            selecionados: $this->selecionados,
            highlightedRecordId: $this->highlightedRecordId,
            resetPage: $resetPage,
        )->to(CargaListTable::class);

        if ($skipParentRender) {
            $this->skipRender();
        }
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    /**
     * @return Builder<Carga>
     */
    protected function buildListQuery(): Builder
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $table = (new Carga)->getTable();

        $query = Carga::query()
            ->with(['motorista:id,proprietario,apelido', 'entregador:id,name', 'veiculo:id,placa,descricao'])
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

        return $query;
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        $allowed = ['todos', Carga::STATUS_ABERTA, Carga::STATUS_FECHADA, Carga::STATUS_CANCELADA];

        return in_array($value, $allowed, true) ? (string) $value : 'todos';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.cargas.screen'),
                View::make('filament.components.erp.cargas.table-host')->columnSpanFull(),
                View::make('filament.components.erp.cargas.action-bar'),
                View::make('filament.components.erp.cargas.form-modal'),
            ]);
    }
}
