<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ForcaVendasDeviceResource\Pages;
use App\Models\ForcaVendasDevice;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ForcaVendasDeviceResource extends Resource
{
    protected static ?string $model = ForcaVendasDevice::class;

    protected static ?string $slug = 'forca-vendas-aparelhos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $modelLabel = 'aparelho';

    protected static ?string $pluralModelLabel = 'aparelhos';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('forca_vendas.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('device_name')
                    ->label('Aparelho')
                    ->grow()
                    ->wrap(false)
                    ->placeholder('—')
                    ->weight(FontWeight::Bold),
                TextColumn::make('pairing_code')
                    ->label('Código')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::Bold)
                    ->copyable(),
                TextColumn::make('user.name')
                    ->label('Vendedor')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('platform')
                    ->label('Plataforma')
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('registered_at')
                    ->label('Solicitado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('last_seen_at')
                    ->label('Último acesso')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->state(fn (ForcaVendasDevice $record): string => $record->situacaoLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'Revogado' => 'danger',
                        'Pendente' => 'warning',
                        default => 'success',
                    })
                    ->badge()
                    ->alignCenter(),
            ])
            ->defaultSort('registered_at', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum aparelho registrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForcaVendasAparelhos::route('/'),
        ];
    }
}
