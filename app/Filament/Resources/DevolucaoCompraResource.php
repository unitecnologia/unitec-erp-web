<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DevolucaoCompraResource\Pages;
use App\Models\DevolucaoCompra;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class DevolucaoCompraResource extends Resource
{
    protected static ?string $model = DevolucaoCompra::class;

    protected static ?string $slug = 'devolucoes-compra';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $modelLabel = 'devolução de compra';

    protected static ?string $pluralModelLabel = 'devoluções de compra';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('devolucoes_compra.access');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('numero')->hidden()->dehydratedWhenHidden(),
            TextInput::make('situacao')->hidden()->dehydratedWhenHidden(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')
                    ->label('Número')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(function (?string $state, DevolucaoCompra $record): string {
                        if (filled($state)) {
                            $digits = (int) preg_replace('/\D/', '', $state);

                            return $digits > 0 ? (string) $digits : $state;
                        }

                        return $record->codigo_legado ? (string) $record->codigo_legado : '—';
                    })
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('compra_numero')
                    ->label('Compra')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('fornecedor_nome')
                    ->label('Fornecedor')
                    ->state(fn (DevolucaoCompra $record): string => mb_strtoupper($record->fornecedorNome(), 'UTF-8'))
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                ViewColumn::make('situacao')
                    ->label('Situação')
                    ->view('filament.components.erp.devolucoes-compra.columns.status')
                    ->alignCenter()
                    ->disabledClick(),
                ViewColumn::make('total')
                    ->label('Total')
                    ->view('filament.components.erp.devolucoes-compra.columns.total')
                    ->disabledClick(),
            ])
            ->defaultSort('data', 'desc')
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma devolução de compra encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevolucoesCompra::route('/'),
        ];
    }
}
