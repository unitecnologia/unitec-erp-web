<?php

namespace App\Filament\Resources;

use App\Support\Erp\ErpAccess;
use App\Filament\Resources\MarcaResource\Pages;
use App\Models\Marca;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MarcaResource extends Resource
{
    protected static ?string $model = Marca::class;

    protected static ?string $slug = 'marcas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'marca';

    protected static ?string $pluralModelLabel = 'marcas';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('marcas.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Código')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nome')
                    ->label('Descrição')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
            ])
            ->defaultSort('id', 'asc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma marca encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarcas::route('/'),
        ];
    }
}
