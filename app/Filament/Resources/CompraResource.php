<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompraResource\Pages;
use App\Models\Compra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class CompraResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static ?string $slug = 'compras';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = 'compra';

    protected static ?string $pluralModelLabel = 'compras';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')
                    ->label('» Número')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        $trimmed = ltrim($state, '0');

                        return $trimmed !== '' ? $trimmed : '0';
                    }),
                TextColumn::make('data_emissao')
                    ->label('Dt. Emissão')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('data_entrada')
                    ->label('Dt. Entrada')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('numero_nota')
                    ->label('Nº da Nota')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('fornecedor.nome_razao')
                    ->label('Fornecedor')
                    ->grow()
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('chave_nfe')
                    ->label('Chave')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => 'R$ ' . number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                ViewColumn::make('ver_itens')
                    ->label('')
                    ->state(fn (): bool => true)
                    ->width('1.35rem')
                    ->view('filament.components.erp.compras.columns.ver-itens')
                    ->alignCenter()
                    ->disabledClick(),
            ])
            ->defaultSort('numero', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma compra encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompras::route('/'),
        ];
    }
}
