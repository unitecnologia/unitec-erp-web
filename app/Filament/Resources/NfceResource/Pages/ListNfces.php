<?php

namespace App\Filament\Resources\NfceResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\NfceResource;
use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Pdv\PdvNfceCupomPrinter;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListNfces extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = NfceResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'serie';

    #[Url(as: 'status')]
    public string $statusFilter = PdvVendaNfce::TAB_TRANSMITIDOS;

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public string $chaveFilter = '';

    public string $chaveFilterApplied = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('NFC-e');

        if ($this->periodoDe === '') {
            $this->periodoDe = now()->startOfMonth()->format('Y-m-d');
        }

        if ($this->periodoAte === '') {
            $this->periodoAte = now()->format('Y-m-d');
        }

        if ($this->periodoDeApplied === '') {
            $this->periodoDeApplied = $this->periodoDe;
        }

        if ($this->periodoAteApplied === '') {
            $this->periodoAteApplied = $this->periodoAte;
        }

        $this->statusFilter = PdvVendaNfce::normalizeTabFilter($this->statusFilter);
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-nfe-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma NFC-e';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-nfe__search-text, .erp-nfe__chave-input',
            'create' => 'modulePending',
            'edit' => 'imprimirNfce',
            'extraKeys' => [
                'F2' => ['method' => 'modulePending', 'params' => ['Cancelar NFC-e']],
                'F3' => ['method' => 'modulePending', 'params' => ['Inutilizar']],
                'F4' => ['method' => 'modulePending', 'params' => ['Recuperar']],
                'F5' => ['method' => 'modulePending', 'params' => ['Transmitir']],
                'F6' => ['method' => 'imprimirNfce'],
                'F7' => ['method' => 'modulePending', 'params' => ['Relatório']],
                'F8' => ['method' => 'modulePending', 'params' => ['Email']],
                'F9' => ['method' => 'modulePending', 'params' => ['Agrupar']],
                'F11' => ['method' => 'modulePending', 'params' => ['Gerar PDF']],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(NfceResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with([
                'pdvVenda.sessao.terminal',
                'pdvVenda.user',
                'pdvVenda.vendedor',
                'pdvVenda.venda',
            ]);

        $empresaId = $this->empresaIdAtiva();
        if ($empresaId) {
            $query->where(function (Builder $outer) use ($empresaId): void {
                $outer->where('empresa_id', $empresaId)
                    ->orWhere(function (Builder $inner) use ($empresaId): void {
                        $inner->whereNull('empresa_id')
                            ->whereHas('pdvVenda.sessao', fn (Builder $sessao): Builder => $sessao
                                ->where('empresa_id', $empresaId));
                    });
            });
        }

        $statuses = PdvVendaNfce::statusesForTab($this->statusFilter);
        $query->whereIn('status', $statuses);

        if (filled($this->periodoDeApplied)) {
            $query->whereHas('pdvVenda', fn (Builder $venda): Builder => $venda
                ->whereDate('fechado_em', '>=', $this->periodoDeApplied));
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereHas('pdvVenda', fn (Builder $venda): Builder => $venda
                ->whereDate('fechado_em', '<=', $this->periodoAteApplied));
        }

        if (filled($this->chaveFilterApplied)) {
            $digits = preg_replace('/\D/', '', $this->chaveFilterApplied) ?? '';
            $query->where('chave', 'like', '%'.$digits.'%');
        }

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, [
            'serie', 'numero', 'chave', 'protocolo', 'cpf', 'caixa', 'usuario', 'vendedor', 'pedido',
        ], true) ? $this->searchColumn : 'serie';

        $like = '%'.$term.'%';

        match ($column) {
            'serie' => $query->where('serie', 'like', $like),
            'numero' => $query->where('numero', 'like', $like),
            'chave' => $query->where('chave', 'like', $like),
            'protocolo' => $query->where('protocolo', 'like', $like),
            'cpf' => $query->whereHas('pdvVenda', fn (Builder $venda): Builder => $venda->where('cpf_nota', 'like', $like)),
            'caixa' => $query->whereHas('pdvVenda.sessao.terminal', fn (Builder $terminal): Builder => $terminal->where('nome', 'like', $like)),
            'usuario' => $query->whereHas('pdvVenda.user', fn (Builder $user): Builder => $user->where('name', 'like', $like)),
            'vendedor' => $query->where(function (Builder $outer) use ($like): void {
                $outer->whereHas('pdvVenda.vendedor', fn (Builder $vendedor): Builder => $vendedor->where('nome', 'like', $like))
                    ->orWhereHas('pdvVenda', fn (Builder $venda): Builder => $venda->where('vendedor_nome', 'like', $like));
            }),
            'pedido' => $query->whereHas('pdvVenda.venda', fn (Builder $venda): Builder => $venda->where('numero', 'like', $like)),
        };
    }

    protected function empresaIdAtiva(): ?int
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? (int) $empresaId : null;
    }

    #[Computed]
    public function empresaNome(): string
    {
        $empresaId = $this->empresaIdAtiva();

        $empresa = $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : Empresa::query()->where('ativo', true)->orderBy('id')->first();

        if (! $empresa) {
            return '—';
        }

        return $empresa->fantasia ?: ($empresa->nome ?: $empresa->razao_social);
    }

    #[Computed]
    public function searchColumnLabels(): array
    {
        return [
            'serie' => 'Série',
            'numero' => 'Número',
            'chave' => 'Chave',
            'protocolo' => 'Protocolo',
            'cpf' => 'CPF',
            'caixa' => 'Caixa',
            'usuario' => 'Usuário',
            'vendedor' => 'Vendedor',
            'pedido' => 'Nº Pedido',
        ];
    }

    #[Computed]
    public function filteredTotal(): float
    {
        $query = clone $this->buildListQuery();

        return (float) $query
            ->join('pdv_vendas as pv_total', 'pv_total.id', '=', 'pdv_venda_nfce.pdv_venda_id')
            ->sum('pv_total.total');
    }

    #[Computed]
    public function highlightedChave(): string
    {
        if (! $this->highlightedRecordId) {
            return '';
        }

        $record = PdvVendaNfce::query()->find($this->highlightedRecordId);

        return (string) ($record?->chave ?? '');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.nfce.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.nfce.footer-total'),
                View::make('filament.components.erp.nfce.action-bar'),
            ]);
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = PdvVendaNfce::normalizeTabFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function applyPeriodFilter(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Período filtrado.')
            ->success()
            ->send();
    }

    public function applyChaveFilter(): void
    {
        $this->chaveFilterApplied = trim($this->chaveFilter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function applyFooterSearch(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function imprimirNfce(): void
    {
        $id = $this->highlightedRecordIdOrNotify('imprimir');
        if (! $id) {
            return;
        }

        $nfce = PdvVendaNfce::query()->with('pdvVenda')->find($id);
        $vendaId = $nfce?->pdv_venda_id;

        if (! $vendaId) {
            Notification::make()
                ->title('NFC-e sem venda vinculada.')
                ->warning()
                ->send();

            return;
        }

        $this->js(PdvNfceCupomPrinter::livewireOpenJs((int) $vendaId, 1));
    }
}
