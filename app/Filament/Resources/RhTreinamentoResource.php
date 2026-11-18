<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhTreinamentoResource\Pages;
use App\Models\RhFuncionario;
use App\Models\RhTreinamento;
use App\Models\RhTreinamentoParticipante;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhTreinamentoResource extends Resource
{
    protected static ?string $model = RhTreinamentoParticipante::class;

    protected static ?string $slug = 'rh-treinamentos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $modelLabel = 'treinamento';

    protected static ?string $pluralModelLabel = 'treinamentos';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.treinamentos.access');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'funcionario:id,codigo,nome',
                'realizacao:id,codigo,treinamento_id,realizado_em,vencimento_em',
                'realizacao.treinamento:id,codigo,nome,carga_horaria',
            ])
            ->withCount('anexos');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Cód.')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('funcionario.nome')
                    ->label('Funcionário')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('realizacao.treinamento.nome')
                    ->label('Treinamento')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('realizacao.realizado_em')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('realizacao.treinamento.carga_horaria')
                    ->label('Carga h.')
                    ->alignCenter()
                    ->placeholder('—')
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        return number_format((float) $state, 2, ',', '.');
                    }),
                TextColumn::make('vencimento_em')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('situacao_label')
                    ->label('Situação')
                    ->state(fn (RhTreinamentoParticipante $record): string => $record->situacaoLabel())
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('anexos_count')
                    ->label('Cert.')
                    ->alignCenter()
                    ->state(fn (RhTreinamentoParticipante $record): string => ((int) ($record->anexos_count ?? 0)) > 0 ? 'Sim' : '—'),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum treinamento encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhTreinamentos::route('/'),
        ];
    }

    /**
     * @return Builder<RhFuncionario>
     */
    public static function funcionariosQuery(): Builder
    {
        return RhFuncionario::query()
            ->where('ativo', true)
            ->whereNull('data_demissao')
            ->orderBy('nome')
            ->limit(500);
    }

    /**
     * @return Builder<RhTreinamento>
     */
    public static function treinamentosQuery(bool $somenteAtivos = true): Builder
    {
        $query = RhTreinamento::query()->orderBy('nome')->limit(500);

        return $somenteAtivos ? $query->where('ativo', true) : $query;
    }
}
