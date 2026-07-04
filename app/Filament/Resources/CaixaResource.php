<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CaixaResource\Pages;
use App\Models\CaixaLancamento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class CaixaResource extends Resource
{
    protected static ?string $model = CaixaLancamento::class;

    protected static ?string $slug = 'caixa';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'lançamento';

    protected static ?string $pluralModelLabel = 'caixa';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('>>Código')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('emissao')
                    ->label('Emissão')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('documento')
                    ->label('Documento')
                    ->placeholder('—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('historico')
                    ->label('Histórico')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('plano_contas')
                    ->label('Plano de Contas')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('conta.nome')
                    ->label('Contas')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('entrada')
                    ->label('Entrada')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('saida')
                    ->label('Saída')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                ViewColumn::make('ver_itens')
                    ->label('')
                    ->state(fn (): bool => true)
                    ->width('1.35rem')
                    ->view('filament.components.erp.caixa.columns.ver-itens')
                    ->alignCenter()
                    ->disabledClick(),
            ])
            ->defaultSort('codigo', 'asc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum lançamento encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCaixa::route('/'),
        ];
    }
}
