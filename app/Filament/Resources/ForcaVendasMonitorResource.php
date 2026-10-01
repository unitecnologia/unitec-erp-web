<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ForcaVendasMonitorResource\Pages;
use App\Models\ForcaVendasOrder;
use App\Models\Nfe;
use App\Models\PdvVendaNfce;
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
        return parent::getEloquentQuery()
            ->where('tipo', 'pedido')
            ->with([
                'pedido',
                'cliente',
                'vendedor',
                'user',
                'venda',
                'empresa:id,param_pix_habilitar',
            ])
            ->withExists(['pixCobrancasPedido as tem_pix_api']);
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
                    ->label('Nº Pedido')
                    ->state(fn (ForcaVendasOrder $record): string => $record->venda?->numero
                        ? (string) (int) preg_replace('/\D/', '', (string) $record->venda->numero)
                        : '—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nf_nfc')
                    ->label('NF/NFC')
                    ->state(fn (ForcaVendasOrder $record): string => self::documentoFiscalNumero($record))
                    ->html()
                    ->alignStart()
                    ->placeholder('—'),
                TextColumn::make('situacao')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ForcaVendasOrder $record): string => $record->situacaoLabel())
                    ->color(fn (ForcaVendasOrder $record): string => $record->situacaoColor())
                    ->alignCenter(),
                ViewColumn::make('local')
                    ->label('Local')
                    ->view('filament.components.erp.forca-vendas.monitor-local-cell')
                    ->alignCenter()
                    ->disabledClick(),
                TextColumn::make('cliente')
                    ->label('Cliente')
                    ->grow()
                    ->wrap(false)
                    ->weight(FontWeight::Bold)
                    ->state(fn (ForcaVendasOrder $record): string => $record->clienteNome()),
                TextColumn::make('vendedor')
                    ->label('Vendedor')
                    ->wrap(false)
                    ->extraHeaderAttributes(['class' => 'erp-fv-mon-vendedor'])
                    ->extraCellAttributes(['class' => 'erp-fv-mon-vendedor'])
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
                    ->extraCellAttributes(['class' => 'erp-fv-mon-money-cell erp-fv-mon-money-cell--desc']),
                ViewColumn::make('acrescimo')
                    ->label('Acre.')
                    ->state(fn (ForcaVendasOrder $record): float => self::totalAcrescimo($record))
                    ->view('filament.components.erp.forca-vendas.monitor-money-cell')
                    ->extraCellAttributes(['class' => 'erp-fv-mon-money-cell erp-fv-mon-money-cell--acre']),
                ViewColumn::make('tt_bruto')
                    ->label('TT Bruto')
                    ->state(fn (ForcaVendasOrder $record): float => self::totalBruto($record))
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
                    ->formatStateUsing(function (string $state, ForcaVendasOrder $record): string {
                        $label = e($state);

                        if (! self::meioPagamentoEhPix($record)) {
                            return $label;
                        }

                        return '<span class="erp-fv-mon-meio--pix">'.$label.'</span>';
                    })
                    ->html()
                    ->placeholder('—')
                    ->wrap(false),
                TextColumn::make('plataforma')
                    ->label('Plataforma')
                    ->state(fn (ForcaVendasOrder $record): string => self::plataformaLabel($record))
                    ->badge()
                    ->color(fn (ForcaVendasOrder $record): string => self::plataformaColor($record))
                    ->alignCenter(),
                ViewColumn::make('envio')
                    ->label('Env.')
                    ->view('filament.components.erp.forca-vendas.monitor-envio-cell')
                    ->alignCenter()
                    ->disabledClick(),
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

        if (self::isVendasInternas($record)) {
            return 'Vendas Internas';
        }

        return self::isTelaVenda($record) ? 'Tela de Vendas' : 'Força de Vendas';
    }

    public static function plataformaColor(ForcaVendasOrder $record): string
    {
        if (self::isMercadoLivre($record)) {
            return 'warning';
        }

        if (self::isVendasInternas($record)) {
            return 'info';
        }

        return self::isTelaVenda($record) ? 'primary' : 'gray';
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

    /** Pedido gerado na Tela de Venda do ERP (não no app mobile). */
    public static function isTelaVenda(ForcaVendasOrder $record): bool
    {
        if ((string) ($record->device_uuid ?? '') === 'monitor-web') {
            return true;
        }

        $origem = (string) ((is_array($record->payload) ? $record->payload : [])['origem'] ?? '');

        return str_starts_with($origem, 'monitor_tela_venda');
    }

    /**
     * Número da NF-e ou NFC-e vinculada ao pedido (venda/DAV), se houver.
     */
    public static function documentoFiscalNumero(ForcaVendasOrder $record): string
    {
        $venda = $record->relationLoaded('venda')
            ? $record->venda
            : ($record->venda_id ? $record->venda()->with(['nfes', 'pdvVenda.nfce'])->first() : null);

        if (! $venda) {
            return '—';
        }

        $nfe = $venda->relationLoaded('nfes')
            ? $venda->nfes
            : $venda->nfes()->get();

        $nfeAtiva = $nfe
            ->filter(fn (Nfe $n): bool => $n->status !== Nfe::STATUS_CANCELADA
                && $n->status !== Nfe::STATUS_INUTILIZADA)
            ->sortByDesc(fn (Nfe $n): int => match ($n->status) {
                Nfe::STATUS_TRANSMITIDA => 3,
                Nfe::STATUS_CONTINGENCIA => 2,
                default => 1,
            })
            ->first();

        if ($nfeAtiva) {
            $num = self::formatNotaNumero((string) ($nfeAtiva->numero ?? ''));
            // Modelo 55 = NF-e → NF; modelo 65 = NFC-e → NC
            $pref = ((string) ($nfeAtiva->modelo ?? '') === '65') ? 'NC' : 'NF';

            return $num !== '' ? self::formatDocPrefHtml($pref, $num) : '—';
        }

        $nfce = $venda->pdvVenda?->nfce;

        if ($nfce && ! in_array($nfce->status, [
            PdvVendaNfce::STATUS_CANCELADA,
            PdvVendaNfce::STATUS_REJEITADA,
        ], true)) {
            $num = self::formatNotaNumero((string) ($nfce->numero ?? ''));

            return $num !== '' ? self::formatDocPrefHtml('NC', $num) : '—';
        }

        return '—';
    }

    protected static function formatDocPrefHtml(string $prefix, string $numero): string
    {
        return '<span class="erp-doc-pref"><span class="erp-doc-pref__letra">'.e($prefix).'-</span><span class="erp-doc-pref__num">'.e($numero).'</span></span>';
    }

    protected static function formatNotaNumero(string $numero): string
    {
        $digits = preg_replace('/\D/', '', $numero) ?? '';

        if ($digits === '') {
            return trim($numero) !== '' ? trim($numero) : '';
        }

        return (string) (int) $digits;
    }

    /**
     * Desconto do pedido: soma dos itens (rateio da tela de venda) ou cabeçalho do DAV/payload.
     */
    public static function totalDesconto(ForcaVendasOrder $record): float
    {
        $somaItens = round((float) ($record->pedido?->itens?->sum('desconto') ?? 0), 2);

        if ($somaItens > 0) {
            return $somaItens;
        }

        $payload = is_array($record->payload) ? $record->payload : [];

        return round((float) (
            $record->pedido?->desconto_valor
            ?? $payload['desconto_valor']
            ?? 0
        ), 2);
    }

    /**
     * Bruto = líquido dos itens − acréscimos + descontos.
     * (O subtotal do pedido já embute acréscimo/desconto das linhas.)
     */
    public static function totalBruto(ForcaVendasOrder $record): float
    {
        $subtotal = (float) ($record->pedido?->subtotal ?? $record->total);

        return round($subtotal - self::totalAcrescimo($record) + self::totalDesconto($record), 2);
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
            ?? $record->pedido?->forma_pagamento
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

    public static function meioPagamentoEhPix(ForcaVendasOrder $record): bool
    {
        $label = mb_strtoupper(self::meioPagamentoLabel($record), 'UTF-8');

        if ($label === '—' || ! str_contains($label, 'PIX')) {
            return false;
        }

        if (! $record->relationLoaded('empresa') || ! (bool) ($record->empresa?->param_pix_habilitar ?? false)) {
            return false;
        }

        return (bool) ($record->tem_pix_api ?? false);
    }

    /**
     * URL do Google Maps com as coordenadas GPS gravadas no pedido (aparelho).
     * Retorna null se latitude/longitude forem inválidas ou ausentes.
     */
    public static function googleMapsUrl(ForcaVendasOrder $record): ?string
    {
        $latRaw = $record->latitude;
        $lngRaw = $record->longitude;

        if ($latRaw === null || $lngRaw === null || $latRaw === '' || $lngRaw === '') {
            return null;
        }

        if (! is_numeric($latRaw) || ! is_numeric($lngRaw)) {
            return null;
        }

        $lat = (float) $latRaw;
        $lng = (float) $lngRaw;

        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        return 'https://www.google.com/maps?q='.rawurlencode(sprintf('%.7f,%.7f', $lat, $lng));
    }
}
