<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhEpiEntregaResource\Pages;
use App\Models\RhEpi;
use App\Models\RhEpiEntrega;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhEpiEntregaResource extends Resource
{
    protected static ?string $model = RhEpiEntrega::class;

    protected static ?string $slug = 'rh-epis';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'entrega de EPI';

    protected static ?string $pluralModelLabel = 'entregas de EPI';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.epis.access');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'funcionario:id,codigo,nome',
                'epi:id,codigo,descricao,ca_numero',
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
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('epi.descricao')
                    ->label('EPI')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('epi.ca_numero')
                    ->label('CA')
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('entregue_em')
                    ->label('Entrega')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('previsao_troca')
                    ->label('Próx. troca')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('situacao_label')
                    ->label('Situação')
                    ->state(fn (RhEpiEntrega $record): string => $record->situacaoLabel())
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma entrega de EPI encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhEpiEntregas::route('/'),
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
     * @return Builder<RhEpi>
     */
    public static function episQuery(bool $somenteAtivos = true): Builder
    {
        $query = RhEpi::query()->orderBy('descricao')->limit(500);

        return $somenteAtivos ? $query->where('ativo', true) : $query;
    }
}
