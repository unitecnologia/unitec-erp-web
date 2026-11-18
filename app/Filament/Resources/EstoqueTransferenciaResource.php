<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EstoqueTransferenciaResource\Pages;
use App\Models\EstoqueTransferencia;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EstoqueTransferenciaResource extends Resource
{
    protected static ?string $model = EstoqueTransferencia::class;

    protected static ?string $slug = 'estoque-transferencias';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $modelLabel = 'transferência de estoque';

    protected static ?string $pluralModelLabel = 'transferências de estoque';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('ajuste_estoque.access')
            || ErpAccess::currentCan('transferencia_estoque.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('numero')
                    ->label('Número')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('estoqueOrigem.nome')
                    ->label('Origem')
                    ->formatStateUsing(function ($state, EstoqueTransferencia $record): string {
                        $cod = trim((string) ($record->estoqueOrigem?->codigo ?? ''));
                        $nome = trim((string) ($record->estoqueOrigem?->nome ?? ''));

                        return $cod !== '' ? $cod.' — '.$nome : ($nome !== '' ? $nome : '—');
                    })
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('estoqueDestino.nome')
                    ->label('Destino')
                    ->formatStateUsing(function ($state, EstoqueTransferencia $record): string {
                        $cod = trim((string) ($record->estoqueDestino?->codigo ?? ''));
                        $nome = trim((string) ($record->estoqueDestino?->nome ?? ''));

                        return $cod !== '' ? $cod.' — '.$nome : ($nome !== '' ? $nome : '—');
                    })
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('itens_count')
                    ->counts('itens')
                    ->label('Itens')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->formatStateUsing(fn ($state, EstoqueTransferencia $record): string => $record->situacaoLabel())
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('data', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma transferência encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEstoqueTransferencias::route('/'),
        ];
    }
}
