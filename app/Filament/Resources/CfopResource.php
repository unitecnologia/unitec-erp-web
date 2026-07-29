<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CfopResource\Pages;
use App\Models\Cfop;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CfopResource extends Resource
{
    protected static ?string $model = Cfop::class;

    protected static ?string $slug = 'cfops';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'CFOP';

    protected static ?string $pluralModelLabel = 'CFOPs';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('cfops.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::Medium),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->wrap(false)
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (?string $state): string => mb_strtoupper((string) $state, 'UTF-8')),
            ])
            ->defaultSort('codigo', 'asc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum CFOP encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCfops::route('/'),
        ];
    }
}
