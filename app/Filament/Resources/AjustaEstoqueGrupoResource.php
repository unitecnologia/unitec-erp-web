<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AjustaEstoqueGrupoResource\Pages;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AjustaEstoqueGrupoResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'ajusta-estoque-grupo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $modelLabel = 'produto';

    protected static ?string $pluralModelLabel = 'ajuste estoque por grupo';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('CÃ³digo')->alignCenter()->weight(FontWeight::SemiBold),
                TextColumn::make('descricao')->label('Produto')->wrap(false)->weight(FontWeight::Bold),
                TextColumn::make('estoque')
                    ->label('Qtd. Atual')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('codigo')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum produto encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAjustaEstoqueGrupo::route('/'),
        ];
    }
}
