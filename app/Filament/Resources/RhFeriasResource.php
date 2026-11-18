<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhFeriasResource\Pages;
use App\Models\RhFeriasGozo;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhFeriasResource extends Resource
{
    protected static ?string $model = RhFeriasGozo::class;

    protected static ?string $slug = 'rh-ferias';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?string $modelLabel = 'férias';

    protected static ?string $pluralModelLabel = 'férias';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.ferias.access');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'funcionario:id,codigo,nome',
                'periodo:id,aquisitivo_inicio,aquisitivo_fim,dias_saldo,concessivo_fim,situacao',
            ]);
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
                    ->weight(FontWeight::Bold),
                TextColumn::make('periodo.aquisitivo')
                    ->label('Aquisitivo')
                    ->state(fn (RhFeriasGozo $record): string => $record->periodo?->aquisitivoResumo() ?? '—')
                    ->alignCenter(),
                TextColumn::make('gozo_inicio')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->alignCenter(),
                TextColumn::make('gozo_fim')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->alignCenter(),
                TextColumn::make('dias')
                    ->label('Dias')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('situacao_label')
                    ->label('Situação')
                    ->state(fn (RhFeriasGozo $record): string => $record->situacaoLabel())
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('alerta_aviso')
                    ->label('Aviso')
                    ->state(fn (RhFeriasGozo $record): string => $record->alertaAvisoLabel())
                    ->alignCenter()
                    ->weight(fn (RhFeriasGozo $record) => $record->alertaAvisoPrazo() ? FontWeight::Bold : FontWeight::SemiBold),
            ])
            ->defaultSort('gozo_inicio', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum gozo de férias encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhFerias::route('/'),
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
}
