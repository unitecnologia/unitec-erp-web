<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ErpOperacaoLogResource\Pages;
use App\Models\ErpOperacaoLog;
use App\Support\Erp\Audit\ErpOperacaoLogLabels;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
        return ErpAccess::currentCan('auditoria.access');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        return parent::getEloquentQuery()
            ->daEmpresaAtiva($empresaId)
            ->paraListagem();
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
                TextColumn::make('user_nome')
                    ->label('Usuário')
                    ->placeholder('—')
                    ->wrap(false),
                TextColumn::make('operacao')
                    ->label('Operação')
                    ->formatStateUsing(fn (?string $state): string => ErpOperacaoLogLabels::operacao($state))
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('documento_numero')
                    ->label('Documento')
                    ->formatStateUsing(function (?string $state, ErpOperacaoLog $record): string {
                        return ErpOperacaoLogLabels::documento(
                            $record->documento_tipo,
                            $state,
                            $record->documento_id ? (int) $record->documento_id : null,
                        );
                    })
                    ->wrap(false),
                TextColumn::make('resultado')
                    ->label('Resultado')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtolower((string) $state, 'UTF-8')) {
                        'ok', 'concluido', 'concluído' => 'success',
                        'erro', 'error', 'falha' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => ErpOperacaoLogLabels::resultado($state)),
                TextColumn::make('resumo')
                    ->label('Resumo')
                    ->wrap()
                    ->limit(80)
                    ->placeholder('—'),
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
