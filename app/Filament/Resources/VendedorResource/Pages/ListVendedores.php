<?php

namespace App\Filament\Resources\VendedorResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\VendedorResource;
use App\Filament\Resources\VendedorResource\Pages\Concerns\ManagesVendedorDeleteConfirm;
use App\Filament\Resources\VendedorResource\Pages\Concerns\ManagesVendedorFormModal;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListVendedores extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesVendedorDeleteConfirm;
    use ManagesVendedorFormModal;

    protected static string $resource = VendedorResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Vendedores');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-vendedores-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um vendedor';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-vendedores__search-text',
            'create' => 'createVendedor',
            'edit' => 'editVendedor',
            'delete' => 'deleteVendedor',
            'extraKeys' => [
                'F4' => ['method' => 'modulePending', 'params' => ['Imprimir']],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(VendedorResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()->with('empresas');

        if (filled($this->localSearch)) {
            $column = in_array($this->searchColumn, ['codigo', 'nome'], true)
                ? $this->searchColumn
                : 'codigo';

            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
            $query->where($column, 'like', '%'.$term.'%');
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.vendedores.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.vendedores.action-bar'),
                View::make('filament.components.erp.vendedores.form-modal'),
                View::make('filament.components.erp.vendedores.confirm-delete-modal'),
            ]);
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        if (filled($this->localSearch) && $this->searchColumn === 'nome') {
            $this->localSearch = mb_strtoupper(trim($this->localSearch), 'UTF-8');
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'codigo';
        $this->clearListSelection();
        $this->resetTable();
    }
}
