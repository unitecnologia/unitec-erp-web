<?php

namespace App\Filament\Resources;

use App\Support\Erp\ErpAccess;
use App\Filament\Resources\EntregadorResource\Pages;
use App\Models\Entregador;
use App\Support\Erp\ErpTableSort;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EntregadorResource extends Resource
{
    protected static ?string $model = Entregador::class;

    protected static ?string $slug = 'entregadores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'entregador';

    protected static ?string $pluralModelLabel = 'entregadores';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('entregadores.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => ErpTableSort::orderByCodigoNumerico($query, $direction))
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nome')
                    ->label('Nome')
                    ->sortable()
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
            ])
            ->defaultSort(fn (Builder $query, string $direction, $livewire): Builder => ErpTableSort::applyDefaultCodigoNumerico($query, $direction, $livewire))
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum entregador encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntregadores::route('/'),
        ];
    }
}
