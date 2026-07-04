<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EntregadorResource\Pages;
use App\Models\Entregador;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EntregadorResource extends Resource
{
    protected static ?string $model = Entregador::class;

    protected static ?string $slug = 'entregadores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'entregador';

    protected static ?string $pluralModelLabel = 'entregadores';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('CÃ³digo')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nome')
                    ->label('Nome')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
            ])
            ->defaultSort('codigo', 'asc')
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
