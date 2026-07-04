<?php

namespace App\Filament\Resources\MarcaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\MarcaResource;
use App\Models\Marca;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListMarcas extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;

    protected static string $resource = MarcaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Marcas');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-marcas-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma marca';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-marcas__input';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createMarca';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editMarca';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteMarca';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(MarcaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $this->applySimpleLocalSearch($query, $this->localSearch, ['codigo', 'nome']);
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.marcas.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.marcas.action-bar'),
            ]);
    }

    public function createMarca(): void
    {
        $this->modulePending('Cadastro de marca (Fase 2)');
    }

    public function editMarca(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $this->modulePending('Alteração de marca (Fase 2)');
    }

    public function deleteMarca(): void
    {
        $this->deleteSimpleRecord(Marca::class, 'Marca excluída.');
    }
}
