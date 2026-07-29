<?php

namespace App\Filament\Resources\EmpresaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesErpSearchColumn;
use App\Filament\Resources\EmpresaResource;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListEmpresas extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesErpSearchColumn;

    protected static string $resource = EmpresaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    public function mount(): void
    {
        parent::mount();

        $this->erpRestoreSearchColumnFromSession();

        ErpScreen::set('Empresa');
    }

    /**
     * @return list<string>
     */
    protected function erpAllowedSearchColumns(): array
    {
        return ['codigo', 'fantasia', 'razao_social', 'cidade', 'cnpj', 'ie'];
    }

    protected function erpDefaultSearchColumn(): string
    {
        return 'codigo';
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-empresas-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma empresa';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-empresas__input',
            'create' => 'createEmpresa',
            'edit' => 'editEmpresa',
            'refresh' => 'focusEmpresaSearch',
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(EmpresaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (! filled($this->localSearch)) {
            return $query;
        }

        $term = trim($this->localSearch);
        $column = $this->erpNormalizeSearchColumn($this->searchColumn);
        $like = '%'.$term.'%';

        if ($column === 'codigo') {
            if (is_numeric($term)) {
                return $query->where('codigo', (int) $term);
            }

            return $query->where('codigo', 'like', $like);
        }

        return $query->where($column, 'like', $like);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.empresas.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.empresas.action-bar'),
            ]);
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = $this->erpDefaultSearchColumn();
        $this->clearListSelection();
        $this->resetTable();
    }

    public function search(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function focusEmpresaSearch(): void
    {
        $this->dispatch('erp-empresa-focus-search');
    }

    public function createEmpresa(): void
    {
        $this->redirect(EmpresaResource::getUrl('create'));
    }

    public function editEmpresa(): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        $this->redirect(EmpresaResource::getUrl('edit', ['record' => $recordId]));
    }
}
