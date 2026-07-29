<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VeiculoResource\Pages;
use App\Models\Veiculo;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VeiculoResource extends Resource
{
    protected static ?string $model = Veiculo::class;

    protected static ?string $slug = 'veiculos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'veículo';

    protected static ?string $pluralModelLabel = 'veículos';

    protected static ?string $recordTitleAttribute = 'placa';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('veiculos.access');
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
                TextColumn::make('renavam')
                    ->label('Renavam')
                    ->placeholder('—'),
                TextColumn::make('rntc')
                    ->label('RNTC')
                    ->placeholder('—'),
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
            'index' => Pages\ListVeiculos::route('/'),
        ];
    }
}
