<?php

namespace App\Filament\Resources\RhUniformeEntregaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhUniformeEntregaResource;
use App\Filament\Resources\RhUniformeEntregaResource\Pages\Concerns\ManagesRhUniformeCatalogModal;
use App\Filament\Resources\RhUniformeEntregaResource\Pages\Concerns\ManagesRhUniformeEntregaFormModal;
use App\Filament\Resources\RhUniformeEntregaResource\Pages\Concerns\ManagesRhUniformeEntregaMovimentos;
use App\Models\RhFuncionario;
use App\Models\RhUniformeEntrega;
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

class ListRhUniformeEntregas extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesRhUniformeCatalogModal;
    use ManagesRhUniformeEntregaFormModal;
    use ManagesRhUniformeEntregaMovimentos;

    protected static string $resource = RhUniformeEntregaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'funcionario';

    #[Url(as: 'status')]
    public string $statusFilter = 'abertas';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);

        ErpScreen::set('RH — Uniformes');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['funcionario', 'uniforme', 'codigo', 'tamanho'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'funcionario';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-uniformes-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma entrega de uniforme';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-uniformes__search-text',
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
        return $this->applyErpListSelection(RhUniformeEntregaResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['abertas', 'devolvidas', 'trocadas', 'substituidas', 'canceladas', 'todos'], true)
            ? (string) $value
            : 'abertas';
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $query = match ($this->statusFilter) {
            'abertas' => $query->where('situacao', RhUniformeEntrega::SIT_ENTREGUE)->where('ativo', true),
            'devolvidas' => $query->where('situacao', RhUniformeEntrega::SIT_DEVOLVIDO),
            'trocadas' => $query->where('situacao', RhUniformeEntrega::SIT_TROCADO),
            'substituidas' => $query->where('situacao', RhUniformeEntrega::SIT_SUBSTITUIDO),
            'canceladas' => $query->where('situacao', RhUniformeEntrega::SIT_CANCELADO),
            default => $query,
        };

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

        if ($column === 'tamanho') {
            $query->whereHas('uniforme', fn (Builder $q) => $q->where('tamanho', 'like', '%'.$term.'%'));

            return;
        }

        $query->whereHas('uniforme', function (Builder $q) use ($term): void {
            $q->where('descricao', 'like', '%'.$term.'%')
                ->orWhere('codigo', 'like', '%'.$term.'%')
                ->orWhere('tipo', 'like', '%'.$term.'%')
                ->orWhere('cor', 'like', '%'.$term.'%');
        });
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.rh-uniformes.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.rh-uniformes.status-filters'),
                View::make('filament.components.erp.rh-uniformes.action-bar'),
                View::make('filament.components.erp.rh-uniformes.entrega-form-modal'),
                View::make('filament.components.erp.rh-uniformes.catalog-form-modal'),
                View::make('filament.components.erp.rh-uniformes.devolver-modal'),
                View::make('filament.components.erp.rh-uniformes.cancel-confirm-modal'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        if (filled($this->localSearch) && in_array($this->searchColumn, ['funcionario', 'uniforme', 'tamanho'], true)) {
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
        if (! ErpAccess::currentCan('rh.uniformes.access')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('open')) {
            return;
        }

        $record = RhUniformeEntrega::query()->find($this->highlightedRecordId);
        if (! $record) {
            return;
        }

        $func = RhFuncionario::query()->find($record->funcionario_id);
        $this->localSearch = $func?->nome ?? '';
        $this->searchColumn = 'funcionario';
        $this->statusFilter = 'todos';
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Histórico filtrado pelo funcionário.')->success()->send();
    }

    public function imprimirEntregas(): void
    {
        if (! ErpAccess::currentCan('rh.uniformes.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        $params = array_filter([
            'status' => $this->statusFilter !== 'abertas' ? $this->statusFilter : null,
            'campo' => $this->searchColumn !== 'funcionario' ? $this->searchColumn : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'auto' => 1,
        ], fn ($v): bool => filled($v));

        $this->redirect(route('erp.reports.rh-uniformes-listagem', $params), navigate: false);
    }
}
