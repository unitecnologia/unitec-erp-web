<?php

namespace App\Filament\Resources\OutrasSaidaMovimentoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\OutrasSaidaMovimentoResource;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ListOutrasSaidasMovimento extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = OutrasSaidaMovimentoResource::class;

    protected static ?string $title = '';

    public function mount(): void
    {
        parent::mount();
        ErpScreen::set('Outras Saídas/Movimento');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-outras-saidas-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um movimento';
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(OutrasSaidaMovimentoResource::table($table));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->gap(false)->components([
            View::make('filament.components.erp.outras-saidas-movimento.screen'),
            EmbeddedTable::make()->columnSpanFull(),
        ]);
    }

    public function openNovoMovimento(): void
    {
        Notification::make()
            ->title('Em breve')
            ->body('O cadastro de itens e a finalização segura da saída de estoque estão em implementação.')
            ->info()
            ->send();
    }
}
