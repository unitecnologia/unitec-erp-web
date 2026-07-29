<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DevolucaoVendaResource\Pages;
use App\Models\DevolucaoVenda;
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

class DevolucaoVendaResource extends Resource
{
    protected static ?string $model = DevolucaoVenda::class;

    protected static ?string $slug = 'devolucoes-venda';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $modelLabel = 'devolução de venda';

    protected static ?string $pluralModelLabel = 'devoluções de venda';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('devolucoes_venda.access');
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
                    ->formatStateUsing(function (?string $state, DevolucaoVenda $record): string {
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
                TextColumn::make('hora')
                    ->label('Hora')
                    ->state(fn (DevolucaoVenda $record): string => $record->horaExibicao() ?? '—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->state(fn (DevolucaoVenda $record): string => mb_strtoupper($record->clienteNome(), 'UTF-8'))
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('venda_numero')
                    ->label('Venda')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('vendedor.nome')
                    ->label('Vendedor')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('tipo_devolucao')
                    ->label('Tipo')
                    ->state(fn (DevolucaoVenda $record): string => mb_strtoupper($record->tipoLabel(), 'UTF-8'))
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                ViewColumn::make('situacao')
                    ->label('Situação')
                    ->view('filament.components.erp.devolucoes-venda.columns.status')
                    ->alignCenter()
                    ->disabledClick(),
                ViewColumn::make('total')
                    ->label('Total')
                    ->view('filament.components.erp.devolucoes-venda.columns.total')
                    ->disabledClick(),
            ])
            ->defaultSort('data', 'desc')
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma devolução de venda encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevolucoesVenda::route('/'),
            'create' => Pages\CreateDevolucaoVenda::route('/create'),
            'edit' => Pages\EditDevolucaoVenda::route('/{record}/edit'),
        ];
    }
}
