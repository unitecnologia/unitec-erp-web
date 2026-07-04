<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FormaPagamentoResource\Pages;
use App\Models\FormaPagamento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FormaPagamentoResource extends Resource
{
    protected static ?string $model = FormaPagamento::class;

    protected static ?string $slug = 'formas-pagamento';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'forma de pagamento';

    protected static ?string $pluralModelLabel = 'formas de pagamento';

    protected static ?string $recordTitleAttribute = 'descricao';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => FormaPagamento::tipoLabels()[$state] ?? ($state ? mb_strtoupper($state) : '—')),
                TextColumn::make('ativo')
                    ->label('Ativo')
                    ->alignCenter()
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sim' : 'Não')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->defaultSort('codigo', 'asc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma forma de pagamento encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormasPagamento::route('/'),
        ];
    }
}
