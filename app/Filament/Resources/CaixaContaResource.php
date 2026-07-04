<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CaixaContaResource\Pages;
use App\Models\CaixaConta;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CaixaContaResource extends Resource
{
    protected static ?string $model = CaixaConta::class;

    protected static ?string $slug = 'contas-caixa';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $modelLabel = 'conta caixa';

    protected static ?string $pluralModelLabel = 'contas caixa';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('contas_caixa.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('» Código')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nome')
                    ->label('Descrição')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state, CaixaConta $record): string => $record->tipoLabel())
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state, CaixaConta $record): string => $record->situacaoLabel())
                    ->color(fn (CaixaConta $record): string => $record->situacao === CaixaConta::SITUACAO_ABERTO ? 'success' : 'gray')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('ultimoUsuario.name')
                    ->label('Último Usuário')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state !== null && $state !== '' ? mb_strtoupper($state, 'UTF-8') : '—')
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('codigo', 'asc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma conta caixa encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCaixaContas::route('/'),
        ];
    }
}
