<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhFuncionarioResource\Pages;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTableSort;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhFuncionarioResource extends Resource
{
    protected static ?string $model = RhFuncionario::class;

    protected static ?string $slug = 'rh-funcionarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'funcionário';

    protected static ?string $pluralModelLabel = 'funcionários';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.funcionarios.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => ErpTableSort::orderByCodigoNumerico($query, $direction))
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nome')
                    ->label('Nome')
                    ->sortable()
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('cargo.nome')
                    ->label('Cargo')
                    ->placeholder('—'),
                TextColumn::make('departamento.nome')
                    ->label('Departamento')
                    ->placeholder('—'),
                TextColumn::make('data_admissao')
                    ->label('Admissão')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('salario')
                    ->label('Salário')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null ? 'R$ '.ErpMoney::formatBr((float) $state) : '—'),
            ])
            ->defaultSort(fn (Builder $query, string $direction, $livewire): Builder => ErpTableSort::applyDefaultCodigoNumerico($query, $direction, $livewire))
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum funcionário encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhFuncionarios::route('/'),
        ];
    }
}
