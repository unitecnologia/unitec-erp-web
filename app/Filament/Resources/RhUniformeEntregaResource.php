<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhUniformeEntregaResource\Pages;
use App\Models\RhFuncionario;
use App\Models\RhUniforme;
use App\Models\RhUniformeEntrega;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhUniformeEntregaResource extends Resource
{
    protected static ?string $model = RhUniformeEntrega::class;

    protected static ?string $slug = 'rh-uniformes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $modelLabel = 'entrega de uniforme';

    protected static ?string $pluralModelLabel = 'entregas de uniforme';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.uniformes.access');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'funcionario:id,codigo,nome',
                'uniforme:id,codigo,descricao,tipo,tamanho,cor',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('funcionario.nome')
                    ->label('Funcionário')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('uniforme.descricao')
                    ->label('Uniforme')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('uniforme.tamanho')
                    ->label('Tamanho')
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('quantidade')
                    ->label('Quantidade')
                    ->alignCenter(),
                TextColumn::make('entregue_em')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('situacao_label')
                    ->label('Situação')
                    ->state(fn (RhUniformeEntrega $record): string => $record->situacaoLabel())
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
            ->emptyStateHeading('Nenhuma entrega de uniforme encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhUniformeEntregas::route('/'),
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
     * @return Builder<RhUniforme>
     */
    public static function uniformesQuery(bool $somenteAtivos = true): Builder
    {
        $query = RhUniforme::query()->orderBy('descricao')->limit(500);

        return $somenteAtivos ? $query->where('ativo', true) : $query;
    }
}
