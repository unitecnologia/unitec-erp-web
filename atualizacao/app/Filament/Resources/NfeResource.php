<?php

namespace App\Filament\Resources;

use App\Support\Erp\ErpAccess;
use App\Filament\Resources\NfeResource\Pages;
use App\Models\Nfe;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
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

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('nfe.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('selecao')
                    ->label('')
                    ->view('filament.components.erp.nfe.select-cell')
                    ->alignCenter()
                    ->width('2.25rem')
                    ->disabledClick(),
                TextColumn::make('numero')
                    ->label('Número')
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
                    ->wrap(false)
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('finalidade')
                    ->label('Finalidade')
                    ->formatStateUsing(fn (?string $state): string => Nfe::finalidadeLabel($state))
                    ->placeholder('NORMAL')
                    ->wrap(false)
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                ViewColumn::make('status')
                    ->label('Situação')
                    ->view('filament.components.erp.nfe.columns.status')
                    ->alignCenter()
                    ->disabledClick(),
                ViewColumn::make('total')
                    ->label('Total')
                    ->view('filament.components.erp.nfe.columns.total')
                    ->alignEnd()
                    ->disabledClick(),
                ViewColumn::make('historico')
                    ->label('')
                    ->state(fn (): bool => true)
                    ->width('1.35rem')
                    ->view('filament.components.erp.nfe.columns.historico')
                    ->alignCenter()
                    ->disabledClick(),
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
