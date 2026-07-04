<?php

namespace App\Filament\Resources\UnidadeResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\UnidadeResource;
use App\Models\Unidade;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListUnidades extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;

    protected static string $resource = UnidadeResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'sigla';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Unidade');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-unidades-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma unidade';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-unidades__input';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createUnidade';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editUnidade';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteUnidade';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(UnidadeResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
            $column = in_array($this->searchColumn, ['sigla', 'descricao'], true)
                ? $this->searchColumn
                : 'sigla';

            $query->where($column, 'like', '%' . $term . '%');
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.unidades.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.unidades.action-bar'),
            ]);
    }

    public function createUnidade(): void
    {
        $this->modulePending('Cadastro de unidade (Fase 2)');
    }

    public function editUnidade(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $this->modulePending('Alteração de unidade (Fase 2)');
    }

    public function deleteUnidade(): void
    {
        $this->deleteSimpleRecord(Unidade::class, 'Unidade excluída.');
    }
}
