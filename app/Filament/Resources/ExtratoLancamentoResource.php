<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExtratoLancamentoResource\Pages;
use App\Models\ExtratoLancamento;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * C1 — Listagem da Conciliação Bancária (padrão visual RH Treinamentos).
 */
class ExtratoLancamentoResource extends Resource
{
    protected static ?string $model = ExtratoLancamento::class;

    protected static ?string $slug = 'conciliacao-bancaria';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $modelLabel = 'lançamento de extrato';

    protected static ?string $pluralModelLabel = 'lançamentos de extrato';

    protected static ?string $recordTitleAttribute = 'id';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('conciliacao_bancaria.access');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->select([
                'id',
                'empresa_id',
                'caixa_conta_id',
                'extrato_importacao_id',
                'data_movimento',
                'tipo',
                'valor',
                'historico',
                'fitid',
                'situacao',
                'origem_tipo',
                'origem_id',
                'caixa_lancamento_id',
            ]);

        // Evita N+1 e deixa claro na grade o que não está disponível para C3.
        if (Schema::hasTable('tef_liquidacao_lote_extratos')) {
            $query->withExists('vinculosTefAtivos');
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data_movimento')
                    ->label('Data')
                    ->formatStateUsing(fn (mixed $state): string => ExtratoLancamento::formatarDataMovimento($state))
                    ->alignCenter()
                    ->wrap(false)
                    ->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->alignCenter()
                    ->formatStateUsing(fn (mixed $state): string => match ((string) $state) {
                        ExtratoLancamento::TIPO_CREDITO => 'Entrada',
                        ExtratoLancamento::TIPO_DEBITO => 'Saída',
                        default => 'Outro',
                    }),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('historico')
                    ->label('Histórico')
                    ->wrap(false)
                    ->placeholder('—'),
                TextColumn::make('fitid')
                    ->label('FITID')
                    ->placeholder('—')
                    ->wrap(false),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(function (mixed $state, ExtratoLancamento $record): string {
                        // Status C3 interno permanece pendente; exibição distingue vínculo TEF.
                        if ((string) $state === ExtratoLancamento::SITUACAO_PENDENTE
                            && $record->temVinculoTefAtivo()
                        ) {
                            return 'Vinculado TEF';
                        }

                        return match ((string) $state) {
                            ExtratoLancamento::SITUACAO_PENDENTE => 'Pendente',
                            ExtratoLancamento::SITUACAO_CONCILIADO => 'Conciliado',
                            ExtratoLancamento::SITUACAO_IGNORADO => 'Ignorado',
                            default => (string) $state,
                        };
                    }),
                TextColumn::make('origem')
                    ->label('Origem')
                    ->state(function (ExtratoLancamento $record): string {
                        if (! filled($record->origem_tipo)) {
                            return '—';
                        }

                        return (string) $record->origem_tipo.'#'.$record->origem_id;
                    }),
            ])
            ->defaultSort('data_movimento', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum lançamento de extrato neste filtro');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExtratoLancamentos::route('/'),
        ];
    }
}
