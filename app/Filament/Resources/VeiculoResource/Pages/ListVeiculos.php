<?php

namespace App\Filament\Resources\VeiculoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\VeiculoResource;
use App\Filament\Resources\VeiculoResource\Pages\Concerns\ManagesVeiculoFormModal;
use App\Models\Veiculo;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListVeiculos extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;
    use ManagesVeiculoFormModal;

    protected static string $resource = VeiculoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'placa';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativos';

    public function mount(): void
    {
        parent::mount();

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);

        ErpScreen::set('Veículos');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-veiculos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um veículo';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-veiculos__search-text';
    }

    protected function erpSimpleListDefaultSearchColumn(): string
    {
        return 'placa';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createVeiculo';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editVeiculo';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteVeiculo';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(VeiculoResource::table($table));
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        return in_array($value, ['ativos', 'inativos', 'todos'], true) ? (string) $value : 'ativos';
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
            'inativos' => $query->where('ativo', false),
            default => $query,
        };

        if (filled($this->localSearch)) {
            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
            $column = in_array($this->searchColumn, ['placa', 'descricao', 'renavam', 'rntc'], true)
                ? $this->searchColumn
                : 'placa';

            $query->where($column, 'like', '%' . $term . '%');
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.veiculos.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.veiculos.status-filters'),
                View::make('filament.components.erp.veiculos.action-bar'),
                View::make('filament.components.erp.veiculos.form-modal'),
            ]);
    }

    public function search(): void
    {
        if (filled($this->localSearch) && in_array($this->searchColumn, ['placa', 'descricao', 'rntc'], true)) {
            $this->localSearch = mb_strtoupper(trim($this->localSearch), 'UTF-8');
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'placa';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function deleteVeiculo(): void
    {
        if ($this->veiculoModalOpen) {
            return;
        }

        $this->deleteSimpleRecord(Veiculo::class, 'Veículo excluído.');
    }
}
