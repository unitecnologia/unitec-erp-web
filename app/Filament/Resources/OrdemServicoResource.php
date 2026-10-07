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
                    ->extraHeaderAttributes(self::colunaOs('numero'))
                    ->extraCellAttributes(self::colunaOs('numero'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nfseAutorizada.numero_nfse')
                    ->label('NFS-e')
                    ->state(fn (OrdemServico $record): string => $record->nfseNumeroLista())
                    ->alignCenter()
                    ->placeholder('—')
                    ->extraHeaderAttributes(self::colunaOs('nfse'))
                    ->extraCellAttributes(self::colunaOs('nfse'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nfeAutorizada.numero')
                    ->label('NF-e')
                    ->state(fn (OrdemServico $record): string => $record->nfeNumeroLista())
                    ->alignCenter()
                    ->placeholder('—')
                    ->extraHeaderAttributes(self::colunaOs('nfe'))
                    ->extraCellAttributes(self::colunaOs('nfe'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nfce_numero')
                    ->label('NFC-e')
                    ->state(fn (OrdemServico $record): string => $record->nfceNumeroLista())
                    ->alignCenter()
                    ->placeholder('—')
                    ->extraHeaderAttributes(self::colunaOs('nfce'))
                    ->extraCellAttributes(self::colunaOs('nfce'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('data_inicio')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->extraHeaderAttributes(self::colunaOs('data'))
                    ->extraCellAttributes(self::colunaOs('data'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('hora_inicio')
                    ->label('H.Aber.')
                    ->state(fn (OrdemServico $record): string => $record->horaInicioExibicao() ?? '—')
                    ->alignCenter()
                    ->extraHeaderAttributes(self::colunaOs('hora-abertura'))
                    ->extraCellAttributes(self::colunaOs('hora-abertura'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('hora_termino')
                    ->label('H.Fech.')
                    ->state(fn (OrdemServico $record): string => $record->horaTerminoExibicao() ?? '—')
                    ->alignCenter()
                    ->extraHeaderAttributes(self::colunaOs('hora-fechamento'))
                    ->extraCellAttributes(self::colunaOs('hora-fechamento'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->state(fn (OrdemServico $record): string => mb_strtoupper($record->clienteNome(), 'UTF-8'))
                    ->wrap(false)
                    ->extraHeaderAttributes(self::colunaOs('cliente'))
                    ->extraCellAttributes(self::colunaOs('cliente'))
                    ->weight(FontWeight::Bold),
                TextColumn::make('atendente.nome')
                    ->label('Atendente')
                    ->placeholder('—')
                    ->wrap(false)
                    ->extraHeaderAttributes(self::colunaOs('atendente'))
                    ->extraCellAttributes(self::colunaOs('atendente'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('descricao')
                    ->label('Equipamento')
                    ->placeholder('—')
                    ->limit(40)
                    ->tooltip(fn (OrdemServico $record): ?string => $record->descricao)
                    ->extraHeaderAttributes(self::colunaOs('equipamento'))
                    ->extraCellAttributes(self::colunaOs('equipamento'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('placa')
                    ->label('Placa')
                    ->placeholder('—')
                    ->alignCenter()
                    ->extraHeaderAttributes(self::colunaOs('placa'))
                    ->extraCellAttributes(self::colunaOs('placa'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('meio_pagamento')
                    ->label('Meio de Pag.')
                    ->state(fn (OrdemServico $record): string => $record->meioPagamentoLista())
                    ->placeholder('—')
                    ->wrap(false)
                    ->tooltip(fn (OrdemServico $record): ?string => ($t = $record->meioPagamentoLista()) !== '—' ? $t : null)
                    ->extraHeaderAttributes(self::colunaOs('meio-pagamento'))
                    ->extraCellAttributes(self::colunaOs('meio-pagamento'))
                    ->weight(FontWeight::SemiBold),
                ViewColumn::make('envio')
                    ->label('Env.')
                    ->view('filament.components.erp.ordens-servico.columns.envio')
                    ->alignCenter()
                    ->extraHeaderAttributes(self::colunaOs('envio'))
                    ->extraCellAttributes(self::colunaOs('envio'))
                    ->disabledClick(),
                ViewColumn::make('situacao')
                    ->label('Situação')
                    ->view('filament.components.erp.ordens-servico.columns.status')
                    ->alignCenter()
                    ->extraHeaderAttributes(self::colunaOs('situacao'))
                    ->extraCellAttributes(self::colunaOs('situacao'))
                    ->disabledClick(),
                ViewColumn::make('desconto_total')
                    ->label('Desconto')
                    ->view('filament.components.erp.ordens-servico.columns.desconto')
                    ->extraHeaderAttributes(self::colunaOs('desconto'))
                    ->extraCellAttributes(self::colunaOs('desconto'))
                    ->disabledClick(),
                ViewColumn::make('total_geral')
                    ->label('Total')
                    ->view('filament.components.erp.ordens-servico.columns.total')
                    ->extraHeaderAttributes(self::colunaOs('total'))
                    ->extraCellAttributes(self::colunaOs('total'))
                    ->disabledClick(),
                ViewColumn::make('ver_espelho')
                    ->label('')
                    ->state(fn (): bool => true)
                    ->view('filament.components.erp.ordens-servico.columns.ver-espelho')
                    ->alignCenter()
                    ->extraHeaderAttributes(self::colunaOs('espelho'))
                    ->extraCellAttributes(self::colunaOs('espelho'))
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

    /**
     * Largura/alinhamento da coluna vêm de erp-ordens-servico.css por esta classe, nunca pela posição.
     *
     * @return array{class: string}
     */
    private static function colunaOs(string $nome): array
    {
        return ['class' => 'erp-os-col erp-os-col-'.$nome];
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
