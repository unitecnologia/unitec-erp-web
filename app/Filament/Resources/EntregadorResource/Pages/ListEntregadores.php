<?php

namespace App\Filament\Resources\EntregadorResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\EntregadorResource;
use App\Models\Entregador;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListEntregadores extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = EntregadorResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Entregador');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-entregadores-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um entregador';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-entregadores__input',
            'create' => 'createEntregador',
            'edit' => 'editEntregador',
            'delete' => 'deleteEntregador',
            'extraKeys' => [
                'F4' => ['method' => 'modulePending', 'params' => ['Imprimir']],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(EntregadorResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $column = in_array($this->searchColumn, ['codigo', 'nome'], true)
                ? $this->searchColumn
                : 'codigo';

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
                View::make('filament.components.erp.entregadores.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.entregadores.action-bar'),
            ]);
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function createEntregador(): void
    {
        $this->modulePending('Cadastro de entregador (Fase 2)');
    }

    public function editEntregador(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $this->modulePending('Alteração de entregador (Fase 2)');
    }

    public function deleteEntregador(): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        Entregador::query()->whereKey($recordId)->delete();

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Entregador excluído.')
            ->success()
            ->send();
    }
}
