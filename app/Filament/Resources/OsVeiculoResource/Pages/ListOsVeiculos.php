<?php

namespace App\Filament\Resources\OsVeiculoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\OsVeiculoResource;
use App\Filament\Resources\OsVeiculoResource\Pages\Concerns\ManagesOsVeiculoFormModal;
use App\Filament\Resources\OsVeiculoResource\Pages\Concerns\ManagesOsVeiculoHistorico;
use App\Models\OsVeiculo;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListOsVeiculos extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;
    use ManagesOsVeiculoFormModal;
    use ManagesOsVeiculoHistorico;

    protected static string $resource = OsVeiculoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'placa';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Veículo/Equipamento');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-veiculos-page';
    }

    protected function erpListExtraPageClasses(): array
    {
        return ['erp-os-veiculos-page'];
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
        return 'createOsVeiculo';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editOsVeiculo';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteOsVeiculo';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(OsVeiculoResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $colunas = ['placa', 'descricao', 'marca', 'modelo', 'chassi', 'renavam', 'cor', 'cidade'];
            $column = in_array($this->searchColumn, $colunas, true) ? $this->searchColumn : 'placa';
            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
            if ($column === 'placa') {
                $term = OsVeiculo::normalizarPlaca($term);
            }

            if ($term !== '') {
                $query->where($column, 'like', '%'.$term.'%');
            }
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.os-veiculos.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.os-veiculos.action-bar'),
                View::make('filament.components.erp.os-veiculos.form-modal'),
                View::make('filament.components.erp.os-veiculos.historico-modal'),
            ]);
    }

    public function search(): void
    {
        if (filled($this->localSearch)) {
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
}
