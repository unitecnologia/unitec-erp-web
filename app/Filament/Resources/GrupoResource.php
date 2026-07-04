<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GrupoResource\Pages;
use App\Models\Grupo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GrupoResource extends Resource
{
    protected static ?string $model = Grupo::class;

    protected static ?string $slug = 'grupos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'grupo';

    protected static ?string $pluralModelLabel = 'grupos';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('CÃ³digo')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nome')
                    ->label('DescriÃ§Ã£o')
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
            ->emptyStateHeading('Nenhum grupo encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGrupos::route('/'),
        ];
    }
}
