<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnidadeResource\Pages;
use App\Models\Unidade;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnidadeResource extends Resource
{
    protected static ?string $model = Unidade::class;

    protected static ?string $slug = 'unidades';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $modelLabel = 'unidade';

    protected static ?string $pluralModelLabel = 'unidades';

    protected static ?string $recordTitleAttribute = 'descricao';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sigla')
                    ->label('Sigla')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('descricao')
                    ->label('DescriÃ§Ã£o')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
            ])
            ->defaultSort('sigla', 'asc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma unidade encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnidades::route('/'),
        ];
    }
}
