<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CargaResource\Pages;
use App\Models\Carga;
use App\Models\Transportadora;
use App\Models\User;
use App\Models\Veiculo;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpTableSort;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CargaResource extends Resource
{
    protected static ?string $model = Carga::class;

    protected static ?string $slug = 'cargas-romaneio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'carga';

    protected static ?string $pluralModelLabel = 'cargas';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('cargas.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('selecionar')
                    ->label('')
                    ->view('filament.components.erp.cargas.select-cell')
                    ->alignCenter(),
                TextColumn::make('numero')
                    ->label('Nº')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => ErpTableSort::orderByCodigoNumerico(
                        $query,
                        $direction,
                        'numero',
                        desempateIdDesc: true,
                    )),
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::orderByColumn(
                        $query,
                        'data',
                        $direction,
                    )),
                TextColumn::make('motorista.proprietario')
                    ->label('Motorista')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::Bold)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::orderByRelationColumn(
                        $query,
                        Transportadora::query()->getModel()->getTable(),
                        'proprietario',
                        'motorista_id',
                        $direction,
                    )),
                TextColumn::make('entregador.name')
                    ->label('Entregador (App)')
                    ->placeholder('—')
                    ->wrap(false)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::orderByRelationColumn(
                        $query,
                        (new User)->getTable(),
                        'name',
                        'entregador_user_id',
                        $direction,
                    )),
                TextColumn::make('veiculo.placa')
                    ->label('Veículo')
                    ->placeholder('—')
                    ->formatStateUsing(function (?string $state, Carga $record): string {
                        if (blank($state)) {
                            return '—';
                        }

                        $descricao = trim((string) ($record->veiculo?->descricao ?? ''));

                        return $descricao !== '' ? "{$state} — {$descricao}" : $state;
                    })
                    ->wrap(false)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::orderByRelationColumn(
                        $query,
                        (new Veiculo)->getTable(),
                        'placa',
                        'veiculo_id',
                        $direction,
                    )),
                TextColumn::make('pedidos_count')
                    ->label('Qtd. pedidos')
                    ->alignCenter()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::orderByColumn(
                        $query,
                        'pedidos_count',
                        $direction,
                    )),
                TextColumn::make('valor_total')
                    ->label('Valor total')
                    ->state(fn (Carga $record): float => (float) ($record->pedidos_sum_total ?? 0))
                    ->money('BRL', locale: 'pt_BR')
                    ->alignEnd()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::orderByColumn(
                        $query,
                        'pedidos_sum_total',
                        $direction,
                    )),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => Carga::statusLabels()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Carga::STATUS_ABERTA => 'info',
                        Carga::STATUS_FECHADA => 'success',
                        Carga::STATUS_CANCELADA => 'danger',
                        default => 'gray',
                    })
                    ->alignCenter()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::orderByStatus(
                        $query,
                        $direction,
                    )),
            ])
            ->defaultSort(
                fn (Builder $query, string $direction, $livewire): Builder => ErpTableSort::applyDefaultCodigoNumerico(
                    $query,
                    $direction,
                    $livewire,
                    'numero',
                    desempateIdDesc: true,
                ),
                'desc',
            )
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma carga encontrada');
    }

    /**
     * @param  Builder<Carga>  $query
     * @return Builder<Carga>
     */
    public static function orderByColumn(Builder $query, string $column, string $direction): Builder
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $table = $query->getModel()->getTable();

        // Aggregates (withCount/withSum) não usam prefixo da tabela principal.
        if (in_array($column, ['pedidos_count', 'pedidos_sum_total'], true)) {
            return $query->orderBy($column, $dir)->orderByDesc("{$table}.id");
        }

        return $query->orderBy("{$table}.{$column}", $dir)->orderByDesc("{$table}.id");
    }

    /**
     * @param  Builder<Carga>  $query
     * @return Builder<Carga>
     */
    public static function orderByRelationColumn(
        Builder $query,
        string $relatedTable,
        string $relatedColumn,
        string $foreignKey,
        string $direction,
    ): Builder {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $table = $query->getModel()->getTable();

        return $query
            ->orderBy(
                \Illuminate\Support\Facades\DB::table($relatedTable)
                    ->select($relatedColumn)
                    ->whereColumn("{$relatedTable}.id", "{$table}.{$foreignKey}")
                    ->limit(1),
                $dir,
            )
            ->orderByDesc("{$table}.id");
    }

    /**
     * @param  Builder<Carga>  $query
     * @return Builder<Carga>
     */
    public static function orderByStatus(Builder $query, string $direction): Builder
    {
        $asc = [Carga::STATUS_ABERTA, Carga::STATUS_FECHADA, Carga::STATUS_CANCELADA];
        $order = strtolower($direction) === 'desc' ? array_reverse($asc) : $asc;
        $table = $query->getModel()->getTable();
        $prefixed = $query->getConnection()->getTablePrefix().$table;
        $placeholders = implode(', ', array_fill(0, count($order), '?'));

        return $query
            ->orderByRaw('FIELD(`'.$prefixed.'`.`status`, '.$placeholders.')', $order)
            ->orderByDesc("{$table}.id");
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCargas::route('/'),
        ];
    }
}
