<?php

namespace App\Filament\Resources\RhEpiEntregaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhEpiEntregaResource;
use App\Filament\Resources\RhEpiEntregaResource\Pages\Concerns\ManagesRhEpiCatalogModal;
use App\Filament\Resources\RhEpiEntregaResource\Pages\Concerns\ManagesRhEpiEntregaFormModal;
use App\Filament\Resources\RhEpiEntregaResource\Pages\Concerns\ManagesRhEpiEntregaMovimentos;
use App\Models\RhEpiEntrega;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListRhEpiEntregas extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesRhEpiCatalogModal;
    use ManagesRhEpiEntregaFormModal;
    use ManagesRhEpiEntregaMovimentos;

    protected static string $resource = RhEpiEntregaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'funcionario';

    #[Url(as: 'status')]
    public string $statusFilter = 'abertas';

    #[Url(as: 'troca')]
    public string $trocaFilter = 'todos';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->trocaFilter = $this->normalizeTrocaFilter($this->trocaFilter);

        ErpScreen::set('RH — EPIs');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['funcionario', 'epi', 'ca', 'codigo'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'funcionario';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-epis-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma entrega de EPI';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-epis__search-text',
            'create' => 'createEntrega',
            'edit' => 'editEntrega',
            'delete' => 'cancelarEntrega',
            'extraKeys' => [
                'F4' => ['method' => 'imprimirEntregas', 'params' => []],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(RhEpiEntregaResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['abertas', 'devolvidas', 'substituidas', 'canceladas', 'todos'], true)
            ? (string) $value
            : 'abertas';
    }

    protected function normalizeTrocaFilter(mixed $value): string
    {
        return in_array($value, ['todos', 'proximas', 'vencidas'], true) ? (string) $value : 'todos';
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function setTrocaFilter(string $filter): void
    {
        $this->trocaFilter = $this->normalizeTrocaFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $query = match ($this->statusFilter) {
            'abertas' => $query->where('situacao', RhEpiEntrega::SIT_ENTREGUE)->where('ativo', true),
            'devolvidas' => $query->where('situacao', RhEpiEntrega::SIT_DEVOLVIDO),
            'substituidas' => $query->where('situacao', RhEpiEntrega::SIT_SUBSTITUIDO),
            'canceladas' => $query->where('situacao', RhEpiEntrega::SIT_CANCELADO),
            default => $query,
        };

        if ($this->trocaFilter === 'proximas') {
            $hoje = now()->toDateString();
            $limite = now()->addDays(30)->toDateString();
            $query->where('situacao', RhEpiEntrega::SIT_ENTREGUE)
                ->whereNotNull('previsao_troca')
                ->whereDate('previsao_troca', '>=', $hoje)
                ->whereDate('previsao_troca', '<=', $limite);
        } elseif ($this->trocaFilter === 'vencidas') {
            $query->where('situacao', RhEpiEntrega::SIT_ENTREGUE)
                ->whereNotNull('previsao_troca')
                ->whereDate('previsao_troca', '<', now()->toDateString());
        }

        if (filled($this->localSearch)) {
            $this->applyEntregaSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyEntregaSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');
        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->erpAllowedSearchColumns(), true)
            ? $this->searchColumn
            : 'funcionario';

        if ($column === 'codigo') {
            if (is_numeric($term)) {
                $query->where(function (Builder $q) use ($term): void {
                    $q->whereKey((int) $term)->orWhere('codigo', $term);
                });

                return;
            }
            $query->where('codigo', 'like', '%'.$term.'%');

            return;
        }

        if ($column === 'funcionario') {
            $like = '%'.$term.'%';
            $query->whereHas('funcionario', function (Builder $q) use ($like): void {
                $q->where('nome', 'like', $like)->orWhere('codigo', 'like', $like);
            });

            return;
        }

        if ($column === 'ca') {
            $query->whereHas('epi', fn (Builder $q) => $q->where('ca_numero', 'like', '%'.$term.'%'));

            return;
        }

        $query->whereHas('epi', function (Builder $q) use ($term): void {
            $q->where('descricao', 'like', '%'.$term.'%')
                ->orWhere('codigo', 'like', '%'.$term.'%');
        });
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.rh-epis.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.rh-epis.status-filters'),
                View::make('filament.components.erp.rh-epis.action-bar'),
                View::make('filament.components.erp.rh-epis.entrega-form-modal'),
                View::make('filament.components.erp.rh-epis.catalog-form-modal'),
                View::make('filament.components.erp.rh-epis.devolver-modal'),
                View::make('filament.components.erp.rh-epis.cancel-confirm-modal'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        if (filled($this->localSearch) && in_array($this->searchColumn, ['funcionario', 'epi'], true)) {
            $this->localSearch = mb_strtoupper(trim($this->localSearch), 'UTF-8');
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'funcionario';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function historicoFuncionarioSelecionado(): void
    {
        if (! ErpAccess::currentCan('rh.epis.access')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('open')) {
            return;
        }

        $record = RhEpiEntrega::query()->find($this->highlightedRecordId);
        if (! $record) {
            return;
        }

        $func = RhFuncionario::query()->find($record->funcionario_id);
        $this->localSearch = $func?->nome ?? '';
        $this->searchColumn = 'funcionario';
        $this->statusFilter = 'todos';
        $this->trocaFilter = 'todos';
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Histórico filtrado pelo funcionário.')->success()->send();
    }

    public function imprimirEntregas(): void
    {
        if (! ErpAccess::currentCan('rh.epis.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        $params = array_filter([
            'status' => $this->statusFilter !== 'abertas' ? $this->statusFilter : null,
            'troca' => $this->trocaFilter !== 'todos' ? $this->trocaFilter : null,
            'campo' => $this->searchColumn !== 'funcionario' ? $this->searchColumn : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'auto' => 1,
        ], fn ($v): bool => filled($v));

        $this->redirect(route('erp.reports.rh-epis-listagem', $params), navigate: false);
    }
}
