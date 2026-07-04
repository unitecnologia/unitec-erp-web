<?php

namespace App\Filament\Resources\AjusteEstoqueResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\AjusteEstoqueResource;
use App\Filament\Resources\AjusteEstoqueResource\Pages\Concerns\ManagesAjusteEstoqueForm;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Queries\AjusteEstoqueListQueryBuilder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListAjustesEstoque extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use ManagesAjusteEstoqueForm;

    protected static string $resource = AjusteEstoqueResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'produto';

    public bool $informarPeriodo = true;

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Lista de Ajustes de Estoque');

        if ($this->periodoDe === '') {
            $this->periodoDe = now()->startOfMonth()->format('Y-m-d');
        }

        if ($this->periodoAte === '') {
            $this->periodoAte = now()->format('Y-m-d');
        }

        if ($this->periodoDeApplied === '') {
            $this->periodoDeApplied = $this->periodoDe;
        }

        if ($this->periodoAteApplied === '') {
            $this->periodoAteApplied = $this->periodoAte;
        }
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-ajustes-estoque-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um ajuste';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-ajustes-estoque__input',
            'searchFocusKey' => 'F5',
            'create' => 'createAjuste',
            'edit' => 'editAjuste',
            'delete' => 'deleteAjuste',
            'extraKeys' => [
                'F4' => ['method' => 'printAjustes'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(AjusteEstoqueResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()->with(['product']);

        if ($this->informarPeriodo) {
            if (filled($this->periodoDeApplied)) {
                $query->whereDate('data', '>=', $this->periodoDeApplied);
            }

            if (filled($this->periodoAteApplied)) {
                $query->whereDate('data', '<=', $this->periodoAteApplied);
            }
        }

        if (filled($this->localSearch)) {
            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
            $like = '%' . $term . '%';

            match ($this->searchColumn) {
                'codigo' => $query->whereHas('product', fn (Builder $productQuery): Builder => $productQuery->where('codigo', 'like', $like)),
                'data' => $query->whereDate('data', str_contains($term, '/') ? implode('-', array_reverse(explode('/', $term))) : $term),
                default => $query->whereHas('product', fn (Builder $productQuery): Builder => $productQuery->where('descricao', 'like', $like)),
            };
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.ajustes-estoque.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.ajustes-estoque.action-bar'),
                View::make('filament.components.erp.ajustes-estoque.modal'),
            ]);
    }

    public function applyPeriodFilter(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()->title('Período filtrado.')->success()->send();
    }

    public function updatedInformarPeriodo(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function modulePending(string $module): void
    {
        if ($this->showAjusteForm) {
            return;
        }

        Notification::make()
            ->title($module)
            ->body('Em implementação.')
            ->info()
            ->send();
    }

    public function refreshTable(): void
    {
        if ($this->showAjusteForm) {
            return;
        }

        $this->resetTable();
        Notification::make()->title('Lista atualizada.')->success()->send();
    }

    public function printAjustes(): void
    {
        if ($this->showAjusteForm) {
            return;
        }

        if (! $this->erpAuthorizeOrNotify('ajuste_estoque.access')) {
            return;
        }

        $builder = new AjusteEstoqueListQueryBuilder(
            informarPeriodo: $this->informarPeriodo,
            periodoDe: $this->periodoDeApplied,
            periodoAte: $this->periodoAteApplied,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
        );

        $params = array_filter(
            $builder->reportFilters(),
            fn ($value, $key): bool => filled($value) || ($key === 'periodo' && $value === '0'),
            ARRAY_FILTER_USE_BOTH,
        );

        if (($params['periodo'] ?? '1') === '1') {
            unset($params['periodo']);
        }

        $url = route('erp.reports.ajustes-estoque-listagem', $params);

        $this->redirect($url, navigate: false);
    }
}
