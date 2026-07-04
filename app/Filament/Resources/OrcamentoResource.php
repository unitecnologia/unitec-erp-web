<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrcamentoResource\Pages;
use App\Models\Orcamento;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class OrcamentoResource extends Resource
{
    protected static ?string $model = Orcamento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'orçamento';

    protected static ?string $pluralModelLabel = 'orçamentos';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('numero')->hidden()->dehydratedWhenHidden(),
            DatePicker::make('data')->hidden()->dehydratedWhenHidden(),
            TextInput::make('status')->hidden()->dehydratedWhenHidden(),
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
                    ->formatStateUsing(function (?string $state): string {
                        if (blank($state)) {
                            return '';
                        }

                        $digits = (int) preg_replace('/\D/', '', $state);

                        return $digits > 0 ? (string) $digits : $state;
                    })
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente.nome_razao')
                    ->label('Cliente')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('vendedor.nome')
                    ->label('Vendedor')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente.cidade_nome')
                    ->label('Cidade')
                    ->placeholder('—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente.uf')
                    ->label('UF')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold),
                ViewColumn::make('ver_itens')
                    ->label('')
                    ->state(fn (): bool => true)
                    ->width('1.35rem')
                    ->view('filament.components.erp.orcamentos.columns.ver-itens')
                    ->alignCenter()
                    ->disabledClick(),
            ])
            ->defaultSort('numero', 'desc')
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum orçamento encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrcamentos::route('/'),
            'create' => Pages\CreateOrcamento::route('/create'),
            'edit' => Pages\EditOrcamento::route('/{record}/edit'),
        ];
    }
}
