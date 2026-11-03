<?php

namespace App\Filament\Resources;

use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpTableSort;
use App\Filament\Resources\NfeResource\Pages;
use App\Models\Nfe;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NfeResource extends Resource
{
    protected static ?string $model = Nfe::class;

    protected static ?string $slug = 'nfe';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static ?string $modelLabel = 'NF-e';

    protected static ?string $pluralModelLabel = 'NF-e';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('nfe.access');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['venda' => static fn ($q) => $q->select(['id', 'numero'])]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('selecao')
                    ->label('')
                    ->view('filament.components.erp.nfe.select-cell')
                    ->alignCenter()
                    ->width('2.25rem')
                    ->disabledClick(),
                TextColumn::make('numero')
                    ->label('Número')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => ErpTableSort::orderByCodigoNumerico(
                        $query,
                        $direction,
                        'numero',
                        desempateIdDesc: true,
                    ))
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('npedido')
                    ->label('Ped/OS')
                    ->state(fn (Nfe $record): string => self::formatNumeroPedido($record))
                    ->html()
                    ->placeholder('—')
                    ->alignStart(),
                TextColumn::make('data_emissao')
                    ->label('Dt.Emissão')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('data_saida')
                    ->label('Dt.Saída')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cliente.nome_razao')
                    ->label('Cliente')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('chave')
                    ->label('Chave')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('protocolo')
                    ->label('Protocolo')
                    ->placeholder('—')
                    ->wrap(false)
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('finalidade')
                    ->label('Finalidade')
                    ->formatStateUsing(fn (?string $state): string => Nfe::finalidadeLabel($state))
                    ->placeholder('NORMAL')
                    ->wrap(false)
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                ViewColumn::make('status')
                    ->label('Situação')
                    ->view('filament.components.erp.nfe.columns.status')
                    ->alignCenter()
                    ->disabledClick(),
                ViewColumn::make('total')
                    ->label('Total')
                    ->view('filament.components.erp.nfe.columns.total')
                    ->alignEnd()
                    ->disabledClick(),
                ViewColumn::make('historico')
                    ->label('')
                    ->state(fn (): bool => true)
                    ->width('38px')
                    ->view('filament.components.erp.nfe.columns.historico')
                    ->alignCenter()
                    ->disabledClick(),
            ])
            ->defaultSort(
                fn (Builder $query, string $direction, $livewire): Builder => ErpTableSort::applyDefaultCodigoNumerico(
                    $query,
                    $direction,
                    $livewire,
                    'numero',
                    desempateIdDesc: true,
                ),
                'desc',
            )
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma NF-e encontrada');
    }

    /**
     * Coluna "Nº Pedido": número comercial da venda vinculada.
     * OS permanece com prefixo O- (não usa DAV.NUMERO_DAV).
     */
    public static function formatNumeroPedido(Nfe $nfe): string
    {
        if (self::isOrigemOrdemServico($nfe)) {
            $raw = trim((string) ($nfe->npedido ?? ''));

            if ($raw === '') {
                return '—';
            }

            $digits = preg_replace('/\D/', '', $raw) ?? '';
            $num = $digits !== '' ? (string) (int) $digits : $raw;

            return '<span class="erp-doc-pref"><span class="erp-doc-pref__letra">O-</span><span class="erp-doc-pref__num">'.e($num).'</span></span>';
        }

        $vendaNumero = trim((string) ($nfe->venda?->numero ?? ''));

        if ($vendaNumero === '') {
            return '—';
        }

        $digits = preg_replace('/\D/', '', $vendaNumero) ?? '';
        $num = $digits !== '' ? (string) (int) $digits : $vendaNumero;

        return e($num);
    }

    /** @deprecated Use formatNumeroPedido() */
    public static function formatOsDav(Nfe $nfe): string
    {
        return self::formatNumeroPedido($nfe);
    }

    protected static function isOrigemOrdemServico(Nfe $nfe): bool
    {
        if ((int) ($nfe->ordem_servico_id ?? 0) > 0) {
            return true;
        }

        $obs = mb_strtoupper(trim((string) ($nfe->obs_contribuinte ?? '')), 'UTF-8');

        return $obs !== '' && (
            str_contains($obs, 'ORIGINADA DA OS')
            || (bool) preg_match('/\bOS\s*N[º°O.]/u', $obs)
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNfes::route('/'),
        ];
    }
}
