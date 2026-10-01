<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CargaResource\Pages;
use App\Models\Carga;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class CargaResource extends Resource
{
    protected static ?string $model = Carga::class;

    protected static ?string $slug = 'cargas-romaneio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'carga';

    protected static ?string $pluralModelLabel = 'cargas';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('cargas.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('selecionar')
                    ->label('')
                    ->view('filament.components.erp.cargas.select-cell')
                    ->alignCenter(),
                TextColumn::make('numero')
                    ->label('Nº')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('motorista.proprietario')
                    ->label('Motorista')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('entregador.name')
                    ->label('Entregador (App)')
                    ->placeholder('—')
                    ->wrap(false),
                TextColumn::make('veiculo.placa')
                    ->label('Veículo')
                    ->placeholder('—')
                    ->formatStateUsing(function (?string $state, Carga $record): string {
                        if (blank($state)) {
                            return '—';
                        }

                        $descricao = trim((string) ($record->veiculo?->descricao ?? ''));

                        return $descricao !== '' ? "{$state} — {$descricao}" : $state;
                    })
                    ->wrap(false),
                TextColumn::make('pedidos_count')
                    ->label('Qtd. pedidos')
                    ->alignCenter(),
                TextColumn::make('valor_total')
                    ->label('Valor total')
                    ->state(fn (Carga $record): float => (float) ($record->pedidos_sum_total ?? 0))
                    ->money('BRL', locale: 'pt_BR')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => Carga::statusLabels()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Carga::STATUS_ABERTA => 'info',
                        Carga::STATUS_FECHADA => 'success',
                        Carga::STATUS_CANCELADA => 'danger',
                        default => 'gray',
                    })
                    ->alignCenter(),
            ])
            ->defaultSort('numero', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma carga encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCargas::route('/'),
        ];
    }
}
