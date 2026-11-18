<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RhOcorrenciaResource\Pages;
use App\Models\RhFuncionario;
use App\Models\RhOcorrencia;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhOcorrenciaService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RhOcorrenciaResource extends Resource
{
    protected static ?string $model = RhOcorrencia::class;

    protected static ?string $slug = 'rh-ocorrencias';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'ocorrência';

    protected static ?string $pluralModelLabel = 'ocorrências';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.ocorrencias.access');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['funcionario:id,codigo,nome']);
    }

    public static function table(Table $table): Table
    {
        $podeVerRestrito = RhOcorrenciaService::podeVerConteudoRestrito();

        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Cód.')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('funcionario.nome')
                    ->label('Funcionário')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('tipo_label')
                    ->label('Tipo')
                    ->state(fn (RhOcorrencia $record): string => $record->tipoLabel())
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('ocorrido_em')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('descricao_resumo')
                    ->label('Descrição')
                    ->state(fn (RhOcorrencia $record): string => $record->descricaoResumo(podeVerRestrito: $podeVerRestrito))
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('situacao_label')
                    ->label('Situação')
                    ->state(fn (RhOcorrencia $record): string => $record->situacaoLabel())
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
            ->emptyStateHeading('Nenhuma ocorrência encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRhOcorrencias::route('/'),
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
}
