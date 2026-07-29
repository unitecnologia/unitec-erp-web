<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanoContaResource\Pages;
use App\Models\PlanoConta;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanoContaResource extends Resource
{
    protected static ?string $model = PlanoConta::class;

    protected static ?string $slug = 'planos-contas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $modelLabel = 'plano de contas';

    protected static ?string $pluralModelLabel = 'planos de contas';

    protected static ?string $recordTitleAttribute = 'descricao';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('planos_contas.access');
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
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->wrap(false)
                    ->weight(FontWeight::Bold)
                    ->formatStateUsing(fn (?string $state): string => mb_strtoupper((string) $state, 'UTF-8')),
                TextColumn::make('dc')
                    ->label('Tipo')
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'D' => 'D',
                        'C' => 'C',
                        default => '—',
                    })
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
            ->emptyStateHeading('Nenhum plano de contas encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlanoContas::route('/'),
        ];
    }
}
