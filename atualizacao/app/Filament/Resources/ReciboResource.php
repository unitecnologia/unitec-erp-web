<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReciboResource\Pages;
use App\Models\Recibo;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReciboResource extends Resource
{
    protected static ?string $model = Recibo::class;

    protected static ?string $slug = 'recibos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'recibo';

    protected static ?string $pluralModelLabel = 'recibos';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('recibos.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('emissao')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::Medium),
                TextColumn::make('recebi_de')
                    ->label('Nominal')
                    ->wrap(false)
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (?string $state): string => mb_strtoupper((string) $state, 'UTF-8')),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->alignEnd()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
            ])
            ->defaultSort('codigo', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum recibo encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecibos::route('/'),
        ];
    }
}
