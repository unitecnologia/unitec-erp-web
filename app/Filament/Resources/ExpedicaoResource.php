<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpedicaoResource\Pages;
use App\Models\Entrega;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class ExpedicaoResource extends Resource
{
    protected static ?string $model = Entrega::class;

    protected static ?string $slug = 'expedicao-controle';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'expedição';

    protected static ?string $pluralModelLabel = 'expedições';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('logistica.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('selecionar')
                    ->label('')
                    ->view('filament.components.erp.expedicao.select-cell')
                    ->alignCenter(),
                TextColumn::make('venda.numero')
                    ->label('Nº Pedido')
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        $trimmed = ltrim($state, '0');

                        return $trimmed !== '' ? $trimmed : '0';
                    })
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->wrap(false)
                    ->weight(FontWeight::Bold)
                    ->placeholder('CONSUMIDOR'),
                TextColumn::make('venda.data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->alignCenter(),
                TextColumn::make('venda.hora')
                    ->label('Hora')
                    ->formatStateUsing(function ($state): string {
                        if ($state === null) {
                            return '—';
                        }

                        return substr((string) $state, 0, 8);
                    })
                    ->alignCenter(),
                TextColumn::make('usuarioExpedicao.name')
                    ->label('Usuário')
                    ->placeholder('—')
                    ->wrap(false),
                ViewColumn::make('venda.total')
                    ->label('Total')
                    ->view('filament.components.erp.expedicao.columns.total')
                    ->alignStart()
                    ->disabledClick(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => Entrega::statusLabels()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Entrega::STATUS_PENDENTE => 'warning',
                        Entrega::STATUS_EM_EXPEDICAO => 'info',
                        Entrega::STATUS_EXPEDIDO => 'success',
                        Entrega::STATUS_CANCELADO => 'danger',
                        default => 'gray',
                    })
                    ->alignCenter(),
            ])
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum pedido encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpedicaoControle::route('/'),
        ];
    }
}
