<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ErpOperacaoLogResource\Pages;
use App\Models\ErpOperacaoLog;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ErpOperacaoLogResource extends Resource
{
    protected static ?string $model = ErpOperacaoLog::class;

    protected static ?string $slug = 'log-operacoes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'log de operação';

    protected static ?string $pluralModelLabel = 'logs de operações';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('vendas.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ocorrido_em')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('operacao')
                    ->label('Operação')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('origem')
                    ->label('Origem')
                    ->placeholder('—')
                    ->wrap(false),
                TextColumn::make('documento_numero')
                    ->label('Documento')
                    ->formatStateUsing(function (?string $state, ErpOperacaoLog $record): string {
                        $tipo = $record->documento_tipo ? mb_strtoupper((string) $record->documento_tipo, 'UTF-8') : '';
                        $numero = $state !== null && $state !== '' ? (string) $state : (string) ($record->documento_id ?? '');

                        if ($tipo === '' && $numero === '') {
                            return '—';
                        }

                        return trim($tipo.' #'.$numero);
                    })
                    ->wrap(false),
                TextColumn::make('resultado')
                    ->label('Resultado')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtolower((string) $state, 'UTF-8')) {
                        'ok' => 'success',
                        'erro' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => mb_strtoupper((string) ($state ?: '—'), 'UTF-8')),
                TextColumn::make('user_nome')
                    ->label('Usuário')
                    ->placeholder('—')
                    ->wrap(false),
                TextColumn::make('resumo')
                    ->label('Resumo')
                    ->wrap()
                    ->limit(80),
            ])
            ->defaultSort('ocorrido_em', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma operação registrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListErpOperacaoLogs::route('/'),
        ];
    }
}
