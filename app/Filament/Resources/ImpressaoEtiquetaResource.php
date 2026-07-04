<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImpressaoEtiquetaResource\Pages;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImpressaoEtiquetaResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'impressao-etiquetas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static ?string $modelLabel = 'produto';

    protected static ?string $pluralModelLabel = 'impressÃ£o de etiquetas';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('CÃ³digo')->alignCenter()->weight(FontWeight::SemiBold),
                TextColumn::make('codigo_barras')->label('CÃ³d.Barra')->placeholder('â€”')->weight(FontWeight::SemiBold),
                TextColumn::make('descricao')->label('DescriÃ§Ã£o')->wrap(false)->weight(FontWeight::Bold),
                TextColumn::make('grupo')->label('Grupo')->alignCenter()->weight(FontWeight::SemiBold),
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
            'index' => Pages\ListImpressaoEtiquetas::route('/'),
        ];
    }
}
