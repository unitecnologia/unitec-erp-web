<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OsVeiculoResource\Pages;
use App\Models\OsVeiculo;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OsVeiculoResource extends Resource
{
    protected static ?string $model = OsVeiculo::class;

    protected static ?string $slug = 'os-veiculos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'veículo';

    protected static ?string $pluralModelLabel = 'veículos';

    protected static ?string $recordTitleAttribute = 'placa';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('ordens_servico.access');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $empresaId = ErpContext::currentEmpresaId();

        if (! $empresaId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('empresa_id', $empresaId);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('placa')
                    ->label('Placa')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->wrap(false)
                    ->placeholder('—')
                    ->weight(FontWeight::Bold),
                TextColumn::make('modelo')
                    ->label('Modelo')
                    ->placeholder('—'),
                TextColumn::make('ano_fabricacao')
                    ->label('Ano')
                    ->alignCenter()
                    ->placeholder('—')
                    ->getStateUsing(fn (OsVeiculo $record): string => $record->anoLista()),
                TextColumn::make('cor')
                    ->label('Cor')
                    ->placeholder('—'),
                TextColumn::make('combustivel')
                    ->label('Combustível')
                    ->placeholder('—'),
                TextColumn::make('nacionalidade')
                    ->label('Nacionalidade')
                    ->placeholder('—'),
                TextColumn::make('cidade')
                    ->label('Cidade')
                    ->placeholder('—'),
                TextColumn::make('uf')
                    ->label('UF')
                    ->alignCenter()
                    ->placeholder('—'),
                ViewColumn::make('historico')
                    ->label('Histórico')
                    ->state(fn (): bool => true)
                    ->width('5.5rem')
                    ->view('filament.components.erp.os-veiculos.columns.historico')
                    ->alignCenter()
                    ->disabledClick(),
            ])
            ->defaultSort('placa', 'asc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum veículo encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOsVeiculos::route('/'),
        ];
    }
}
