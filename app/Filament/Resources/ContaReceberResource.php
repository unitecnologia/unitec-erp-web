<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContaReceberResource\Pages;
use App\Models\ContaReceber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class ContaReceberResource extends Resource
{
    protected static ?string $model = ContaReceber::class;

    protected static ?string $slug = 'contas-receber';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownCircle;

    protected static ?string $modelLabel = 'conta a receber';

    protected static ?string $pluralModelLabel = 'contas a receber';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('baixa')
                    ->label('')
                    ->view('filament.components.erp.receber.select-cell')
                    ->alignCenter(),
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
                TextColumn::make('historico')
                    ->label('Histórico')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('documento')
                    ->label('Doc.')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente.nome_razao')
                    ->label('Cliente')
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
                TextColumn::make('valor_recebido')
                    ->label('Vl Recebido')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('recebido_em')
                    ->label('Recebido Em')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::Bold),
                ViewColumn::make('visualizar')
                    ->label('')
                    ->view('filament.components.erp.receber.view-cell')
                    ->alignCenter(),
            ])
            ->defaultSort('vencimento', 'asc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma conta a receber encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContasReceber::route('/'),
        ];
    }
}
