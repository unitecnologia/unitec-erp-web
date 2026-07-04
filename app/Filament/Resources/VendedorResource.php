<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendedorResource\Pages;
use App\Models\Vendedor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VendedorResource extends Resource
{
    protected static ?string $model = Vendedor::class;

    protected static ?string $slug = 'vendedores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $modelLabel = 'vendedor';

    protected static ?string $pluralModelLabel = 'vendedores';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('>>Código')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nome')
                    ->label('Nome')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
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
            ->defaultSort('codigo', 'asc')
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
