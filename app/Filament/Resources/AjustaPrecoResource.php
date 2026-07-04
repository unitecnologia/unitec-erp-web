<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AjustaPrecoResource\Pages;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class AjustaPrecoResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'ajusta-precos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $modelLabel = 'produto';

    protected static ?string $pluralModelLabel = 'ajuste de preÃ§os';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('CÃ³digo')->alignCenter()->weight(FontWeight::SemiBold),
                TextColumn::make('referencia')->label('ReferÃªncia')->placeholder('â€”')->alignCenter()->weight(FontWeight::SemiBold),
                TextColumn::make('codigo_barras')->label('CÃ³d. Barras')->placeholder('â€”')->weight(FontWeight::SemiBold),
                TextColumn::make('descricao')->label('DescriÃ§Ã£o')->wrap(false)->weight(FontWeight::Bold),
                TextInputColumn::make('preco_compra')->label('Pr.Compra')->type('number')->step(0.01)->alignEnd(),
                TextInputColumn::make('pct_custos')->label('% Custo')->type('number')->step(0.01)->alignEnd(),
                TextInputColumn::make('preco_custo')->label('Pr. Custo')->type('number')->step(0.01)->alignEnd(),
                TextInputColumn::make('pct_lucro')->label('Margem')->type('number')->step(0.01)->alignEnd(),
                TextInputColumn::make('preco_venda')->label('Pr.Venda')->type('number')->step(0.01)->alignEnd(),
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
            'index' => Pages\ListAjustaPrecos::route('/'),
        ];
    }
}
