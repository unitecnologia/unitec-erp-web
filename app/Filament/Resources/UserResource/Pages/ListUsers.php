<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\Concerns\ManagesUserFormModal;
use App\Models\User;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListUsers extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use ManagesUserFormModal;

    protected static string $resource = UserResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'name';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Usuários');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-usuarios-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um usuário';
    }

    protected function customErpListKeyboardConfig(): array
    {
        $config = [
            'searchInput' => '.erp-usuarios__search-text',
            'create' => 'createUser',
            'edit' => 'editUser',
            'delete' => 'deleteUser',
            'extraKeys' => [],
        ];

        if ($this->erpCan('acesso.permissoes.manage')) {
            $config['extraKeys']['F4'] = ['method' => 'openUserPermissions'];
        }

        return $config;
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(UserResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $column = in_array($this->searchColumn, ['name', 'email'], true)
                ? $this->searchColumn
                : 'name';

            $term = mb_strtoupper(trim($this->localSearch), 'UTF-8');
            $query->where($column, 'like', '%' . $term . '%');
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.usuarios.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.usuarios.action-bar'),
                View::make('filament.components.erp.usuarios.form-modal'),
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
        if (filled($this->localSearch) && $this->searchColumn === 'name') {
            $this->localSearch = mb_strtoupper(trim($this->localSearch), 'UTF-8');
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'name';
        $this->clearListSelection();
        $this->resetTable();
    }
}
