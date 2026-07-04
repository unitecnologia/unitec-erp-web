<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NfeResource\Pages;
use App\Models\Nfe;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NfeResource extends Resource
{
    protected static ?string $model = Nfe::class;

    protected static ?string $slug = 'nfe';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static ?string $modelLabel = 'NF-e';

    protected static ?string $pluralModelLabel = 'NF-e';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')
                    ->label('>>Número')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('data_emissao')
                    ->label('Dt.Emissão')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('data_saida')
                    ->label('Dt.Saída')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente.nome_razao')
                    ->label('Cliente')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('chave')
                    ->label('Chave')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('protocolo')
                    ->label('Protocolo')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('numero', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma NF-e encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNfes::route('/'),
        ];
    }
}
