<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AjusteEstoqueResource\Pages;
use App\Models\AjusteEstoque;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AjusteEstoqueResource extends Resource
{
    protected static ?string $model = AjusteEstoque::class;

    protected static ?string $slug = 'ajustes-estoque';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $modelLabel = 'ajuste de estoque';

    protected static ?string $pluralModelLabel = 'ajustes de estoque';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('product.codigo')
                    ->label('Código')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('product.descricao')
                    ->label('Produto')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('qtd_ajust')
                    ->label('Qtd. Ajust.')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('data', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum ajuste encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAjustesEstoque::route('/'),
        ];
    }
}
