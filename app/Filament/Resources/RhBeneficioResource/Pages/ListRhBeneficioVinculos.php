<?php

namespace App\Filament\Resources\RhBeneficioResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\RhBeneficioResource;
use App\Filament\Resources\RhBeneficioResource\Pages\Concerns\ManagesRhBeneficioCatalogModal;
use App\Filament\Resources\RhBeneficioResource\Pages\Concerns\ManagesRhBeneficioVinculoFormModal;
use App\Filament\Resources\RhBeneficioResource\Pages\Concerns\ManagesRhBeneficioVinculoMovimentos;
use App\Models\RhBeneficioVinculo;
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

class ListRhBeneficioVinculos extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;
    use ManagesRhBeneficioCatalogModal;
    use ManagesRhBeneficioVinculoFormModal;
    use ManagesRhBeneficioVinculoMovimentos;

    protected static string $resource = RhBeneficioResource::class;

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

        ErpScreen::set('RH — Benefícios');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['funcionario', 'beneficio'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'funcionario';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-rh-beneficios-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um vínculo de benefício';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-beneficios__search-text',
            'create' => 'createVinculo',
            'edit' => 'editVinculo',
            'delete' => 'cancelarVinculo',
            'extraKeys' => [
                'F4' => ['method' => 'imprimirBeneficios', 'params' => []],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(RhBeneficioResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'cancelados', 'todos'], true)
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
            'ativos' => $query->where('situacao', RhBeneficioVinculo::SIT_ATIVO)->where('ativo', true),
            'cancelados' => $query->where('situacao', RhBeneficioVinculo::SIT_CANCELADO),
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

        if ($column === 'beneficio') {
            $query->whereHas('beneficio', function (Builder $q) use ($term): void {
                $q->where('descricao', 'like', '%'.$term.'%')
                    ->orWhere('codigo', 'like', '%'.$term.'%');
            });

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
                View::make('filament.components.erp.rh-beneficios.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.rh-beneficios.filters'),
                View::make('filament.components.erp.rh-beneficios.action-bar'),
                View::make('filament.components.erp.rh-beneficios.form-modal'),
                View::make('filament.components.erp.rh-beneficios.catalog-modal'),
                View::make('filament.components.erp.rh-beneficios.confirm-modal'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        if (filled($this->localSearch) && in_array($this->searchColumn, ['funcionario', 'beneficio'], true)) {
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

    public function imprimirBeneficios(): void
    {
        if (! ErpAccess::currentCan('rh.beneficios.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        $params = array_filter([
            'status' => $this->statusFilter !== 'ativos' ? $this->statusFilter : null,
            'campo' => $this->searchColumn !== 'funcionario' ? $this->searchColumn : null,
            'q' => filled($this->localSearch) ? $this->localSearch : null,
            'auto' => 1,
        ], fn ($v): bool => filled($v));

        $this->redirect(route('erp.reports.rh-beneficios-listagem', $params), navigate: false);
    }
}
