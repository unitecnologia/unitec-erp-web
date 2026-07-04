<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NfceResource\Pages;
use App\Models\PdvVendaNfce;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NfceResource extends Resource
{
    protected static ?string $model = PdvVendaNfce::class;

    protected static ?string $slug = 'nfce';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $modelLabel = 'NFC-e';

    protected static ?string $pluralModelLabel = 'NFC-e';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('serie')
                    ->label('>>Série')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('numero')
                    ->label('Número')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => $state !== null ? str_pad((string) $state, 6, '0', STR_PAD_LEFT) : '—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.fechado_em')
                    ->label('Dt.Emissão')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('chave')
                    ->label('Chave')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('protocolo')
                    ->label('Protocolo')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.cpf_nota')
                    ->label('CPF')
                    ->placeholder('—')
                    ->alignCenter()
                    ->formatStateUsing(function (?string $state): string {
                        if (! filled($state)) {
                            return '—';
                        }

                        $digits = preg_replace('/\D/', '', $state) ?? '';

                        if (strlen($digits) === 11) {
                            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
                        }

                        return $state;
                    })
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.sessao.terminal.nome')
                    ->label('Caixa')
                    ->placeholder('—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.user.name')
                    ->label('Usuário')
                    ->placeholder('—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.vendedor.nome')
                    ->label('Vendedor')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state, $record): string => filled($state)
                        ? (string) $state
                        : (string) ($record->pdvVenda?->vendedor_nome ?: '—'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.venda.numero')
                    ->label('Nº Pedido')
                    ->alignCenter()
                    ->placeholder('—')
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        $trimmed = ltrim($state, '0');

                        return $trimmed !== '' ? $trimmed : '0';
                    })
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.numero')
                    ->label('Nº Caixa')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => $state !== null ? str_pad((string) $state, 6, '0', STR_PAD_LEFT) : '—')
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('numero', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma NFC-e encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNfces::route('/'),
        ];
    }
}
