<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhExameResource\Pages;
use App\Models\RhExame;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhExameResource extends Resource
{
    protected static ?string $model = RhExame::class;

    protected static ?string $slug = 'rh-exames';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $modelLabel = 'exame / ASO';

    protected static ?string $pluralModelLabel = 'exames / ASO';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.exames.access');
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
                TextColumn::make('tipo_label')
                    ->label('Tipo')
                    ->state(fn (RhExame $record): string => $record->tipoLabel())
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('realizado_em')
                    ->label('Data exame')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('proximo_em')
                    ->label('Próximo exame')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('situacao_label')
                    ->label('Situação')
                    ->state(fn (RhExame $record): string => $record->situacaoPrazoLabel())
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
            ->emptyStateHeading('Nenhum exame encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhExames::route('/'),
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
