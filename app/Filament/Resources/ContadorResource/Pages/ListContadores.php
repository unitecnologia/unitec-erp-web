<?php

namespace App\Filament\Resources\ContadorResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\ContadorResource;
use App\Filament\Resources\ContadorResource\Pages\Concerns\ManagesContadorDeleteConfirm;
use App\Filament\Resources\ContadorResource\Pages\Concerns\ManagesContadorFormModal;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListContadores extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesContadorDeleteConfirm;
    use ManagesContadorFormModal;

    protected static string $resource = ContadorResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Contadores');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-contadores-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um contador';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-contadores__search-text',
            'create' => 'createContador',
            'edit' => 'editContador',
            'delete' => 'deleteContador',
            'extraKeys' => [
                'F4' => ['method' => 'modulePending', 'params' => ['Imprimir']],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(ContadorResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $allowed = ['codigo', 'nome', 'cnpj_cpf', 'cidade', 'email', 'fone'];
            $column = in_array($this->searchColumn, $allowed, true)
                ? $this->searchColumn
                : 'codigo';

            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');

            if ($column === 'cnpj_cpf' || $column === 'fone') {
                $digits = preg_replace('/\D/', '', $term) ?? '';
                $query->where($column, 'like', '%'.$digits.'%');
            } else {
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
                View::make('filament.components.erp.contadores.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.contadores.action-bar'),
                View::make('filament.components.erp.contadores.form-modal'),
                View::make('filament.components.erp.contadores.confirm-delete-modal'),
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
