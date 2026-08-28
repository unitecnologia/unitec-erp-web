<?php

namespace App\Filament\Resources\PromocaoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\PromocaoResource;
use App\Filament\Resources\PromocaoResource\Pages\Concerns\ManagesPromocaoFormModal;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListPromocoes extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;
    use ManagesPromocaoFormModal;

    protected static string $resource = PromocaoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'descricao';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Promoções');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-promocoes-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma promoção';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-unidades__input';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createPromocao';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editPromocao';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deletePromocao';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(PromocaoResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $this->applySimpleLocalSearch($query, $this->localSearch, ['descricao'], 'descricao');
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.promocoes.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.promocoes.action-bar'),
                View::make('filament.components.erp.promocoes.modal'),
            ]);
    }
}
