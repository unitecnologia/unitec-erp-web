<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ForcaVendasPedidoResource\Pages;
use App\Models\ForcaVendasOrder;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ForcaVendasPedidoResource extends Resource
{
    protected static ?string $model = ForcaVendasOrder::class;

    protected static ?string $slug = 'forca-vendas-pedidos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'orçamento';

    protected static ?string $pluralModelLabel = 'orçamentos';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('forca_vendas.access');
    }

    /**
     * "Orçamentos recebidos" mostra apenas os documentos do tipo "orcamento"
     * (os do tipo "pedido" vão para a tela "Monitor de Vendas").
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'orcamento');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('received_at')
                    ->label('Recebido')
                    ->dateTime('d/m/Y H:i')
                    ->timezone(\App\Support\Erp\ErpTimezone::DEFAULT)
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('user.name')
                    ->label('Vendedor')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente')
                    ->label('Cliente')
                    ->grow()
                    ->wrap(false)
                    ->weight(FontWeight::Bold)
                    ->state(function (ForcaVendasOrder $record): string {
                        $payload = $record->payload ?? [];

                        return $payload['cliente_nome']
                            ?? ($record->cliente_id ? '#' . $record->cliente_id : '—');
                    }),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'orcamento' => 'Orçamento',
                        'venda' => 'Venda',
                        default => (string) $state,
                    })
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => 'R$ ' . number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('orcamento_id')
                    ->label('Orçamento')
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        ForcaVendasOrder::STATUS_IMPORTADO => 'Importado',
                        ForcaVendasOrder::STATUS_ERRO => 'Erro',
                        default => (string) $state,
                    })
                    ->color(fn (?string $state): string => $state === ForcaVendasOrder::STATUS_ERRO ? 'danger' : 'success')
                    ->badge()
                    ->alignCenter(),
            ])
            ->defaultSort('received_at', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum orçamento recebido');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForcaVendasPedidos::route('/'),
        ];
    }
}
