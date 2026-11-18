<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TefLiquidacaoLoteResource\Pages;
use App\Models\TefLiquidacaoLote;
use App\Models\Terminal;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TefLiquidacaoLoteResource extends Resource
{
    protected static ?string $model = TefLiquidacaoLote::class;

    protected static ?string $slug = 'tef-liquidacao-lotes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $modelLabel = 'lote TEF';

    protected static ?string $pluralModelLabel = 'lotes TEF';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('tef_liquidacao_lote.access');
    }

    public static function getEloquentQuery(): Builder
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $query = parent::getEloquentQuery()
            ->with([
                'terminal:id,empresa_id,nome,numero_logico_terminal',
                'caixaConta:id,empresa_id,codigo,nome,tipo,ativo',
            ])
            ->withCount('itens');

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
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
                TextColumn::make('terminal.nome')
                    ->label('Terminal')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('acquirer_cnpj')
                    ->label('Adquirente')
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? (string) $state : 'NÃO IDENT.')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('caixaConta.nome')
                    ->label('Conta BANCO')
                    ->placeholder('—')
                    ->wrap(false),
                TextColumn::make('periodo_de')
                    ->label('Período')
                    ->alignCenter()
                    ->formatStateUsing(function ($state, TefLiquidacaoLote $record): string {
                        $de = $record->periodo_de?->format('d/m/Y') ?? '—';
                        $ate = $record->periodo_ate?->format('d/m/Y') ?? '—';

                        return $de.' a '.$ate;
                    }),
                TextColumn::make('valor_bruto')
                    ->label('Bruto')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('valor_taxas')
                    ->label('Taxas')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('valor_liquido')
                    ->label('Líquido')
                    ->alignRight()
                    ->weight(FontWeight::Bold)
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('itens_count')
                    ->label('Itens')
                    ->alignCenter(),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->alignCenter()
                    ->formatStateUsing(fn (string $state): string => TefLiquidacaoLote::situacaoLabel($state))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('ofx_situacao')
                    ->label('OFX')
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state): string => TefLiquidacaoLote::ofxSituacaoLabel(
                        (string) ($state ?: TefLiquidacaoLote::OFX_PENDENTE)
                    ))
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
            ->emptyStateHeading('Nenhum lote de liquidação');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTefLiquidacaoLotes::route('/'),
        ];
    }

    /**
     * @return Builder<Terminal>
     */
    public static function terminaisQuery(?int $empresaId = null): Builder
    {
        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);

        return Terminal::query()
            ->when($empresaId > 0, fn (Builder $q) => $q->where('empresa_id', $empresaId))
            ->where('ativo', true)
            ->orderBy('nome')
            ->limit(300);
    }
}
