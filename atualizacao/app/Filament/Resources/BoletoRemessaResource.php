<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BoletoRemessaResource\Pages;
use App\Models\BoletoRemessa;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BoletoRemessaResource extends Resource
{
    protected static ?string $model = BoletoRemessa::class;

    protected static ?string $slug = 'boleto-remessas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $modelLabel = 'remessa de boleto';

    protected static ?string $pluralModelLabel = 'remessas de boleto';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('boletos.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Código')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('data')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('banco_id')
                    ->label('Banco')
                    ->alignCenter(),
                TextColumn::make('agencia')
                    ->label('Agência')
                    ->formatStateUsing(fn (?string $state, BoletoRemessa $record): string => trim($state.'/'.($record->agencia_digito ?? ''), '/')),
                TextColumn::make('conta')
                    ->label('Conta')
                    ->formatStateUsing(fn (?string $state, BoletoRemessa $record): string => trim($state.'/'.($record->conta_digito ?? ''), '/')),
                TextColumn::make('carteira')
                    ->label('Carteira')
                    ->alignCenter(),
                TextColumn::make('qtd_titulos')
                    ->label('Títulos')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('valor_total')
                    ->label('Valor')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => 'R$ '.number_format((float) $state, 2, ',', '.')),
                TextColumn::make('cancelada')
                    ->label('Sit.')
                    ->alignCenter()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'CANCELADA' : 'ATIVA')
                    ->weight(FontWeight::Bold),
                TextColumn::make('local_arquivo')
                    ->label('Arquivo')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma remessa encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoletoRemessas::route('/'),
        ];
    }
}
