<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AniversarianteResource\Pages;
use App\Models\Person;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AniversarianteResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static ?string $slug = 'aniversariantes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static ?string $modelLabel = 'aniversariante';

    protected static ?string $pluralModelLabel = 'aniversariantes';

    protected static ?string $recordTitleAttribute = 'nome_razao';

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
                TextColumn::make('nome_razao')
                    ->label('Nome')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('apelido_fantasia')
                    ->label('Apelido')
                    ->placeholder('â€”')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('nome_razao', 'asc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum aniversariante encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAniversariantes::route('/'),
        ];
    }
}
