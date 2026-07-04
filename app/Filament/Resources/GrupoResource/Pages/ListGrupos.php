<?php

namespace App\Filament\Resources\GrupoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\GrupoResource;
use App\Models\Grupo;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListGrupos extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;

    protected static string $resource = GrupoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Grupos');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-grupos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um grupo';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-grupos__input';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createGrupo';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editGrupo';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteGrupo';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(GrupoResource::table($table));
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
                View::make('filament.components.erp.grupos.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.grupos.action-bar'),
            ]);
    }

    public function createGrupo(): void
    {
        $this->modulePending('Cadastro de grupo (Fase 2)');
    }

    public function editGrupo(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $this->modulePending('Alteração de grupo (Fase 2)');
    }

    public function deleteGrupo(): void
    {
        $this->deleteSimpleRecord(Grupo::class, 'Grupo excluído.');
    }
}
