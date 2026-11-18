<?php

namespace App\Filament\Resources\RhFeriasResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhFeriasResource;
use App\Filament\Resources\RhFeriasResource\Pages\Concerns\ManagesRhFeriasModals;
use App\Models\RhFeriasGozo;
use App\Models\RhFeriasPeriodo;
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

class ListRhFerias extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesRhFeriasModals;

    protected static string $resource = RhFeriasResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'funcionario';

    #[Url(as: 'status')]
    public string $statusFilter = 'programadas';

    #[Url(as: 'alerta')]
    public int $alertaDias = 30;

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->alertaDias = $this->normalizeAlertaDias($this->alertaDias);

        ErpScreen::set('RH — Férias');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['funcionario', 'codigo'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'funcionario';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-ferias-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um gozo de férias';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-ferias__search-text',
            'create' => 'createFeriasGozo',
            'edit' => 'editFeriasGozo',
            'delete' => 'cancelarFeriasGozo',
            'extraKeys' => [
                'F4' => ['method' => 'imprimirFerias', 'params' => []],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(RhFeriasResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['programadas', 'em_andamento', 'concluidas', 'pendentes', 'todos'], true)
            ? (string) $value
            : 'programadas';
    }

    protected function normalizeAlertaDias(mixed $value): int
    {
        $dias = (int) $value;

        return in_array($dias, [7, 15, 30], true) ? $dias : 30;
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function setAlertaDias(int $dias): void
    {
        $this->alertaDias = $this->normalizeAlertaDias($dias);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $hoje = now()->toDateString();
        $limiteConcessivo = now()->addDays(60)->toDateString();

        $query = match ($this->statusFilter) {
            'programadas' => $query->where('ativo', true)
                ->where('situacao', RhFeriasGozo::SIT_PROGRAMADA),
            'em_andamento' => $query->where('ativo', true)
                ->where('situacao', RhFeriasGozo::SIT_EM_ANDAMENTO),
            'concluidas' => $query->where('situacao', RhFeriasGozo::SIT_CONCLUIDA),
            'pendentes' => $query->where('ativo', true)
                ->where('situacao', '!=', RhFeriasGozo::SIT_CANCELADA)
                ->whereHas('periodo', function (Builder $q) use ($hoje, $limiteConcessivo): void {
                    $q->where('dias_saldo', '>', 0)
                        ->whereIn('situacao', [
                            RhFeriasPeriodo::SIT_ABERTO,
                            RhFeriasPeriodo::SIT_PARCIAL,
                            RhFeriasPeriodo::SIT_VENCIDO,
                        ])
                        ->whereDate('concessivo_fim', '>=', $hoje)
                        ->whereDate('concessivo_fim', '<=', $limiteConcessivo);
                }),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $this->applyFeriasSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyFeriasSearch(Builder $query, string $term): void
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

        $like = '%'.$term.'%';
        $query->whereHas('funcionario', function (Builder $q) use ($like): void {
            $q->where('nome', 'like', $like)->orWhere('codigo', 'like', $like);
        });
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.rh-ferias.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.rh-ferias.status-filters'),
                View::make('filament.components.erp.rh-ferias.action-bar'),
                View::make('filament.components.erp.rh-ferias.form-modal'),
                View::make('filament.components.erp.rh-ferias.periodo-modal'),
                View::make('filament.components.erp.rh-ferias.cancel-modal'),
                View::make('filament.components.erp.rh-ferias.saldo-modal'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        if (filled($this->localSearch) && $this->searchColumn === 'funcionario') {
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

    public function imprimirFerias(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        $params = array_filter([
            'status' => $this->statusFilter !== 'programadas' ? $this->statusFilter : null,
            'alerta' => $this->alertaDias !== 30 ? $this->alertaDias : null,
            'campo' => $this->searchColumn !== 'funcionario' ? $this->searchColumn : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'auto' => 1,
        ], fn ($v): bool => filled($v));

        $this->redirect(route('erp.reports.rh-ferias-listagem', $params), navigate: false);
    }
}
