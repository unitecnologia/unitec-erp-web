<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OutrasSaidaMovimentoResource\Pages;
use App\Models\OutrasSaidaMovimento;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OutrasSaidaMovimentoResource extends Resource
{
    protected static ?string $model = OutrasSaidaMovimento::class;

    protected static ?string $slug = 'outras-saidas-movimentos-lista';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('outras_saidas_movimento.access')
            || ErpAccess::currentCan('ajuste_estoque.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->label('Número')->weight(FontWeight::Bold),
                TextColumn::make('data')->label('Data')->date('d/m/Y')->alignCenter(),
                TextColumn::make('tipo_movimento')->label('Movimento')->formatStateUsing(fn (): string => 'Saída de estoque'),
                TextColumn::make('estoque.nome')->label('Estoque')->placeholder('—'),
                TextColumn::make('fornecedor_nome')->label('Fornecedor')->placeholder('—'),
                TextColumn::make('situacao')->label('Situação')->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('total')->label('Total')->money('BRL')->alignEnd(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOutrasSaidasMovimento::route('/'),
        ];
    }
}
