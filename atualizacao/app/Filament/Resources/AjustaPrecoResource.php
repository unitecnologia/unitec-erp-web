<?php

namespace App\Filament\Resources;

use App\Support\Erp\ErpAccess;
use App\Filament\Resources\AjustaPrecoResource\Pages;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class AjustaPrecoResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'ajusta-precos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $modelLabel = 'produto';

    protected static ?string $pluralModelLabel = 'ajuste de preços em lote';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('ajusta_preco.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('selecionar')
                    ->label('')
                    ->view('filament.components.erp.ajusta-precos.select-cell')
                    ->alignCenter(),
                TextColumn::make('codigo')
                    ->label('Código')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->wrap(false)
                    ->weight(FontWeight::Bold)
                    ->limit(52),
                TextColumn::make('ultFornecedor.nome_razao')
                    ->label('Fornecedor')
                    ->placeholder('—')
                    ->wrap(false)
                    ->limit(28),
                TextColumn::make('marca')
                    ->label('Marca')
                    ->placeholder('—')
                    ->wrap(false)
                    ->limit(18),
                TextColumn::make('grupo')
                    ->label('Grupo')
                    ->placeholder('—')
                    ->wrap(false)
                    ->limit(18),
                TextColumn::make('ncm')
                    ->label('NCM')
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('ativo')
                    ->label('Status')
                    ->formatStateUsing(fn ($state): string => $state ? 'Ativo' : 'Inativo')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->alignCenter(),
                TextColumn::make('pct_lucro')
                    ->label('% Lucro')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('preco_venda')
                    ->label('Pr. Varejo')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('preco_atacado')
                    ->label('Pr. Atacado')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('preco_especial')
                    ->label('Pr. Especial')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
            ])
            ->defaultSort('codigo')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Não há dados para mostrar');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAjustaPrecos::route('/'),
        ];
    }
}
