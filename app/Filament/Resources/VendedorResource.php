<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendedorResource\Pages;
use App\Models\Vendedor;
use App\Support\Erp\ErpTableSort;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendedorResource extends Resource
{
    protected static ?string $model = Vendedor::class;

    protected static ?string $slug = 'vendedores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $modelLabel = 'operador';

    protected static ?string $pluralModelLabel = 'operadores';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        if (\App\Support\Erp\ErpOnboarding::step() === \App\Support\Erp\ErpOnboarding::STEP_COLABORADOR) {
            return true;
        }

        return parent::canAccess();
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
                    ->label('Nome')
                    ->sortable()
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('cargo')
                    ->label('Cargo')
                    ->placeholder('—')
                    ->wrap(false),
                TextColumn::make('empresa_numeros')
                    ->label('Empresa')
                    ->state(fn (Vendedor $record): string => $record->empresasNumeros())
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('ativo')
                    ->label('Ativo')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'S' : 'N')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('comissao_av')
                    ->label('Comissão AV')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('comissao_ap')
                    ->label('Comissão AP')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort(fn (Builder $query, string $direction, $livewire): Builder => ErpTableSort::applyDefaultCodigoNumerico($query, $direction, $livewire))
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum vendedor encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendedores::route('/'),
        ];
    }
}
