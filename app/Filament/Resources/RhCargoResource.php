<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhCargoResource\Pages;
use App\Models\RhCargo;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpTableSort;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhCargoResource extends Resource
{
    protected static ?string $model = RhCargo::class;

    protected static ?string $slug = 'rh-cargos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $modelLabel = 'cargo';

    protected static ?string $pluralModelLabel = 'cargos';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.cargos.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => ErpTableSort::orderByCodigoNumerico($query, $direction))
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nome')
                    ->label('Cargo')
                    ->sortable()
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
            ])
            ->defaultSort(fn (Builder $query, string $direction, $livewire): Builder => ErpTableSort::applyDefaultCodigoNumerico($query, $direction, $livewire))
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum cargo encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhCargos::route('/'),
        ];
    }
}
