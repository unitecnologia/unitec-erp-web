<?php

namespace App\Filament\Resources\ForcaVendasPedidoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\ForcaVendasPedidoResource;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListForcaVendasPedidos extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = ForcaVendasPedidoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'todos';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Orçamentos recebidos');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-fv-pedidos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um orçamento';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-entregadores__select',
            'create' => null,
            'edit' => null,
            'delete' => null,
            'extraKeys' => [],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(ForcaVendasPedidoResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()->where('tipo', 'orcamento')->with('user');

        if (in_array($this->statusFilter, ['importado', 'erro'], true)) {
            $query->where('status', $this->statusFilter);
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.forca-vendas.pedidos-screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.forca-vendas.pedidos-action-bar'),
            ]);
    }

    public function updatedStatusFilter(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }
}
