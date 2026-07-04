<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContaPagarResource\Pages;
use App\Models\ContaPagar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContaPagarResource extends Resource
{
    protected static ?string $model = ContaPagar::class;

    protected static ?string $slug = 'contas-pagar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpCircle;

    protected static ?string $modelLabel = 'conta a pagar';

    protected static ?string $pluralModelLabel = 'contas a pagar';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')
                    ->label('>>Número')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('emissao')
                    ->label('Emissão')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('produto')
                    ->label('Produto')
                    ->wrap(false)
                    ->placeholder('—')
                    ->weight(FontWeight::Bold),
                TextColumn::make('documento')
                    ->label('Doc')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('fornecedor.nome_razao')
                    ->label('Fornecedor')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('desconto')
                    ->label('Desconto')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('juros')
                    ->label('Juros')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('valor_pago')
                    ->label('Vl. Pago')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pago_em')
                    ->label('Pago Em')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::Bold),
            ])
            ->defaultSort('vencimento', 'asc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma conta a pagar encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContasPagar::route('/'),
        ];
    }
}
