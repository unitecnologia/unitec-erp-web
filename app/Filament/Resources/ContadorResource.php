<?php

namespace App\Filament\Resources;

use App\Support\Erp\ErpAccess;
use App\Filament\Resources\ContadorResource\Pages;
use App\Models\Contador;
use App\Support\Erp\ErpTableSort;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContadorResource extends Resource
{
    protected static ?string $model = Contador::class;

    protected static ?string $slug = 'contadores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $modelLabel = 'contador';

    protected static ?string $pluralModelLabel = 'contadores';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('contadores.access');
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
                TextColumn::make('cnpj_cpf')
                    ->label('CNPJ/CPF')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? Contador::formatCnpjCpf($state) : '—')
                    ->placeholder('—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cidade')
                    ->label('Cidade')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('uf')
                    ->label('UF')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('fone')
                    ->label('Fone')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? Contador::formatFone($state) : '—')
                    ->placeholder('—')
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
            ->emptyStateHeading('Nenhum contador encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContadores::route('/'),
        ];
    }
}
