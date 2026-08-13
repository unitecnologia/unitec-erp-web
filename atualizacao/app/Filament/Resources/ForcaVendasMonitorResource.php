<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ForcaVendasMonitorResource\Pages;
use App\Models\ForcaVendasOrder;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpTimezone;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class ForcaVendasMonitorResource extends Resource
{
    protected static ?string $model = ForcaVendasOrder::class;

    protected static ?string $slug = 'forca-vendas-monitor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'pedido';

    protected static ?string $pluralModelLabel = 'pedidos';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('vendas.access') || ErpAccess::currentCan('forca_vendas.access');
    }

    /**
     * O Monitor de Vendas mostra apenas os documentos do tipo "pedido"
     * (os do tipo "orcamento" vão para a tela "Orçamentos recebidos").
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'pedido');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('selecionar')
                    ->label('')
                    ->view('filament.components.erp.forca-vendas.monitor-select-cell')
                    ->alignCenter(),
                TextColumn::make('numero_pedido')
                    ->label('Nº DAV')
                    ->state(fn (ForcaVendasOrder $record): string => $record->orcamento?->numero
                        ? (string) (int) preg_replace('/\D/', '', (string) $record->orcamento->numero)
                        : '#' . $record->id)
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('situacao')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ForcaVendasOrder $record): string => $record->situacaoLabel())
                    ->color(fn (ForcaVendasOrder $record): string => $record->situacaoColor())
                    ->alignCenter(),
                TextColumn::make('cliente')
                    ->label('Cliente')
                    ->grow()
                    ->wrap(false)
                    ->weight(FontWeight::Bold)
                    ->state(fn (ForcaVendasOrder $record): string => $record->clienteNome()),
                TextColumn::make('vendedor')
                    ->label('Vendedor')
                    ->wrap(false)
                    ->state(fn (ForcaVendasOrder $record): string => $record->vendedor?->nome
                        ?? $record->user?->name
                        ?? '—'),
                TextColumn::make('data_abert')
                    ->label('Data Abert.')
                    ->state(fn (ForcaVendasOrder $record): string => $record->dataAberturaAt() ? ErpTimezone::toLocal($record->dataAberturaAt())->format('d/m/Y') : '—')
                    ->alignCenter(),
                TextColumn::make('hora_abert')
                    ->label('Hora Abert.')
                    ->state(fn (ForcaVendasOrder $record): string => $record->dataAberturaAt() ? ErpTimezone::toLocal($record->dataAberturaAt())->format('H:i') : '—')
                    ->alignCenter(),
                TextColumn::make('data_fech')
                    ->label('Data Fech.')
                    ->state(function (ForcaVendasOrder $record): string {
                        $fech = $record->faturado_at ?? $record->confirmed_at;

                        return $fech ? ErpTimezone::toLocal($fech)->format('d/m/Y') : '—';
                    })
                    ->alignCenter(),
                TextColumn::make('hora_fech')
                    ->label('Hora Fech.')
                    ->state(function (ForcaVendasOrder $record): string {
                        $fech = $record->faturado_at ?? $record->confirmed_at;

                        return $fech ? ErpTimezone::toLocal($fech)->format('H:i') : '—';
                    })
                    ->alignCenter(),
                TextColumn::make('sincronizado')
                    ->label('Sincronizado')
                    ->state(fn (ForcaVendasOrder $record): string => $record->received_at ? ErpTimezone::toLocal($record->received_at)->format('d/m/Y H:i') : '—')
                    ->alignCenter(),
                ViewColumn::make('desconto')
                    ->label('Desc.')
                    ->state(fn (ForcaVendasOrder $record): float => self::totalDesconto($record))
                    ->view('filament.components.erp.forca-vendas.monitor-money-cell')
                    ->extraCellAttributes(['class' => 'erp-fv-mon-money-cell']),
                ViewColumn::make('acrescimo')
                    ->label('Acmo.')
                    ->state(fn (ForcaVendasOrder $record): float => self::totalAcrescimo($record))
                    ->view('filament.components.erp.forca-vendas.monitor-money-cell')
                    ->extraCellAttributes(['class' => 'erp-fv-mon-money-cell']),
                ViewColumn::make('tt_bruto')
                    ->label('TT Bruto')
                    ->state(fn (ForcaVendasOrder $record): float => (float) ($record->orcamento?->subtotal ?? $record->total))
                    ->view('filament.components.erp.forca-vendas.monitor-money-cell')
                    ->extraCellAttributes(['class' => 'erp-fv-mon-money-cell']),
                ViewColumn::make('total')
                    ->label('TT Líquido')
                    ->state(fn (ForcaVendasOrder $record): float => (float) $record->total)
                    ->view('filament.components.erp.forca-vendas.monitor-money-cell')
                    ->extraCellAttributes(['class' => 'erp-fv-mon-money-cell erp-fv-mon-money-cell--bold']),
                TextColumn::make('meio_pgto')
                    ->label('Meio Pgto')
                    ->state(fn (ForcaVendasOrder $record): string => self::meioPagamentoLabel($record))
                    ->placeholder('—')
                    ->wrap(false),
                TextColumn::make('plataforma')
                    ->label('Plataforma')
                    ->state(fn (ForcaVendasOrder $record): string => self::plataformaLabel($record))
                    ->badge()
                    ->color(fn (ForcaVendasOrder $record): string => self::plataformaColor($record))
                    ->alignCenter(),
                TextColumn::make('financeiro')
                    ->label('Financeiro')
                    ->alignCenter()
                    ->state(function (ForcaVendasOrder $record): string {
                        if ($record->situacao === ForcaVendasOrder::SITUACAO_FINANCEIRO) {
                            return 'Financeiro';
                        }
                        $payload = is_array($record->payload) ? $record->payload : [];

                        return ! empty($payload['financeiro_liberado']) ? 'Liberado' : '—';
                    })
                    ->badge(fn (ForcaVendasOrder $record): bool => $record->situacao === ForcaVendasOrder::SITUACAO_FINANCEIRO
                        || ! empty((is_array($record->payload) ? $record->payload : [])['financeiro_liberado']))
                    ->color(function (ForcaVendasOrder $record): string {
                        if ($record->situacao === ForcaVendasOrder::SITUACAO_FINANCEIRO) {
                            return 'warning';
                        }
                        $payload = is_array($record->payload) ? $record->payload : [];

                        return ! empty($payload['financeiro_liberado']) ? 'success' : 'gray';
                    })
                    ->extraCellAttributes(function (ForcaVendasOrder $record): array {
                        if ($record->situacao !== ForcaVendasOrder::SITUACAO_FINANCEIRO) {
                            return [];
                        }

                        return [
                            'class' => 'erp-fv-mon__fin-cell',
                            'data-fv-fin' => (string) $record->getKey(),
                            'role' => 'button',
                            'tabindex' => '0',
                            'title' => 'Abrir liberação financeira',
                        ];
                    }),
            ])
            ->defaultSort('client_created_at', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Não há dados para mostrar');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForcaVendasMonitor::route('/'),
        ];
    }

    public static function plataformaLabel(ForcaVendasOrder $record): string
    {
        if (self::isMercadoLivre($record)) {
            return 'Mercado Livre';
        }

        return self::isVendasInternas($record) ? 'Vendas Internas' : 'Força de Vendas';
    }

    public static function plataformaColor(ForcaVendasOrder $record): string
    {
        if (self::isMercadoLivre($record)) {
            return 'warning';
        }

        return self::isVendasInternas($record) ? 'info' : 'gray';
    }

    public static function isMercadoLivre(ForcaVendasOrder $record): bool
    {
        if (filled($record->meli_order_id)) {
            return true;
        }

        $payload = is_array($record->payload) ? $record->payload : [];

        return ($payload['origem'] ?? '') === 'mercado_livre';
    }

    public static function isVendasInternas(ForcaVendasOrder $record): bool
    {
        $payload = is_array($record->payload) ? $record->payload : [];

        return ($payload['origem'] ?? '') === 'vendas_internas';
    }

    /**
     * Desconto do pedido: soma dos itens (rateio da tela de venda) ou cabeçalho do DAV/payload.
     */
    public static function totalDesconto(ForcaVendasOrder $record): float
    {
        $somaItens = round((float) ($record->orcamento?->itens?->sum('desconto') ?? 0), 2);

        if ($somaItens > 0) {
            return $somaItens;
        }

        $payload = is_array($record->payload) ? $record->payload : [];

        return round((float) (
            $record->orcamento?->desconto_valor
            ?? $payload['desconto_valor']
            ?? 0
        ), 2);
    }

    /**
     * Acréscimo do pedido: payload (tela de venda) ou frete legado do app.
     */
    public static function totalAcrescimo(ForcaVendasOrder $record): float
    {
        $payload = is_array($record->payload) ? $record->payload : [];

        if (isset($payload['acrescimo_valor']) && (float) $payload['acrescimo_valor'] > 0) {
            return round((float) $payload['acrescimo_valor'], 2);
        }

        $itens = is_array($payload['itens'] ?? null) ? $payload['itens'] : [];
        $soma = 0.0;

        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }

            $soma += (float) ($item['acrescimo'] ?? 0);
        }

        if ($soma > 0) {
            return round($soma, 2);
        }

        return round((float) ($payload['frete'] ?? 0), 2);
    }

    public static function meioPagamentoLabel(ForcaVendasOrder $record): string
    {
        $payload = is_array($record->payload) ? $record->payload : [];
        $forma = trim((string) (
            $payload['forma_pagamento']
            ?? $record->orcamento?->forma_pagamento
            ?? ''
        ));

        if ($forma === '') {
            return '—';
        }

        $chave = mb_strtoupper($forma, 'UTF-8');
        $chave = str_replace(['Á', 'À', 'Ã', 'Â', 'É', 'Ê', 'Í', 'Ó', 'Ô', 'Õ', 'Ú', 'Ç'], ['A', 'A', 'A', 'A', 'E', 'E', 'I', 'O', 'O', 'O', 'U', 'C'], $chave);

        return match ($chave) {
            'DINHEIRO' => 'Dinheiro',
            'PIX' => 'PIX',
            'POS DEBITO' => 'POS Débito',
            'POS CREDITO' => 'POS Crédito',
            'CREDIARIO' => 'Crediário',
            default => $forma,
        };
    }
}
