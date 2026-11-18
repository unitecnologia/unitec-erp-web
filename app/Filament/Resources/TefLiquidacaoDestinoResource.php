<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TefLiquidacaoDestinoResource\Pages;
use App\Models\CaixaConta;
use App\Models\TefLiquidacaoDestino;
use App\Models\Terminal;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Tef\TefProviderId;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TefLiquidacaoDestinoResource extends Resource
{
    protected static ?string $model = TefLiquidacaoDestino::class;

    protected static ?string $slug = 'tef-liquidacao-destinos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $modelLabel = 'destino SuperTEF';

    protected static ?string $pluralModelLabel = 'destinos SuperTEF';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('tef_liquidacao_destino.access');
    }

    public static function getEloquentQuery(): Builder
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $query = parent::getEloquentQuery()
            ->with([
                'terminal:id,empresa_id,nome,numero_logico_terminal',
                'caixaConta:id,empresa_id,codigo,nome,tipo,ativo',
            ]);

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
                TextColumn::make('provedor')
                    ->label('Provedor')
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state): string => TefProviderId::label((string) ($state ?: TefProviderId::SUPERTEF)))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('acquirer_cnpj')
                    ->label('Adquirente')
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? (string) $state : 'PADRÃO')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('caixaConta.nome')
                    ->label('Conta BANCO')
                    ->placeholder('—')
                    ->wrap(false)
                    ->description(fn (TefLiquidacaoDestino $record): ?string => $record->caixaConta
                        ? trim((string) ($record->caixaConta->codigo ?? '').' ')
                        : null),
                TextColumn::make('ativo')
                    ->label('Ativo')
                    ->alignCenter()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'SIM' : 'NÃO')
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
            ->emptyStateHeading('Nenhum destino configurado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTefLiquidacaoDestinos::route('/'),
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

    /**
     * Contas BANCO ativas visíveis na empresa atual.
     *
     * @return Builder<CaixaConta>
     */
    public static function contasBancoQuery(?int $empresaId = null): Builder
    {
        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);

        return CaixaConta::query()
            ->where('ativo', true)
            ->where('tipo', CaixaConta::TIPO_BANCO)
            ->visiveisNaEmpresa($empresaId > 0 ? $empresaId : null)
            ->orderBy('nome')
            ->limit(300);
    }
}
