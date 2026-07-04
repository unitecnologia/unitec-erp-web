<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContadorResource\Pages;
use App\Models\Contador;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContadorResource extends Resource
{
    protected static ?string $model = Contador::class;

    protected static ?string $slug = 'contadores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $modelLabel = 'contador';

    protected static ?string $pluralModelLabel = 'contadores';

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
            ->defaultSort('codigo', 'asc')
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
