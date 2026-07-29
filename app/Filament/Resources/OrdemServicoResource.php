<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrdemServicoResource\Pages;
use App\Models\OrdemServico;
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

class OrdemServicoResource extends Resource
{
    protected static ?string $model = OrdemServico::class;

    protected static ?string $slug = 'ordens-servico';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $modelLabel = 'ordem de serviço';

    protected static ?string $pluralModelLabel = 'ordens de serviço';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('ordens_servico.access');
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
                    ->formatStateUsing(function (?string $state, OrdemServico $record): string {
                        if (filled($state)) {
                            $digits = (int) preg_replace('/\D/', '', $state);

                            return $digits > 0 ? (string) $digits : $state;
                        }

                        return $record->codigo_legado ? (string) $record->codigo_legado : '—';
                    })
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('data_inicio')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('hora_inicio')
                    ->label('Hora')
                    ->state(fn (OrdemServico $record): string => $record->horaInicioExibicao() ?? '—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->state(fn (OrdemServico $record): string => mb_strtoupper($record->clienteNome(), 'UTF-8'))
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('atendente.nome')
                    ->label('Atendente')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('descricao')
                    ->label('Equipamento')
                    ->placeholder('—')
                    ->limit(40)
                    ->tooltip(fn (OrdemServico $record): ?string => $record->descricao)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('placa')
                    ->label('Placa')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('previsao_entrega')
                    ->label('Previsão')
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                ViewColumn::make('situacao')
                    ->label('Situação')
                    ->view('filament.components.erp.ordens-servico.columns.status')
                    ->alignCenter()
                    ->disabledClick(),
                ViewColumn::make('total_geral')
                    ->label('Total')
                    ->view('filament.components.erp.ordens-servico.columns.total')
                    ->disabledClick(),
            ])
            ->defaultSort('data_inicio', 'desc')
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma ordem de serviço encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdensServico::route('/'),
            'create' => Pages\CreateOrdemServico::route('/create'),
            'edit' => Pages\EditOrdemServico::route('/{record}/edit'),
        ];
    }
}
