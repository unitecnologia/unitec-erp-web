<?php

namespace App\Filament\Resources\EstoqueTransferenciaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\EstoqueTransferenciaResource;
use App\Filament\Resources\EstoqueTransferenciaResource\Pages\Concerns\ManagesEstoqueTransferenciaForm;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListEstoqueTransferencias extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use ManagesEstoqueTransferenciaForm;

    protected static string $resource = EstoqueTransferenciaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'numero';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Transferência de Estoque');

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
        return 'uma transferência';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-ajustes-estoque__input',
            'searchFocusKey' => 'F5',
            'create' => 'createTransferencia',
            'edit' => 'viewTransferencia',
            'delete' => null,
            'extraKeys' => [],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(EstoqueTransferenciaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $query = parent::getTableQuery()
            ->with(['estoqueOrigem:id,codigo,nome', 'estoqueDestino:id,codigo,nome'])
            ->withCount('itens')
            ->when($empresaId > 0, fn (Builder $q): Builder => $q->where('empresa_origem_id', $empresaId));

        if (filled($this->periodoDeApplied)) {
            $query->whereDate('data', '>=', $this->periodoDeApplied);
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereDate('data', '<=', $this->periodoAteApplied);
        }

        if (filled($this->localSearch)) {
            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
            $like = '%'.$term.'%';

            match ($this->searchColumn) {
                'origem' => $query->whereHas(
                    'estoqueOrigem',
                    fn (Builder $e): Builder => $e->where('nome', 'like', $like)->orWhere('codigo', 'like', $like)
                ),
                'destino' => $query->whereHas(
                    'estoqueDestino',
                    fn (Builder $e): Builder => $e->where('nome', 'like', $like)->orWhere('codigo', 'like', $like)
                ),
                default => $query->where('numero', 'like', $like),
            };
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.estoque-transferencias.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.estoque-transferencias.action-bar'),
                View::make('filament.components.erp.estoque-transferencias.modal'),
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

    public function search(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'numero';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function refreshTable(): void
    {
        if ($this->showTransferForm) {
            return;
        }

        $this->resetTable();
        Notification::make()->title('Lista atualizada.')->success()->send();
    }
}
