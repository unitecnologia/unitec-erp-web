<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhEscalaResource\Pages;
use App\Models\RhEscalaVinculo;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhEscalaResource extends Resource
{
    protected static ?string $model = RhEscalaVinculo::class;

    protected static ?string $slug = 'rh-escalas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'vínculo de escala';

    protected static ?string $pluralModelLabel = 'vínculos de escala';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.escalas.access');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'funcionario:id,codigo,nome',
                'escala:id,codigo,nome,tipo,hora_entrada,hora_saida',
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
                TextColumn::make('escala.nome')
                    ->label('Escala')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('escala.tipo_label')
                    ->label('Tipo')
                    ->state(fn (RhEscalaVinculo $record): string => $record->escala?->tipoLabel() ?? '—')
                    ->alignCenter(),
                TextColumn::make('inicio_em')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->alignCenter(),
                TextColumn::make('termino_em')
                    ->label('Término')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('situacao_label')
                    ->label('Situação')
                    ->state(fn (RhEscalaVinculo $record): string => $record->ativo ? 'Ativo' : 'Encerrado')
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
            ->emptyStateHeading('Nenhum vínculo de escala encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhEscalas::route('/'),
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
