<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BoletoRetornoResource\Pages;
use App\Models\BoletoRetorno;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BoletoRetornoResource extends Resource
{
    protected static ?string $model = BoletoRetorno::class;

    protected static ?string $slug = 'boleto-retornos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $modelLabel = 'retorno de boleto';

    protected static ?string $pluralModelLabel = 'retornos de boleto';

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
                TextColumn::make('cadastrado_em')
                    ->label('Cadastro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('arquivo_nome')
                    ->label('Arquivo')
                    ->limit(40)
                    ->placeholder('—')
                    ->weight(FontWeight::Bold),
                TextColumn::make('arquivo_numero')
                    ->label('Nº arq.')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('arquivo_qtd_titulos')
                    ->label('Títulos')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('processado_em')
                    ->label('Processado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state, BoletoRetorno $record): string => mb_strtoupper($record->situacaoLabel(), 'UTF-8'))
                    ->weight(FontWeight::Bold),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum retorno encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoletoRetornos::route('/'),
        ];
    }
}
