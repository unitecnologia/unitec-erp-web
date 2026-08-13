<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TomadorServicoResource\Pages;
use App\Models\TomadorServico;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpTableSort;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TomadorServicoResource extends Resource
{
    protected static ?string $model = TomadorServico::class;

    protected static ?string $slug = 'tomadores-servico';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $modelLabel = 'tomador de serviço';

    protected static ?string $pluralModelLabel = 'tomadores de serviço';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('tomadores_servico.access');
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
                    ->label('Tomador')
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
            ->emptyStateHeading('Nenhum tomador de serviço encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTomadoresServico::route('/'),
        ];
    }
}
