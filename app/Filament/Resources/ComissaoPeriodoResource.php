<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComissaoPeriodoResource\Pages;
use App\Models\ComissaoPeriodo;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ComissaoPeriodoResource extends Resource
{
    protected static ?string $model = ComissaoPeriodo::class;

    protected static ?string $slug = 'comissoes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'comissão';

    protected static ?string $pluralModelLabel = 'comissões';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('comissoes.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('periodo')
                    ->label('Período')
                    ->state(fn (ComissaoPeriodo $r): string => $r->periodo_de?->format('d/m/Y').' – '.$r->periodo_ate?->format('d/m/Y'))
                    ->weight(FontWeight::Medium),
                TextColumn::make('vendedor.nome')
                    ->label('Vendedor')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('status')
                    ->label('Status')
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state): string => ComissaoPeriodo::statusLabels()[$state] ?? (string) $state),
                TextColumn::make('base_avista')
                    ->label('Base AV')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('base_aprazo')
                    ->label('Base AP')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('comissao_avista')
                    ->label('Com. AV')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('comissao_aprazo')
                    ->label('Com. AP')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('comissao_total')
                    ->label('Total')
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('contaPagar.numero')
                    ->label('CAP')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('fechado_em')
                    ->label('Fechado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextColumn::make('fechadoPor.name')
                    ->label('Por')
                    ->placeholder('—'),
            ])
            ->defaultSort('periodo_de', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma comissão encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComissaoPeriodos::route('/'),
        ];
    }
}
