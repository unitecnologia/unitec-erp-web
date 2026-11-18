<?php

namespace App\Filament\Resources\RhEscalaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhEscalaResource;
use App\Filament\Resources\RhEscalaResource\Pages\Concerns\ManagesRhEscalaAjustes;
use App\Filament\Resources\RhEscalaResource\Pages\Concerns\ManagesRhEscalaCalendarioModal;
use App\Filament\Resources\RhEscalaResource\Pages\Concerns\ManagesRhEscalaCatalogModal;
use App\Filament\Resources\RhEscalaResource\Pages\Concerns\ManagesRhEscalaVinculoFormModal;
use App\Models\RhEscalaVinculo;
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

class ListRhEscalas extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesRhEscalaAjustes;
    use ManagesRhEscalaCalendarioModal;
    use ManagesRhEscalaCatalogModal;
    use ManagesRhEscalaVinculoFormModal;

    protected static string $resource = RhEscalaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'funcionario';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->mountCalendarioDefaults();

        ErpScreen::set('RH — Escalas');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['funcionario', 'escala', 'codigo'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'funcionario';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-escalas-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um vínculo de escala';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-escalas__search-text',
            'create' => 'createVinculo',
            'edit' => 'editVinculo',
            'delete' => 'encerrarVinculoSelecionado',
            'extraKeys' => [
                'F4' => ['method' => 'imprimirVinculos', 'params' => []],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(RhEscalaResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'encerrados', 'todos'], true)
            ? (string) $value
            : 'ativos';
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
            'ativos' => $query->where('ativo', true),
            'encerrados' => $query->where('ativo', false),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applyVinculoSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyVinculoSearch(Builder $query, string $term): void
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

        if ($column === 'escala') {
            $like = '%'.$term.'%';
            $query->whereHas('escala', function (Builder $q) use ($like): void {
                $q->where('nome', 'like', $like)->orWhere('codigo', 'like', $like);
            });

            return;
        }

        $like = '%'.$term.'%';
        $query->whereHas('funcionario', function (Builder $q) use ($like): void {
            $q->where('nome', 'like', $like)->orWhere('codigo', 'like', $like);
        });
    }

    public function imprimirVinculos(): void
    {
        if (! ErpAccess::currentCan('rh.escalas.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        $params = array_filter([
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'campo' => $this->searchColumn,
            'status' => $this->statusFilter,
            'auto' => 0,
        ]);

        $this->redirect(route('erp.reports.rh-escalas-listagem', $params), navigate: false);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->schema([
                View::make('filament.components.erp.rh-escalas.screen'),
                EmbeddedTable::make(),
                View::make('filament.components.erp.rh-escalas.status-filters'),
                View::make('filament.components.erp.rh-escalas.action-bar'),
                View::make('filament.components.erp.rh-escalas.vinculo-form-modal'),
                View::make('filament.components.erp.rh-escalas.catalog-form-modal'),
                View::make('filament.components.erp.rh-escalas.ajuste-form-modal'),
                View::make('filament.components.erp.rh-escalas.calendario-modal'),
            ]);
    }
}
