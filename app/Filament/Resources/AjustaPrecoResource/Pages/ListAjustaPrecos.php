<?php

namespace App\Filament\Resources\AjustaPrecoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\AjustaPrecoResource;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListAjustaPrecos extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = AjustaPrecoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Ajusta Preço em lote');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-ajusta-precos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um produto';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-ajusta-precos__input',
            'create' => null,
            'edit' => null,
            'delete' => null,
        ];
    }

    public function table(Table $table): Table
    {
        return AjustaPrecoResource::table($table)
            ->recordUrl(null)
            ->recordAction(null);
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()->where('ativo', true);

        if (filled($this->localSearch)) {
            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
            $like = '%' . $term . '%';

            $query->where(function (Builder $searchQuery) use ($like): void {
                $searchQuery
                    ->where('codigo', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('descricao', 'like', $like);
            });
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.ajusta-precos.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.ajusta-precos.hint'),
                View::make('filament.components.erp.ajusta-precos.action-bar'),
            ]);
    }
}
