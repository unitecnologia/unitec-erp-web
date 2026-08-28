<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromocaoResource\Pages;
use App\Models\Promocao;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromocaoResource extends Resource
{
    protected static ?string $model = Promocao::class;

    protected static ?string $slug = 'promocoes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'promoção';

    protected static ?string $pluralModelLabel = 'promoções';

    protected static ?string $recordTitleAttribute = 'descricao';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('promocoes.access');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('empresa');
        $empresaId = ErpContext::currentEmpresaId();

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->wrap(false)
                    ->weight(FontWeight::Bold)
                    ->searchable(),
                TextColumn::make('data_inicio')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->alignCenter(),
                TextColumn::make('data_fim')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->alignCenter(),
                TextColumn::make('empresa.nome')
                    ->label('Empresa')
                    ->wrap(false),
                IconColumn::make('ativa')
                    ->label('Ativa')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('data_inicio', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma promoção cadastrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromocoes::route('/'),
        ];
    }
}
