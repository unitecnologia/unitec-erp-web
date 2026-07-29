<?php

namespace App\Filament\Resources;

use App\Support\Erp\ErpAccess;
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

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('grupos.access');
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
                TextColumn::make('mostrar_no_app')
                    ->label('App')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => $state ? '�o"' : '')
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
