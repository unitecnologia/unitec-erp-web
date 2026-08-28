<?php

namespace App\Filament\Resources\NfeResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Concerns\InteractsWithLocalClienteSearchLookup;
use App\Filament\Resources\NfeResource;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeCancelamento;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeCartaCorrecao;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeCceDispatch;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeContadorEmail;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeDanfeEmail;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeEmissaoModal;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeEspelhoModal;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeHistoricoModal;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeImportacao;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeInutilizacao;
use App\Filament\Resources\NfeResource\Pages\Concerns\ManagesNfeRelatorio;
use App\Models\DevolucaoCompra;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\OperacaoFiscal;
use App\Models\OutrasSaidaMovimento;
use App\Models\Person;
use App\Models\Venda;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfe\NfeDevolucaoCompraService;
use App\Support\Erp\Nfe\NfeVendaMercadoriaService;
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

class ListNfes extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use InteractsWithLocalClienteSearchLookup {
        InteractsWithLocalClienteSearchLookup::updatedLocalSearch as protected updatedLocalSearchFromClienteLookup;
    }
    use ManagesNfeEmissaoModal;
    use ManagesNfeDanfeEmail;
    use ManagesNfeEspelhoModal;
    use ManagesNfeImportacao;
    use ManagesNfeCancelamento;
    use ManagesNfeCartaCorrecao;
    use ManagesNfeCceDispatch;
    use ManagesNfeInutilizacao;
    use ManagesNfeHistoricoModal;
    use ManagesNfeRelatorio;
    use ManagesNfeContadorEmail;

    protected static string $resource = NfeResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'cliente';

    /** @var list<string> */
    public array $searchFieldsActive = ['cliente', 'data_emissao'];

    /** @var array<string, string> */
    public array $localSearchByField = [];

    public string $clienteFilter = 'todos';

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'todas';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('NF-e');

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->searchFieldsActive = $this->ensureTwoSearchFields($this->normalizedSearchFieldsActive());
        $this->searchColumn = $this->searchFieldsActive[array_key_last($this->searchFieldsActive)] ?? 'cliente';
        $this->hydrateLocalSearchByFieldFromLegacy();

        if ($this->activeDateSearchColumn() !== null) {
            $this->applyCurrentMonthDateFilter();
        }

        $movimentoId = (int) request()->query('outras_saida_movimento', 0);
        if ($movimentoId > 0) {
            $this->abrirNfeDeOutrasSaida($movimentoId);

            return;
        }

        $devolucaoCompraId = (int) request()->query('devolucao_compra_id', 0);
        if ($devolucaoCompraId > 0) {
            $this->abrirNfeDeDevolucaoCompra($devolucaoCompraId);

            return;
        }

        $vendaId = (int) request()->query('venda_id', 0);
        if ($vendaId > 0) {
            $this->abrirNfeDeVendaMercadoria($vendaId);
        }
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-nfe-page';
    }

    private function abrirNfeDeOutrasSaida(int $movimentoId): void
    {
        $empresaId = \App\Support\Erp\ErpContext::currentEmpresaId();

        $movimento = OutrasSaidaMovimento::query()
            ->with('itens')
            ->whereKey($movimentoId)
            ->when($empresaId, fn (Builder $query, int $id) => $query->where('empresa_id', $id))
            ->first();

        if (! $movimento) {
            Notification::make()
                ->title('Movimento de saída não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $rows = $movimento->itens
            ->filter(fn ($item): bool => (int) ($item->product_id ?? 0) > 0)
            ->map(fn ($item): array => [
                'product_id' => (int) $item->product_id,
                'quantidade' => (float) $item->qtd,
                'valor_unitario' => (float) $item->preco,
                'descricao' => (string) ($item->produto_descricao ?? ''),
            ])
            ->values()
            ->all();

        if ($rows === []) {
            Notification::make()
                ->title('O movimento não possui itens vinculados a produtos.')
                ->body('Abra o movimento e grave novamente após incluir os produtos.')
                ->warning()
                ->send();

            return;
        }

        $this->createNfe();

        $perda = $movimento->tipo_movimento === 'perda';
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        if ($perda) {
            $cnpjEmpresa = preg_replace('/\D/', '', (string) ($empresa?->cnpj ?? '')) ?: '';
            $destinatarioProprio = $cnpjEmpresa === ''
                ? null
                : Person::query()
                    ->whereRaw("REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '/', ''), '-', '') = ?", [$cnpjEmpresa])
                    ->first();

            if (! $destinatarioProprio) {
                $this->closeNfeModal();
                Notification::make()
                    ->title('Não é possível emitir a NF-e de perda para outro destinatário.')
                    ->body('Cadastre a própria empresa em Pessoas com o mesmo CNPJ da empresa para usar como destinatário da NF-e de perda.')
                    ->danger()
                    ->send();

                return;
            }

            $this->nfeForm['cliente_id'] = (string) $destinatarioProprio->id;
            $this->updatedNfeFormClienteId();
        } elseif ($movimento->fornecedor_id) {
            $this->nfeForm['cliente_id'] = (string) $movimento->fornecedor_id;
            $this->updatedNfeFormClienteId();
        }

        $this->nfeForm['numero_pedido'] = (string) $movimento->numero;
        $this->nfeForm['data_emissao'] = $movimento->data?->format('Y-m-d') ?? ErpTimezone::today();
        $this->nfeForm['data_saida'] = $movimento->data?->format('Y-m-d') ?? ErpTimezone::today();

        if ($perda && $empresaId) {
            $cfop = OperacaoFiscal::forEmpresa($empresaId)->cfopSaidaPerda(false);

            if ($cfop) {
                $descricaoCfop = \App\Models\Cfop::query()
                    ->where('codigo', $cfop)
                    ->value('descricao');
                $this->nfeForm['natureza_operacao'] = trim(
                    $cfop.($descricaoCfop ? ' - '.mb_strtoupper((string) $descricaoCfop, 'UTF-8') : '')
                );

                foreach ($rows as $index => $row) {
                    $rows[$index]['cfop'] = (string) $cfop;
                }
            }
        }

        $observacao = trim((string) ($movimento->observacoes ?? ''));
        $origem = 'NF-e originada da saída de estoque nº '.ltrim((string) $movimento->numero, '0').'.';
        $this->nfeForm['obs_contribuinte'] = trim($observacao === '' ? $origem : $origem.' '.$observacao);

        $this->nfeModalRows = $rows;
        $this->recalculateNfeTotais();
        $this->dispatch('erp-nfe-focus-item-codigo');
    }

    private function abrirNfeDeDevolucaoCompra(int $devolucaoCompraId): void
    {
        $empresaId = \App\Support\Erp\ErpContext::currentEmpresaId();

        $devolucao = DevolucaoCompra::query()
            ->with(['itens.product', 'compra', 'fornecedor', 'empresa'])
            ->whereKey($devolucaoCompraId)
            ->when($empresaId, fn (Builder $query, int $id) => $query->where('empresa_id', $id))
            ->first();

        if (! $devolucao) {
            Notification::make()
                ->title('Devolução de compra não encontrada.')
                ->warning()
                ->send();

            return;
        }

        try {
            $payload = app(NfeDevolucaoCompraService::class)->montarPayload($devolucao);

            $this->createNfe();

            $this->nfeModalDevolucaoCompraId = (int) $payload['devolucao_compra_id'];
            $this->nfeForm['cliente_id'] = (string) $payload['cliente_id'];
            $this->updatedNfeFormClienteId();
            $this->nfeForm['finalidade'] = $payload['finalidade'];
            $this->nfeForm['movimento'] = $payload['movimento'];
            $this->nfeForm['numero_pedido'] = $payload['numero_pedido'];
            $this->nfeForm['natureza_operacao'] = $payload['natureza_operacao'];
            $this->nfeForm['data_emissao'] = $payload['data_emissao'];
            $this->nfeForm['data_saida'] = $payload['data_saida'];
            $this->nfeForm['obs_contribuinte'] = mb_strtoupper((string) $payload['obs_contribuinte'], 'UTF-8');

            foreach ($payload['referencias'] as $referencia) {
                $chave = trim((string) ($referencia['referencia'] ?? ''));

                if ($chave !== '') {
                    $this->nfeModalReferencias[] = ['referencia' => $chave];
                }
            }

            $this->nfeModalRows = $payload['rows'];
            $this->recalculateNfeTotais();
        } catch (\Throwable $exception) {
            $this->closeNfeModal();

            Notification::make()
                ->title('Não foi possível preparar a NF-e de devolução.')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        // saveNfe no mount quebra o layout Livewire (MissingLayoutException).
        // Grava o rascunho no próximo request e recarrega o modal do banco.
        $this->js('queueMicrotask(() => $wire.saveNfeDraftFromMount())');

        Notification::make()
            ->title('NF-e de devolução preparada.')
            ->body('Revise os dados e transmita a nota.')
            ->success()
            ->send();

        $this->dispatch('erp-nfe-focus-item-codigo');
    }

    private function abrirNfeDeVendaMercadoria(int $vendaId): void
    {
        $empresaId = \App\Support\Erp\ErpContext::currentEmpresaId();

        $venda = Venda::query()
            ->with(['itens.product', 'cliente', 'vendedor', 'forcaVendasOrder.orcamento'])
            ->whereKey($vendaId)
            ->when(
                $empresaId,
                fn (Builder $query, int $id) => $query->whereHas(
                    'forcaVendasOrder',
                    fn (Builder $q) => $q->where('empresa_id', $id),
                ),
            )
            ->first();

        if (! $venda) {
            Notification::make()
                ->title('Venda não encontrada.')
                ->warning()
                ->send();

            return;
        }

        try {
            $payload = app(NfeVendaMercadoriaService::class)->montarPayload($venda);

            $this->createNfe();

            $this->nfeModalVendaId = (int) $payload['venda_id'];
            $this->nfeForm['cliente_id'] = (string) $payload['cliente_id'];
            $this->updatedNfeFormClienteId();
            $this->nfeForm['finalidade'] = $payload['finalidade'];
            $this->nfeForm['movimento'] = $payload['movimento'];
            $this->nfeForm['numero_pedido'] = $payload['numero_pedido'];
            $this->nfeForm['natureza_operacao'] = $payload['natureza_operacao'];
            $this->nfeForm['data_emissao'] = $payload['data_emissao'];
            $this->nfeForm['data_saida'] = $payload['data_saida'];
            $this->nfeForm['forma_pgto'] = $payload['forma_pgto'];
            $this->nfeForm['meio_pgto'] = $payload['meio_pgto'];
            $this->nfeForm['obs_contribuinte'] = mb_strtoupper((string) $payload['obs_contribuinte'], 'UTF-8');
            $this->nfeModalFaturas = $payload['faturas'];
            $this->nfeModalRows = $payload['rows'];
            $this->recalculateNfeTotais();
        } catch (\Throwable $exception) {
            $this->closeNfeModal();

            Notification::make()
                ->title('Não foi possível preparar a NF-e de venda.')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        // saveNfe no mount quebra o layout Livewire (MissingLayoutException).
        // Grava o rascunho no próximo request e recarrega o modal do banco.
        $this->js('queueMicrotask(() => $wire.saveNfeDraftFromMount())');

        Notification::make()
            ->title('NF-e de venda preparada.')
            ->body('Revise os dados e transmita a nota.')
            ->success()
            ->send();

        $this->dispatch('erp-nfe-focus-item-codigo');
    }

    protected function erpListEntityName(): string
    {
        return 'uma NF-e';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-nfe__search-text, .erp-nfe__search-date-from, .erp-field-dd__btn',
            'create' => 'createNfe',
            'edit' => 'editNfe',
            'extraKeys' => [
                'F4' => ['method' => 'cancelarNfe'],
                'F5' => ['method' => 'inutilizarNfe'],
                'F7' => ['method' => 'handleNfeF7FromList'],
                'F8' => ['method' => 'cartaCorrecaoNfe'],
                'F9' => ['method' => 'openNfeDanfeEmailFromList'],
                'F10' => ['method' => 'printRelatorioNfe'],
                'F12' => ['method' => 'openNfeContadorEmailModal'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(NfeResource::table($table));
    }

    public function toggleNfeSelecionado(int $recordId): void
    {
        if ((int) $this->highlightedRecordId === $recordId) {
            $this->highlightedRecordId = null;

            return;
        }

        $this->highlightedRecordId = $recordId;
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['cliente', 'venda']);

        $empresaId = \App\Support\Erp\ErpContext::currentEmpresaId();

        if ($empresaId !== null) {
            $query->where('empresa_id', $empresaId);
        }

        if ($this->statusFilter !== 'todas') {
            $query->where('status', $this->statusFilter);
        }

        foreach ($this->normalizedSearchFieldsActive() as $column) {
            if ($this->isDateSearchColumn($column)) {
                $this->applyLocalSearchDateRange($query, $column);

                continue;
            }

            if ($column === 'cliente') {
                if ($this->clienteFilter !== 'todos' && is_numeric($this->clienteFilter)) {
                    $query->where('cliente_id', (int) $this->clienteFilter);

                    continue;
                }

                if ($this->shouldSkipLocalSearchWhileTyping()) {
                    continue;
                }
            }

            $term = trim((string) ($this->localSearchByField[$column] ?? ''));

            if ($term === '') {
                continue;
            }

            $this->applyLocalSearchForColumn($query, $term, $column);
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return ['numero', 'data_emissao', 'data_saida', 'cliente', 'chave', 'protocolo', 'total'];
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)
            ? $this->searchColumn
            : 'cliente';

        if ($this->isDateSearchColumn($column)) {
            return;
        }

        $this->applyLocalSearchForColumn($query, $term, $column);
    }

    protected function applyLocalSearchForColumn(Builder $query, string $term, string $column): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $like),
            'cliente' => $query->whereHas('cliente', fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $like)),
            'chave' => $query->where('chave', 'like', '%'.(preg_replace('/\D/', '', $term) ?? '').'%'),
            'protocolo' => $query->where('protocolo', 'like', $like),
            'total' => $this->applyLocalSearchByTotal($query, $term),
            default => null,
        };
    }

    protected function applyLocalSearchDateRange(Builder $query, string $column): void
    {
        if (! filled($this->localSearchDe) && ! filled($this->localSearchAte)) {
            return;
        }

        if (filled($this->localSearchDe)) {
            $query->whereDate($column, '>=', $this->localSearchDe);
        }

        if (filled($this->localSearchAte)) {
            $query->whereDate($column, '<=', $this->localSearchAte);
        }
    }

    protected function isDateSearchColumn(string $column): bool
    {
        return in_array($column, ['data_emissao', 'data_saida'], true);
    }

    protected function activeDateSearchColumn(): ?string
    {
        foreach ($this->normalizedSearchFieldsActive() as $column) {
            if ($this->isDateSearchColumn($column)) {
                return $column;
            }
        }

        return null;
    }

    protected function applyCurrentMonthDateFilter(): void
    {
        $hoje = ErpTimezone::toLocal();
        $this->localSearchDe = $hoje->copy()->startOfMonth()->toDateString();
        $this->localSearchAte = $hoje->copy()->endOfMonth()->toDateString();
    }

    protected function applyLocalSearchByTotal(Builder $query, string $term): void
    {
        $normalized = str_replace(['R$', ' '], '', $term);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            if ($this->databaseDriver($query) === 'sqlite') {
                $query->whereRaw('CAST(total AS TEXT) LIKE ?', ['%'.$normalized.'%']);

                return;
            }

            $query->where('total', 'like', '%'.$normalized.'%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', total), '.', ',') LIKE ?", ['%'.$term.'%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT(total, 2), '.', ',') LIKE ?", ['%'.$term.'%']);
    }

    protected function databaseDriver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
    }

    protected function normalizeStatusFilter(string $filter): string
    {
        $allowed = [
            'todas',
            Nfe::STATUS_ABERTA,
            Nfe::STATUS_TRANSMITIDA,
            Nfe::STATUS_CANCELADA,
            Nfe::STATUS_DUPLICIDADE,
            Nfe::STATUS_INUTILIZADA,
            Nfe::STATUS_DENEGADA,
            Nfe::STATUS_CONTINGENCIA,
        ];

        return in_array($filter, $allowed, true) ? $filter : Nfe::STATUS_ABERTA;
    }

    #[Computed]
    public function empresaNome(): string
    {
        $empresaId = \App\Support\Erp\ErpContext::currentEmpresaId();

        $empresa = $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : null;

        if (! $empresa) {
            return '—';
        }

        return $empresa->fantasia ?: ($empresa->nome ?: $empresa->razao_social);
    }

    #[Computed]
    public function clientesOptions(): array
    {
        return Person::query()
            ->where('is_cliente', true)
            ->where('ativo', true)
            ->orderBy('nome_razao')
            ->pluck('nome_razao', 'id')
            ->all();
    }

    #[Computed]
    public function filteredTotal(): float
    {
        return (float) $this->buildListQuery()->sum('total');
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'whatsapp' => 'uma NF-e transmitida para enviar WhatsApp',
            'email' => 'uma NF-e transmitida para enviar e-mail',
            'imprimir' => 'uma NF-e para imprimir',
            'espelho' => 'uma NF-e aberta para visualizar o espelho',
            'cancelar' => 'uma NF-e transmitida para cancelar',
            'cce' => 'uma NF-e transmitida para Carta de Correção',
            default => parent::erpListSelectPrompt($action),
        };
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.nfe.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.nfe.footer-total'),
                View::make('filament.components.erp.nfe.action-bar'),
                View::make('filament.components.erp.nfe.lancamento-modal'),
                View::make('filament.components.erp.nfe.fiscal-whatsapp-modal'),
                View::make('filament.components.erp.nfe.fiscal-danfe-email-modal'),
                View::make('filament.components.erp.nfe.fiscal-cancel-modal'),
                View::make('filament.components.erp.nfe.fiscal-cancel-aberta-modal'),
                View::make('filament.components.erp.nfe.fiscal-inutilizar-modal'),
                View::make('filament.components.erp.nfe.fiscal-inutilizar-sucesso-overlay'),
                View::make('filament.components.erp.nfe.fiscal-erro-overlay'),
                View::make('filament.components.erp.nfe.fiscal-sucesso-overlay'),
                View::make('filament.components.erp.nfe.fiscal-cce-modal'),
                View::make('filament.components.erp.nfe.fiscal-cce-sucesso-overlay'),
                View::make('filament.components.erp.nfe.fiscal-cce-whatsapp-modal'),
                View::make('filament.components.erp.nfe.fiscal-cce-email-modal'),
                View::make('filament.components.erp.nfe.historico-modal'),
                View::make('filament.components.erp.nfe.espelho-modal'),
                View::make('filament.components.erp.nfe.espelho-email-modal'),
                View::make('filament.components.erp.nfe.email-contador-modal'),
            ]);
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function setSearchColumn(string $column): void
    {
        $this->toggleSearchField($column);
    }

    public function updatedSearchColumn(): void
    {
        $this->toggleSearchField($this->searchColumn);
    }

    public function toggleSearchField(string $column): void
    {
        $allowed = $this->localSearchColumns();

        if (! in_array($column, $allowed, true)) {
            return;
        }

        $active = $this->ensureTwoSearchFields($this->normalizedSearchFieldsActive());

        if (in_array($column, $active, true)) {
            $active = array_values(array_filter($active, fn (string $item): bool => $item !== $column));
            $active[] = $column;
            $this->searchFieldsActive = $active;
            $this->searchColumn = $column;

            return;
        }

        $active[] = $column;
        $active = array_values(array_slice($active, -2));

        $this->searchFieldsActive = $active;
        $this->searchColumn = $column;
        $this->pruneLocalSearchByField();

        if (! in_array('cliente', $active, true)) {
            $this->clienteFilter = 'todos';
            $this->closeLocalClienteLookup();
        }

        $hasDate = collect($active)->contains(fn (string $item): bool => $this->isDateSearchColumn($item));

        if ($hasDate) {
            $this->applyCurrentMonthDateFilter();
        } else {
            $this->localSearchDe = '';
            $this->localSearchAte = '';
        }

        $this->syncLegacyLocalSearch();
        $this->clearListSelection();
        $this->resetTable();
        $this->dispatch('erp-masks-refresh');
    }

    /**
     * @param  list<string>  $active
     * @return list<string>
     */
    protected function ensureTwoSearchFields(array $active): array
    {
        $allowed = $this->localSearchColumns();
        $active = array_values(array_unique(array_filter(
            $active,
            fn (mixed $column): bool => is_string($column) && in_array($column, $allowed, true),
        )));

        $defaults = ['cliente', 'data_emissao'];

        foreach ($defaults as $default) {
            if (count($active) >= 2) {
                break;
            }

            if (! in_array($default, $active, true)) {
                $active[] = $default;
            }
        }

        foreach ($allowed as $column) {
            if (count($active) >= 2) {
                break;
            }

            if (! in_array($column, $active, true)) {
                $active[] = $column;
            }
        }

        return array_values(array_slice($active, 0, 2));
    }

    protected function pruneLocalSearchByField(): void
    {
        $active = $this->normalizedSearchFieldsActive();

        foreach (array_keys($this->localSearchByField) as $column) {
            if (! in_array($column, $active, true) || $this->isDateSearchColumn($column)) {
                unset($this->localSearchByField[$column]);
            }
        }
    }

    protected function syncLegacyLocalSearch(): void
    {
        foreach ($this->normalizedSearchFieldsActive() as $column) {
            if ($this->isDateSearchColumn($column)) {
                continue;
            }

            $this->localSearch = (string) ($this->localSearchByField[$column] ?? '');

            return;
        }

        $this->localSearch = '';
    }

    protected function hydrateLocalSearchByFieldFromLegacy(): void
    {
        if (! filled($this->localSearch)) {
            return;
        }

        foreach ($this->searchFieldsActive as $column) {
            if ($this->isDateSearchColumn($column)) {
                continue;
            }

            if (! filled($this->localSearchByField[$column] ?? null)) {
                $this->localSearchByField[$column] = $this->localSearch;
            }

            return;
        }
    }

    /**
     * @return list<string>
     */
    protected function normalizedSearchFieldsActive(): array
    {
        $allowed = $this->localSearchColumns();
        $active = array_values(array_filter(
            $this->searchFieldsActive,
            fn (mixed $column): bool => is_string($column) && in_array($column, $allowed, true),
        ));

        if ($active === []) {
            $fallback = in_array($this->searchColumn, $allowed, true) ? $this->searchColumn : 'cliente';

            return [$fallback];
        }

        return array_values(array_unique($active));
    }

    public function updatedLocalSearchByField(): void
    {
        $clienteTerm = (string) ($this->localSearchByField['cliente'] ?? '');

        if ($this->isLocalClienteSearchColumn()) {
            $this->onLocalClienteSearchTyped($clienteTerm);
        } else {
            $this->closeLocalClienteLookup();
            $this->clienteFilter = 'todos';
        }

        $this->syncLegacyLocalSearch();
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearch(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearchDe(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearchAte(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function search(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function modulePending(string $module): void
    {
        if ($this->nfeModalOpen) {
            $this->showNfeFiscalOverlayInfo($module);

            return;
        }

        Notification::make()
            ->title($module)
            ->body('Em implementação.')
            ->info()
            ->send();
    }
}
