<?php

namespace App\Filament\Resources;

use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfce\NfceConsumidorIdentificado;
use App\Filament\Resources\NfceResource\Pages;
use App\Models\PdvVendaNfce;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class NfceResource extends Resource
{
    protected static ?string $model = PdvVendaNfce::class;

    protected static ?string $slug = 'nfce';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $modelLabel = 'NFC-e';

    protected static ?string $pluralModelLabel = 'NFC-e';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    private const ABAS_COM_SITUACAO = [
        PdvVendaNfce::TAB_GRAVADOS,
        PdvVendaNfce::TAB_DUPLICIDADE,
        PdvVendaNfce::TAB_INUTILIZADOS,
        PdvVendaNfce::TAB_DENEGADO,
    ];

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('nfce.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('transmitir_flag')
                    ->label('')
                    ->view('filament.components.erp.nfce.transmit-select-cell')
                    ->alignCenter(),
                TextColumn::make('serie')
                    ->label('>>Série')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('numero')
                    ->label('NFC-e')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => $state !== null ? str_pad((string) $state, 6, '0', STR_PAD_LEFT) : '—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.fechado_em')
                    ->label('Dt.Emissão')
                    ->formatStateUsing(fn ($state): string => filled($state)
                        ? ErpTimezone::toLocal($state)->format('d/m/Y H:i')
                        : '—')
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('chave')
                    ->label('Chave')
                    ->placeholder('—')
                    ->wrap(false)
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('protocolo')
                    ->label('Protocolo')
                    ->placeholder('—')
                    ->alignCenter()
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.cpf_nota')
                    ->label('CPF')
                    ->placeholder('—')
                    ->alignCenter()
                    ->formatStateUsing(function (?string $state, $record): string {
                        $raw = filled($state) ? $state : ($record?->pdvVenda?->person?->cpf_cnpj ?? null);

                        if (! filled($raw)) {
                            return '—';
                        }

                        $digits = preg_replace('/\D/', '', (string) $raw) ?? '';

                        if (strlen($digits) === 11) {
                            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
                        }

                        return (string) $raw;
                    })
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->placeholder('—')
                    ->getStateUsing(function (PdvVendaNfce $record): ?string {
                        $venda = $record->pdvVenda;
                        if ($venda === null) {
                            return null;
                        }

                        $nota = trim((string) ($venda->nome_nota ?? ''));
                        if ($nota !== '' && filled($venda->cpf_nota)) {
                            return $nota;
                        }

                        return NfceConsumidorIdentificado::nome($venda->person);
                    })
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.sessao.terminal.nome')
                    ->label('Caixa')
                    ->placeholder('—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.vendedor.nome')
                    ->label('Vendedor')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state, $record): string => filled($state)
                        ? (string) $state
                        : (string) ($record->pdvVenda?->vendedor_nome ?: '—'))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('desc_acres')
                    ->label('Desc/Acrés')
                    ->alignEnd()
                    ->getStateUsing(fn (PdvVendaNfce $record): float => round(
                        (float) ($record->pdvVenda?->acrescimo ?? 0) - (float) ($record->pdvVenda?->desconto ?? 0),
                        2
                    ))
                    ->formatStateUsing(fn ($state): string => (float) $state == 0.0
                        ? '0,00'
                        : ((float) $state > 0 ? '+' : '-').number_format(abs((float) $state), 2, ',', '.'))
                    ->color(fn ($state): ?string => match (true) {
                        (float) $state < 0 => 'danger',
                        (float) $state > 0 => 'success',
                        default => null,
                    })
                    ->weight(FontWeight::SemiBold),
                ViewColumn::make('total')
                    ->label('Total')
                    ->view('filament.components.erp.nfce.columns.total')
                    ->alignEnd()
                    ->disabledClick(),
                TextColumn::make('pdvVenda.venda.numero')
                    ->label('Pedido')
                    ->alignCenter()
                    ->placeholder('—')
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        $trimmed = ltrim($state, '0');

                        return $trimmed !== '' ? $trimmed : '0';
                    })
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('pdvVenda.numero')
                    ->label('Nº Dav')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => $state !== null ? str_pad((string) $state, 6, '0', STR_PAD_LEFT) : '—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('motivo_rejeicao')
                    ->label('Situação SEFAZ')
                    ->visible(fn ($livewire): bool => in_array((string) ($livewire->statusFilter ?? ''), self::ABAS_COM_SITUACAO, true))
                    ->getStateUsing(fn (PdvVendaNfce $record): string => $record->situacaoSefazLabel()
                        .(filled($record->motivo_rejeicao) ? ' — '.trim((string) $record->motivo_rejeicao) : ''))
                    ->limit(90)
                    ->tooltip(fn (PdvVendaNfce $record): ?string => filled($record->motivo_rejeicao) ? trim((string) $record->motivo_rejeicao) : null)
                    ->color(fn (PdvVendaNfce $record): ?string => in_array((string) $record->status, [
                        PdvVendaNfce::STATUS_REJEITADA,
                        PdvVendaNfce::STATUS_DENEGADA,
                        PdvVendaNfce::STATUS_DUPLICIDADE,
                    ], true) ? 'danger' : null)
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('numero', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma NFC-e encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNfces::route('/'),
        ];
    }
}
