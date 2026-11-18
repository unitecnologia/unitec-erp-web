<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhDocumentoResource\Pages;
use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhDocumentoResource extends Resource
{
    protected static ?string $model = RhAnexo::class;

    protected static ?string $slug = 'rh-documentos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'documentos';

    protected static ?string $recordTitleAttribute = 'titulo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.documentos.access');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->documentos()
            ->with(['anexavel:id,codigo,nome']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Cód.')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('anexavel.codigo')
                    ->label('Func.')
                    ->alignCenter()
                    ->placeholder('—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('anexavel.nome')
                    ->label('Funcionário')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('titulo')
                    ->label('Documento')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('emitido_em')
                    ->label('Emitido')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('valido_ate')
                    ->label('Válido até')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->state(fn (RhAnexo $record): string => $record->situacaoValidadeLabel())
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum documento encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhDocumentos::route('/'),
        ];
    }

    /**
     * @return Builder<RhFuncionario>
     */
    public static function funcionariosQuery(): Builder
    {
        return RhFuncionario::query()
            ->where('ativo', true)
            ->whereNull('data_demissao')
            ->orderBy('nome')
            ->limit(500);
    }

    public static function validadeVencida(?Carbon $data): bool
    {
        return $data instanceof Carbon && $data->lt(Carbon::today());
    }
}
