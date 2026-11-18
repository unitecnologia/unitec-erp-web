<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhHoleriteResource\Pages;
use App\Models\RhFuncionario;
use App\Models\RhHolerite;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhHoleriteResource extends Resource
{
    protected static ?string $model = RhHolerite::class;

    protected static ?string $slug = 'rh-holerites';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'holerite';

    protected static ?string $pluralModelLabel = 'holerites';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.holerites.access');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['funcionario:id,codigo,nome']);
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
                TextColumn::make('competencia')
                    ->label('Competência')
                    ->state(fn (RhHolerite $record): string => $record->competenciaLabel())
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pago_em')
                    ->label('Pago em')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->state(fn (RhHolerite $record): string => $record->situacaoLabel())
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
            ->emptyStateHeading('Nenhum holerite encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhHolerites::route('/'),
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
