<?php

namespace App\Filament\Resources\OsVeiculoResource\Pages;

use App\Filament\Concerns\InteractsWithErpDualSearchFields;
use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\OsVeiculoResource;
use App\Filament\Resources\OsVeiculoResource\Pages\Concerns\ManagesOsVeiculoFormModal;
use App\Filament\Resources\OsVeiculoResource\Pages\Concerns\ManagesOsVeiculoHistorico;
use App\Models\OsVeiculo;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpSearchFieldSelection;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListOsVeiculos extends ListRecords
{
    use InteractsWithErpDualSearchFields;
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

        if (! request()->has('campo')) {
            $this->searchColumn = (string) session($this->searchColumnSessionKey(), $this->searchColumn);
        }

        $this->searchColumn = $this->normalizeSearchColumn($this->searchColumn);
        $this->erpRestoreDualSearchFields($this->allowedSearchColumns(), $this->searchFieldsSessionKey());

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

    /**
     * @return list<string>
     */
    protected function allowedSearchColumns(): array
    {
        return ['placa', 'descricao', 'marca', 'modelo', 'chassi', 'renavam', 'cor', 'cidade'];
    }

    protected function searchFieldsSessionKey(): string
    {
        return 'erp_os_veiculos_search_fields';
    }

    protected function searchColumnSessionKey(): string
    {
        return 'erp_os_veiculos_search_column';
    }

    protected function normalizeSearchColumn(mixed $value): string
    {
        return in_array($value, $this->allowedSearchColumns(), true) ? (string) $value : 'placa';
    }

    /**
     * @return list<string>
     */
    protected function activeSearchColumns(): array
    {
        return $this->searchFieldsActive !== []
            ? $this->searchFieldsActive
            : [$this->normalizeSearchColumn($this->searchColumn)];
    }

    public function setSearchColumn(string $column): void
    {
        $normalized = $this->normalizeSearchColumn($column);

        if (! $this->erpToggleDualSearchField($normalized, $this->allowedSearchColumns(), $this->searchFieldsSessionKey())) {
            return;
        }

        session([$this->searchColumnSessionKey() => $this->searchColumn]);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(OsVeiculoResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (! filled($this->localSearch)) {
            return $query;
        }

        $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
        if ($term === '') {
            return $query;
        }

        $columns = $this->activeSearchColumns();

        ErpSearchFieldSelection::applyOr($query, $columns, function (Builder $inner, string $column) use ($term): void {
            $valor = $column === 'placa' ? OsVeiculo::normalizarPlaca($term) : $term;
            if ($valor === '') {
                return;
            }

            $inner->where($column, 'like', '%'.$valor.'%');
        });

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

    public function updatedLocalSearch(): void
    {
        if (filled($this->localSearch)) {
            $this->localSearch = mb_strtoupper(trim($this->localSearch), 'UTF-8');
        }

        $this->clearListSelection();
        $this->resetPage();
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function updatedSearchColumn(): void
    {
        // Dual search usa setSearchColumn (toggle). Evita limpar o termo ao marcar 2º campo.
    }

    public function search(): void
    {
        $this->updatedLocalSearch();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->erpApplyDualSearchFields(['placa'], $this->searchFieldsSessionKey());
        session([$this->searchColumnSessionKey() => $this->searchColumn]);
        $this->clearListSelection();
        $this->resetTable();
    }
}
