<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransportadoraResource\Pages;
use App\Models\Contador;
use App\Models\Transportadora;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpTableSort;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransportadoraResource extends Resource
{
    protected static ?string $model = Transportadora::class;

    protected static ?string $slug = 'transportadoras';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'transportadora';

    protected static ?string $pluralModelLabel = 'transportadoras';

    protected static ?string $recordTitleAttribute = 'proprietario';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('transportadoras.access');
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
                TextColumn::make('proprietario')
                    ->label('Nome/Razão')
                    ->sortable()
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('apelido')
                    ->label('Apelido/Fantasia')
                    ->placeholder('—')
                    ->wrap(false),
                TextColumn::make('cnpj_cpf')
                    ->label('CPF/CNPJ')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? Contador::formatCnpjCpf($state) : '—')
                    ->placeholder('—'),
                TextColumn::make('rg_ie')
                    ->label('RG/IE')
                    ->placeholder('—'),
                TextColumn::make('cidade')
                    ->label('Cidade')
                    ->placeholder('—'),
                TextColumn::make('uf')
                    ->label('UF')
                    ->placeholder('—')
                    ->alignCenter(),
            ])
            ->defaultSort(fn (Builder $query, string $direction, $livewire): Builder => ErpTableSort::applyDefaultCodigoNumerico($query, $direction, $livewire))
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum transportador encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportadoras::route('/'),
        ];
    }
}
