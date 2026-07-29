<?php

namespace App\Filament\Resources\ImpressaoEtiquetaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\ImpressaoEtiquetaResource;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListImpressaoEtiquetas extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = ImpressaoEtiquetaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    public string $searchColumn = 'q';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Impressão de Etiquetas');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-impressao-etiquetas-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um produto';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-impressao-etiquetas__input',
            'create' => null,
            'edit' => null,
            'delete' => null,
            'extraKeys' => [
                'F4' => ['method' => 'modulePending', 'params' => ['Imprimir']],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(ImpressaoEtiquetaResource::table($table));
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
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('descricao', 'like', $like)
                    ->orWhere('grupo', 'like', $like);
            });
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.impressao-etiquetas.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.impressao-etiquetas.action-bar'),
            ]);
    }

    public function search(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function pesquisar(): void
    {
        $this->search();
    }

    public function limparBusca(): void
    {
        $this->clearSearch();
    }
}
