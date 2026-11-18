<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhBeneficioResource\Pages;
use App\Models\RhBeneficio;
use App\Models\RhBeneficioVinculo;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhBeneficioResource extends Resource
{
    protected static ?string $model = RhBeneficioVinculo::class;

    protected static ?string $slug = 'rh-beneficios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $modelLabel = 'vínculo de benefício';

    protected static ?string $pluralModelLabel = 'benefícios';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.beneficios.access');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'funcionario:id,codigo,nome',
                'beneficio:id,codigo,descricao,tipo',
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
                TextColumn::make('beneficio.descricao')
                    ->label('Benefício')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('beneficio_tipo')
                    ->label('Tipo')
                    ->state(fn (RhBeneficioVinculo $record): string => $record->beneficio?->tipoLabel() ?? '—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->state(fn (RhBeneficioVinculo $record): string => self::formatMoney($record->valor))
                    ->alignEnd(),
                TextColumn::make('participacao_funcionario')
                    ->label('Part. func.')
                    ->state(fn (RhBeneficioVinculo $record): string => $record->participacao_funcionario !== null
                        ? self::formatMoney($record->participacao_funcionario)
                        : '—')
                    ->alignEnd(),
                TextColumn::make('inicio_em')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('termino_em')
                    ->label('Término')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('situacao_label')
                    ->label('Situação')
                    ->state(fn (RhBeneficioVinculo $record): string => $record->situacaoLabel())
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
            ->emptyStateHeading('Nenhum vínculo de benefício encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhBeneficioVinculos::route('/'),
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
     * @return Builder<RhBeneficio>
     */
    public static function beneficiosQuery(bool $somenteAtivos = true): Builder
    {
        $query = RhBeneficio::query()->orderBy('descricao')->limit(500);

        return $somenteAtivos ? $query->where('ativo', true) : $query;
    }

    private static function formatMoney(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, 2, ',', '.');
    }
}
