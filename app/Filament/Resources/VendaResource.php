<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendaResource\Pages;
use App\Models\Venda;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class VendaResource extends Resource
{
    protected static ?string $model = Venda::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $modelLabel = 'venda';

    protected static ?string $pluralModelLabel = 'vendas';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('vendas.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')
                    ->label('Nº Pedido')
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
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente.nome_razao')
                    ->label('Cliente')
                    ->grow()
                    ->wrap(false)
                    ->tooltip(fn (Venda $record): ?string => $record->cliente?->nome_razao)
                    ->weight(FontWeight::Bold),
                TextColumn::make('vendedor_nome')
                    ->label('Vendedor')
                    ->state(fn (Venda $record): string => $record->vendedorNome())
                    ->placeholder('LOJA')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('plataforma')
                    ->label('Plataforma')
                    ->state(fn (Venda $record): string => $record->plataformaLabel())
                    ->badge()
                    ->color(fn (Venda $record): string => match ($record->plataformaEfetiva()) {
                        Venda::PLATAFORMA_PDV => 'info',
                        Venda::PLATAFORMA_MOBILE => 'warning',
                        default => 'gray',
                    })
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('forma_pagamento')
                    ->label('Meio de Pagamento')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => 'R$ ' . number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('status')
                    ->label('Situação')
                    ->formatStateUsing(fn (string $state): string => Venda::statusLabels()[$state] ?? $state)
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => Venda::tipoLabels()[$state] ?? $state)
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.numero')
                    ->label('Nº Caixa')
                    ->alignCenter()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state): string => $state !== null ? str_pad((string) $state, 6, '0', STR_PAD_LEFT) : '—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.nfce.numero')
                    ->label('NFC-e')
                    ->alignCenter()
                    ->placeholder('—')
                    ->formatStateUsing(function ($state, Venda $record): string {
                        $nfce = $record->pdvVenda?->nfce;
                        if ($nfce === null || $nfce->numero === null) {
                            return '—';
                        }

                        $serie = ltrim((string) ($nfce->serie ?? '1'), '0') ?: '1';
                        $numero = str_pad((string) $nfce->numero, 6, '0', STR_PAD_LEFT);

                        return $serie.' / '.$numero;
                    })
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('hora')
                    ->label('Hora')
                    ->formatStateUsing(function ($state): string {
                        if ($state === null) {
                            return '—';
                        }

                        if ($state instanceof \DateTimeInterface) {
                            return $state->format('H:i');
                        }

                        return substr((string) $state, 0, 5);
                    })
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                ViewColumn::make('ver_itens')
                    ->label('')
                    ->state(fn (): bool => true)
                    ->width('1.35rem')
                    ->view('filament.components.erp.vendas.columns.ver-itens')
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
            ->emptyStateHeading('Nenhuma venda encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendas::route('/'),
        ];
    }
}
