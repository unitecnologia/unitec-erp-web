<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\CompraResource\Pages\Concerns\ManagesCompraContadorEmail;
use App\Filament\Concerns\ManagesImportarXmlModal;
use App\Filament\Resources\CompraResource;
use App\Filament\Resources\ProductResource\Pages\Concerns\ManagesProductPrecificacao;
use App\Models\CaixaConta;
use App\Models\Compra;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\NotaFornecedor;
use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\Compra\CancelarCompraService;
use App\Support\Erp\Compra\CompraLancamentoDraftService;
use App\Support\Erp\Compra\FinalizarCompraLancamentoService;
use App\Support\Erp\Compra\ReabrirCompraLancamentoService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\NotaFornecedor\NotaFornecedorDanfeReportService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListCompras extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use ManagesCompraContadorEmail;
    use ManagesImportarXmlModal;
    use ManagesProductPrecificacao;

    protected static string $resource = CompraResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'fornecedor';

    #[Url(as: 'status')]
    public string $statusFilter = 'todas';

    public bool $lancamentoModalOpen = false;

    public ?int $lancamentoModalCompraId = null;

    public bool $lancamentoRomaneio = false;

    public string $lancamentoRomaneioFornecedorBusca = '';

    /** @var array<int, array{id: int, label: string}> */
    public array $lancamentoRomaneioFornecedores = [];

    public string $lancamentoRomaneioProdutoBusca = '';

    /** @var array<int, array{id: int, codigo: string, descricao: string, referencia: string}> */
    public array $lancamentoRomaneioProdutos = [];

    public int $lancamentoRomaneioProdutoSelecionado = 0;

    public ?int $lancamentoRomaneioProdutoPendenteId = null;

    public string $lancamentoRomaneioQtd = '1,000';

    public string $lancamentoRomaneioValorUnitario = '0,00';

    public string $lancamentoRomaneioTotalItem = '0,00';

    public bool $entradaRomaneioFornecedorModalOpen = false;

    public string $entradaRomaneioFornecedorBusca = '';

    /** @var array<int, array{id: int, label: string}> */
    public array $entradaRomaneioFornecedores = [];

    public string $lancamentoModalStatus = '';

    /** @var array<string, string> */
    public array $lancamentoModalHeader = [];

    /** @var array<int, array<string, string>> */
    public array $lancamentoModalRows = [];

    public string $lancamentoModalValorCompra = '0,00';

    public string $lancamentoModalValorMargemVarejo = '0,00';

    public string $lancamentoModalValorVarejo = '0,00';

    public string $lancamentoModalValorMargemAtacado = '0,00';

    public string $lancamentoModalValorAtacado = '0,00';

    public string $lancamentoModalValorMargemEspecial = '0,00';

    public string $lancamentoModalValorEspecial = '0,00';

    /** @var array<string, string> */
    public array $lancamentoModalTotais = [];

    public string $lancamentoMargemEscopo = 'item';

    public string $lancamentoMargemPercentVarejo = '0,00';

    public string $lancamentoMargemPercentAtacado = '0,00';

    public string $lancamentoMargemPercentEspecial = '0,00';

    public bool $lancamentoParamAjustaPreco = true;

    public bool $lancamentoParamGerarFinanceiro = true;

    public bool $lancamentoParamGeraEstoque = true;

    public ?int $lancamentoModalItemIndex = 0;

    public bool $lancamentoFinalizarConfirmOpen = false;

    public bool $lancamentoParcelasOpen = false;

    public string $lancamentoParcelasSubtotal = '0,00';

    public string $lancamentoParcelasEntrada = '0,00';

    public string $lancamentoParcelasTotal = '0,00';

    public string $lancamentoParcelasQtd = '1';

    public string $lancamentoParcelasIntervalo = '30';

    /**
     * @var list<array{
     *     documento: string,
     *     vencimento: string,
     *     forma_pagamento_id: string,
     *     caixa_conta_id: string,
     *     valor: string
     * }>
     */
    public array $lancamentoParcelasRows = [];

    public ?int $lancamentoParcelasSelectedIndex = null;

    /** Remonta a grade do lançamento (após aplicar margem em lote, etc.). */
    public int $lancamentoGridEpoch = 0;

    /** Evita recalcular/selecionar de novo no blur após Enter. */
    public bool $lancamentoSkipQtdBlur = false;

    public bool $lancamentoSkipTotaisBlur = false;

    /** Evita o wire:model.blur sobrescrever o preço já gravado no Enter. */
    public bool $lancamentoSkipPrecoBlur = false;

    /** Durante Aplicar precificação: não recalcular Mg% a partir do preço. */
    public bool $lancamentoSuppressRowSync = false;

    /** Evita Enter duplicado na Qtd no mesmo request. */
    protected bool $lancamentoQtdCommitting = false;

    /** Stub usado pelo trait ManagesProductPrecificacao. */
    public array $data = [];

    public bool $suppressProductPriceRecalculation = false;

    public ?int $lancamentoPrecificacaoRowIndex = null;

    /** Força remount dos inputs da grade após aplicar precificação. */
    public int $lancamentoGridRevision = 0;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Compras');

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);

        $abrirId = (int) request()->query('abrir_lancamento', 0);
        if ($abrirId > 0) {
            $modo = request()->query('modo_lancamento', 'alterando') === 'visualizar'
                ? 'visualizar'
                : 'alterando';
            $this->openCompraLancamento($abrirId, $modo);
        }
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-compras-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma compra';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-compras__search-text, .erp-compras__search-date-from',
            'searchFocusKey' => 'F8',
            'create' => 'createCompra',
            'edit' => 'editCompra',
            'extraKeys' => [
                'F4' => ['method' => 'cancelCompra'],
                'F6' => ['method' => 'openLerXmlFromCompraSelecionada'],
                'F7' => ['method' => 'reabrirCompra'],
                'F9' => ['method' => 'openCompraContadorEmailModal'],
            ],
        ];
    }

    protected function importarXmlScreenTitle(): string
    {
        return 'Compras';
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'cancel' => 'uma compra para cancelar',
            'reabrir' => 'uma compra fechada para reabrir',
            'ler o XML' => 'uma compra para ler o XML',
            default => $this->defaultErpListSelectPrompt($action),
        };
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(CompraResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['fornecedor'])
            ->withExists([
                'devolucoes as has_devolucao_finalizada' => fn (Builder $query): Builder => $query
                    ->where('situacao', \App\Models\DevolucaoCompra::SITUACAO_FINALIZADA),
            ]);

        $empresaId = ErpContext::currentEmpresaId();

        if ($empresaId !== null) {
            $query->where(function (Builder $empresaQuery) use ($empresaId): void {
                $empresaQuery
                    ->where('empresa_id', $empresaId)
                    ->orWhereNull('empresa_id');
            });
        }

        if ($this->statusFilter !== 'todas') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->isDateSearchColumn()) {
            $this->applyLocalSearchByDateRange($query);
        } elseif (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function isDateSearchColumn(): bool
    {
        return in_array($this->searchColumn, ['data_emissao', 'data_entrada'], true);
    }

    protected function applyLocalSearchByDateRange(Builder $query): void
    {
        if (! filled($this->localSearchDe) && ! filled($this->localSearchAte)) {
            return;
        }

        $column = $this->searchColumn === 'data_entrada' ? 'data_entrada' : 'data_emissao';

        if (filled($this->localSearchDe)) {
            $query->whereDate($column, '>=', $this->localSearchDe);
        }

        if (filled($this->localSearchAte)) {
            $query->whereDate($column, '<=', $this->localSearchAte);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return ['numero', 'data_emissao', 'data_entrada', 'numero_nota', 'fornecedor', 'chave', 'total'];
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)
            ? $this->searchColumn
            : 'fornecedor';

        $like = '%' . $term . '%';

        match ($column) {
            'numero' => $query->where('numero', 'like', $like),
            'numero_nota' => $query->where('numero_nota', 'like', $like),
            'fornecedor' => $query->whereHas('fornecedor', fn (Builder $fornecedorQuery): Builder => $fornecedorQuery->where('nome_razao', 'like', $like)),
            'chave' => $query->where('chave_nfe', 'like', $like),
            'total' => $this->applyLocalSearchByTotal($query, $term),
            default => null,
        };
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
                $query->whereRaw('CAST(total AS TEXT) LIKE ?', ['%' . $normalized . '%']);

                return;
            }

            $query->where('total', 'like', '%' . $normalized . '%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', total), '.', ',') LIKE ?", ['%' . $term . '%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT(total, 2), '.', ',') LIKE ?", ['%' . $term . '%']);
    }

    protected function databaseDriver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
    }

    /**
     * Query de compras restrita à empresa da sessão (aceita legado sem empresa).
     */
    protected function scopedCompraQuery(): Builder
    {
        $query = Compra::query();
        $empresaId = ErpContext::currentEmpresaId();

        if ($empresaId !== null) {
            $query->where(function (Builder $inner) use ($empresaId): void {
                $inner->where('empresa_id', $empresaId)
                    ->orWhereNull('empresa_id');
            });
        }

        return $query;
    }

    #[Computed]
    public function highlightedCompraDevolvida(): bool
    {
        if (! $this->highlightedRecordId) {
            return false;
        }

        return $this->scopedCompraQuery()
            ->whereKey($this->highlightedRecordId)
            ->whereHas(
                'devolucoes',
                fn (Builder $query): Builder => $query->where(
                    'situacao',
                    \App\Models\DevolucaoCompra::SITUACAO_FINALIZADA,
                ),
            )
            ->exists();
    }

    protected function abortIfHighlightedCompraDevolvida(string $acao): bool
    {
        if (! $this->highlightedCompraDevolvida) {
            return false;
        }

        Notification::make()
            ->title('Compra devolvida')
            ->body("Não é possível {$acao} uma compra que possui devolução finalizada.")
            ->warning()
            ->send();

        return true;
    }

    #[Computed]
    public function empresaNome(): string
    {
        $empresaId = ErpContext::currentEmpresaId();

        $empresa = $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : null;

        if (! $empresa) {
            return '—';
        }

        return $empresa->fantasia ?: ($empresa->nome ?: $empresa->razao_social);
    }

    #[Computed]
    public function filteredTotal(): float
    {
        return (float) $this->buildListQuery()->sum('total');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.compras.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.compras.footer-total'),
                View::make('filament.components.erp.compras.action-bar'),
                View::make('filament.components.erp.compras.lancamento-modal'),
                View::make('filament.components.erp.compras.finalizar-confirm-modal'),
                View::make('filament.components.erp.compras.parcelas-modal'),
                View::make('filament.components.erp.produtos.form.precificacao-modal'),
                View::make('filament.components.erp.notas-fornecedores.importar-xml-modal'),
                View::make('filament.components.erp.notas-fornecedores.product-overlay'),
                View::make('filament.components.erp.compras.email-contador-modal'),
            ]);
    }

    public function openCompraLancamento(int $compraId, string $modo = 'visualizar'): void
    {
        $compra = $this->scopedCompraQuery()
            ->with(['itens.product', 'fornecedor'])
            ->find($compraId);

        if (! $compra) {
            Notification::make()
                ->title('Compra não encontrada.')
                ->danger()
                ->send();

            return;
        }

        $modo = in_array($modo, ['visualizar', 'alterando'], true) ? $modo : 'visualizar';

        if ($modo === 'alterando' && $compra->status === Compra::STATUS_CANCELADA) {
            Notification::make()
                ->title('Compra cancelada')
                ->body('Não é possível alterar uma compra cancelada.')
                ->warning()
                ->send();

            return;
        }

        $status = $modo === 'alterando'
            ? 'ALTERANDO'
            : mb_strtoupper(Compra::statusLabels()[$compra->status] ?? $compra->status, 'UTF-8');

        $nfeKeyParts = $this->extractNfeKeyParts($compra->chave_nfe);

        $this->lancamentoModalCompraId = $compra->id;
        $this->lancamentoRomaneio = $this->isCompraRomaneio($compra);
        $this->lancamentoModalStatus = $status;
        $this->lancamentoModalHeader = [
            'numero' => $this->formatCompraNumero($compra->numero),
            'empresa' => $this->empresaNome,
            'fornecedor' => $compra->fornecedor?->nome_razao ?? '—',
            'uf' => $compra->fornecedor?->uf ?: '—',
            'cnpj' => $this->formatCpfCnpj($compra->fornecedor?->cpf_cnpj),
            'chave' => $compra->chave_nfe ?: '—',
            'nota' => $compra->numero_nota ?: '—',
            'modelo' => $nfeKeyParts['modelo'],
            'serie' => $nfeKeyParts['serie'],
            'data_emissao' => $compra->data_emissao?->format('d/m/Y') ?? '—',
            'data_entrada' => $compra->data_entrada?->format('d/m/Y') ?? '—',
        ];
        $this->lancamentoModalRows = $this->buildLancamentoModalRows($compra);
        $this->lancamentoModalTotais = $this->buildLancamentoModalTotais($compra);
        $this->lancamentoMargemEscopo = 'item';
        $this->lancamentoMargemPercentVarejo = '0,00';
        $this->lancamentoMargemPercentAtacado = '0,00';
        $this->lancamentoMargemPercentEspecial = '0,00';
        $this->lancamentoParamAjustaPreco = true;
        $this->lancamentoParamGerarFinanceiro = true;
        $this->lancamentoParamGeraEstoque = true;
        $this->lancamentoModalItemIndex = 0;
        $this->lancamentoGridEpoch++;
        $this->aplicarRateioExtrasNoVlCusto();

        if ($modo === 'alterando') {
            $this->restoreLancamentoDraftIfAny($compra);
        }

        $this->lancamentoModalOpen = true;
    }

    public function openEntradaRomaneio(): void
    {
        if (! $this->erpAuthorizeOrNotify('compras.create')) {
            return;
        }

        $empresaId = ErpContext::currentEmpresaId();
        $fornecedor = Person::query()
            ->where('ativo', true)
            ->where('is_fornecedor', true)
            ->whereRaw('UPPER(TRIM(nome_razao)) = ?', ['CONSUMIDOR FINAL'])
            ->first();

        if (! $empresaId || ! $fornecedor) {
            $this->entradaRomaneioFornecedorBusca = '';
            $this->entradaRomaneioFornecedores = [];
            $this->entradaRomaneioFornecedorModalOpen = true;

            return;
        }

        $this->iniciarEntradaRomaneio((int) $fornecedor->id);
    }

    public function updatedEntradaRomaneioFornecedorBusca(): void
    {
        $term = trim($this->entradaRomaneioFornecedorBusca);

        if (mb_strlen($term) < 2) {
            $this->entradaRomaneioFornecedores = [];

            return;
        }

        $like = '%'.mb_strtoupper($term, 'UTF-8').'%';
        $this->entradaRomaneioFornecedores = Person::query()
            ->where('ativo', true)
            ->where('is_fornecedor', true)
            ->where('nome_razao', 'like', $like)
            ->orderBy('nome_razao')
            ->limit(12)
            ->get()
            ->map(fn (Person $pessoa): array => [
                'id' => (int) $pessoa->id,
                'label' => (string) $pessoa->nome_razao,
            ])
            ->all();
    }

    public function cancelarEntradaRomaneio(): void
    {
        $this->entradaRomaneioFornecedorModalOpen = false;
        $this->entradaRomaneioFornecedorBusca = '';
        $this->entradaRomaneioFornecedores = [];
    }

    public function iniciarEntradaRomaneio(int $fornecedorId): void
    {
        $empresaId = ErpContext::currentEmpresaId();
        $fornecedor = Person::query()
            ->whereKey($fornecedorId)
            ->where('ativo', true)
            ->where('is_fornecedor', true)
            ->first();

        if (! $empresaId || ! $fornecedor) {
            Notification::make()
                ->title('Fornecedor ou empresa não identificado.')
                ->warning()
                ->send();

            return;
        }

        $compra = Compra::query()->create([
            'empresa_id' => $empresaId,
            'fornecedor_id' => $fornecedor->id,
            'numero' => Compra::nextNumero(),
            'numero_nota' => 'ROMANEIO',
            'data_emissao' => now()->toDateString(),
            'data_entrada' => now()->toDateString(),
            'total' => 0,
            'status' => Compra::STATUS_ABERTA,
        ]);

        $this->lancamentoRomaneio = true;
        $this->lancamentoRomaneioFornecedorBusca = (string) $fornecedor->nome_razao;
        $this->lancamentoRomaneioFornecedores = [];
        $this->lancamentoRomaneioProdutoBusca = '';
        $this->lancamentoRomaneioProdutos = [];
        $this->cancelarEntradaRomaneio();

        $this->openCompraLancamento((int) $compra->id, 'alterando');
        $this->lancamentoRomaneio = true;
        $this->lancamentoModalStatus = 'ROMANEIO';
        $this->lancamentoModalHeader['nota'] = 'ROMANEIO';
        $this->lancamentoModalHeader['modelo'] = '—';
        $this->lancamentoModalHeader['serie'] = '—';
    }

    public function updatedLancamentoRomaneioFornecedorBusca(): void
    {
        $term = trim($this->lancamentoRomaneioFornecedorBusca);

        if (mb_strlen($term) < 2) {
            $this->lancamentoRomaneioFornecedores = [];

            return;
        }

        $like = '%'.mb_strtoupper($term, 'UTF-8').'%';
        $this->lancamentoRomaneioFornecedores = Person::query()
            ->where('ativo', true)
            ->where('is_fornecedor', true)
            ->where('nome_razao', 'like', $like)
            ->orderBy('nome_razao')
            ->limit(12)
            ->get()
            ->map(fn (Person $pessoa): array => [
                'id' => (int) $pessoa->id,
                'label' => (string) $pessoa->nome_razao,
            ])
            ->all();
    }

    public function selectLancamentoRomaneioFornecedor(int $fornecedorId): void
    {
        if (! $this->lancamentoModalCompraId || ! $this->lancamentoRomaneio) {
            return;
        }

        $fornecedor = Person::query()
            ->whereKey($fornecedorId)
            ->where('ativo', true)
            ->where('is_fornecedor', true)
            ->first();

        if (! $fornecedor) {
            Notification::make()->title('Fornecedor não encontrado.')->warning()->send();

            return;
        }

        $compra = $this->scopedCompraQuery()->find($this->lancamentoModalCompraId);
        if (! $compra || $compra->status !== Compra::STATUS_ABERTA) {
            return;
        }

        $compra->update(['fornecedor_id' => $fornecedor->id]);
        $this->lancamentoRomaneioFornecedorBusca = (string) $fornecedor->nome_razao;
        $this->lancamentoRomaneioFornecedores = [];
        $this->lancamentoModalHeader['fornecedor'] = (string) $fornecedor->nome_razao;
        $this->lancamentoModalHeader['uf'] = (string) ($fornecedor->uf ?: '—');
        $this->lancamentoModalHeader['cnpj'] = $this->formatCpfCnpj($fornecedor->cpf_cnpj);
    }

    public function updatedLancamentoRomaneioProdutoBusca(): void
    {
        $term = trim($this->lancamentoRomaneioProdutoBusca);

        if (mb_strlen($term) < 2) {
            $this->lancamentoRomaneioProdutos = [];
            $this->lancamentoRomaneioProdutoSelecionado = 0;

            return;
        }

        $like = '%'.mb_strtoupper($term, 'UTF-8').'%';
        $this->lancamentoRomaneioProdutos = Product::query()
            ->where(function (Builder $query) use ($like): void {
                $query->where('descricao', 'like', $like)
                    ->orWhere('codigo', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like);
            })
            ->orderBy('descricao')
            ->limit(15)
            ->get()
            ->map(fn (Product $produto): array => [
                'id' => (int) $produto->id,
                'codigo' => (string) ($produto->codigo ?? ''),
                'descricao' => (string) $produto->descricao,
                'referencia' => (string) ($produto->referencia ?? ''),
                'preco_compra' => $this->romaneioPrecoCompra($produto),
            ])
            ->all();
        $this->lancamentoRomaneioProdutoSelecionado = 0;
    }

    public function moveLancamentoRomaneioProdutoSelecionado(int $direction): void
    {
        $count = count($this->lancamentoRomaneioProdutos);

        if ($count === 0) {
            return;
        }

        $this->lancamentoRomaneioProdutoSelecionado = max(
            0,
            min($count - 1, $this->lancamentoRomaneioProdutoSelecionado + $direction),
        );
    }

    public function selectLancamentoRomaneioProduto(int $productId): void
    {
        $produto = Product::query()->find($productId);

        if (! $produto) {
            return;
        }

        $this->lancamentoRomaneioProdutoPendenteId = (int) $produto->id;
        $this->lancamentoRomaneioProdutoBusca = trim(implode(' — ', array_filter([
            $produto->codigo,
            $produto->descricao,
        ])));
        $this->lancamentoRomaneioProdutos = [];
        $this->lancamentoRomaneioQtd = '1,000';
        $this->lancamentoRomaneioValorUnitario = number_format($this->romaneioPrecoCompra($produto), 2, ',', '.');
        $this->recalcularLancamentoRomaneioTotalItem();
        $this->dispatch('erp-masks-refresh');
        $this->dispatch('erp-compra-romaneio-focus-qtd');
    }

    public function updatedLancamentoRomaneioQtd(): void
    {
        $this->recalcularLancamentoRomaneioTotalItem();
    }

    public function updatedLancamentoRomaneioValorUnitario(): void
    {
        $this->recalcularLancamentoRomaneioTotalItem();
    }

    public function focarLancamentoRomaneioValorAposQtd(): void
    {
        if ($this->lancamentoRomaneioProdutoPendenteId === null) {
            $this->confirmarLancamentoRomaneioProduto();

            return;
        }

        $quantidade = BrDecimal::parse($this->lancamentoRomaneioQtd, 3);
        if ($quantidade <= 0) {
            Notification::make()->title('Quantidade inválida.')->warning()->send();
            $this->dispatch('erp-compra-romaneio-focus-qtd');

            return;
        }

        $this->lancamentoRomaneioQtd = number_format($quantidade, 3, ',', '.');
        $this->recalcularLancamentoRomaneioTotalItem();
        $this->dispatch('erp-compra-romaneio-focus-valor');
    }

    public function confirmarLancamentoRomaneioProduto(): void
    {
        $term = mb_strtoupper(trim($this->lancamentoRomaneioProdutoBusca), 'UTF-8');

        if ($term === '') {
            $this->dispatch('erp-compra-romaneio-focus-produto');

            return;
        }

        if ($this->lancamentoRomaneioProdutoPendenteId !== null) {
            $this->dispatch('erp-compra-romaneio-focus-qtd');

            return;
        }

        $produto = Product::query()
            ->where('codigo', $term)
            ->orWhere('codigo_barras', $term)
            ->orWhere('referencia', $term)
            ->first();

        if ($produto) {
            $this->selectLancamentoRomaneioProduto((int) $produto->id);

            return;
        }

        if ($this->lancamentoRomaneioProdutos !== []) {
            $this->selectLancamentoRomaneioProduto(
                (int) ($this->lancamentoRomaneioProdutos[$this->lancamentoRomaneioProdutoSelecionado]['id']
                    ?? $this->lancamentoRomaneioProdutos[0]['id']),
            );

            return;
        }

        $this->updatedLancamentoRomaneioProdutoBusca();

        if ($this->lancamentoRomaneioProdutos !== []) {
            $this->lancamentoRomaneioProdutoSelecionado = 0;
            $this->dispatch('erp-compra-romaneio-focus-produto');

            return;
        }

        Notification::make()->title('Produto não encontrado.')->warning()->send();
        $this->dispatch('erp-compra-romaneio-focus-produto');
    }

    public function confirmarLancamentoRomaneioValor(): void
    {
        if ($this->lancamentoRomaneioProdutoPendenteId === null) {
            $this->confirmarLancamentoRomaneioProduto();

            return;
        }

        $valor = BrDecimal::parse($this->lancamentoRomaneioValorUnitario, 2);
        if ($valor <= 0) {
            Notification::make()->title('Informe o valor de compra.')->warning()->send();
            $this->dispatch('erp-compra-romaneio-focus-valor');

            return;
        }

        $this->lancamentoRomaneioValorUnitario = number_format($valor, 2, ',', '.');
        $this->addLancamentoRomaneioProduto($this->lancamentoRomaneioProdutoPendenteId);
    }

    public function addLancamentoRomaneioProduto(int $productId): void
    {
        if (! $this->lancamentoModalCompraId || ! $this->lancamentoRomaneio) {
            return;
        }

        $produto = Product::query()->find($productId);
        $compra = $this->scopedCompraQuery()->find($this->lancamentoModalCompraId);

        if (! $produto || ! $compra || $compra->status !== Compra::STATUS_ABERTA) {
            return;
        }

        $quantidade = max(0.001, BrDecimal::parse($this->lancamentoRomaneioQtd, 3));
        $custo = max(0, BrDecimal::parse($this->lancamentoRomaneioValorUnitario, 2));
        $itemExistente = $compra->itens()
            ->where('product_id', $produto->id)
            ->first();

        if ($itemExistente) {
            $novaQuantidade = round((float) $itemExistente->quantidade + $quantidade, 3);
            $itemExistente->update([
                'quantidade' => $novaQuantidade,
                'valor_unitario' => $custo,
                'total' => round($novaQuantidade * $custo, 2),
            ]);
        } else {
            $compra->itens()->create([
                'product_id' => $produto->id,
                'quantidade' => $quantidade,
                'valor_unitario' => $custo,
                'total' => round($quantidade * $custo, 2),
            ]);
        }

        $compra->refresh()->load(['itens' => fn ($query) => $query->latest('id')->with('product')]);
        $this->lancamentoModalRows = $this->buildLancamentoModalRows($compra);
        $this->lancamentoModalTotais = $this->buildLancamentoModalTotais($compra);
        $this->sincronizarTotalRomaneio();
        $this->lancamentoModalItemIndex = 0;
        $this->lancamentoGridEpoch++;
        $this->lancamentoRomaneioProdutoBusca = '';
        $this->lancamentoRomaneioProdutos = [];
        $this->lancamentoRomaneioProdutoPendenteId = null;
        $this->lancamentoRomaneioQtd = '1,000';
        $this->lancamentoRomaneioValorUnitario = '0,00';
        $this->lancamentoRomaneioTotalItem = '0,00';
        $this->dispatch('erp-masks-refresh');
        $this->dispatch('erp-compra-romaneio-focus-produto');
    }

    protected function recalcularLancamentoRomaneioTotalItem(): void
    {
        $quantidade = max(0, BrDecimal::parse($this->lancamentoRomaneioQtd, 3));
        $unitario = max(0, BrDecimal::parse($this->lancamentoRomaneioValorUnitario, 2));
        $this->lancamentoRomaneioTotalItem = number_format(round($quantidade * $unitario, 2), 2, ',', '.');
    }

    protected function romaneioPrecoCompra(Product $produto): float
    {
        return max(0, (float) ($produto->preco_compra ?: $produto->preco_custo ?: $produto->ult_compra));
    }

    protected function isCompraRomaneio(Compra $compra): bool
    {
        return mb_strtoupper(trim((string) $compra->numero_nota), 'UTF-8') === 'ROMANEIO';
    }

    public function selectLancamentoItem(int $index): void
    {
        if (! isset($this->lancamentoModalRows[$index])) {
            return;
        }

        $this->lancamentoSkipPrecoBlur = false;

        if ($this->lancamentoModalItemIndex === $index) {
            $this->skipRender();

            return;
        }

        $this->lancamentoModalItemIndex = $index;
        $this->refreshLancamentoMargemFromRows();
    }

    public function openLancamentoPrecificacao(int $index): void
    {
        if ($this->lancamentoModalStatus === 'FECHADA' || $this->lancamentoModalStatus === 'CANCELADA') {
            Notification::make()
                ->title('Compra não editável')
                ->body('Abra a compra em modo Alterando para precificar.')
                ->warning()
                ->send();

            return;
        }

        if (! isset($this->lancamentoModalRows[$index])) {
            return;
        }

        $row = $this->lancamentoModalRows[$index];
        $productId = (int) ($row['product_id'] ?? 0);
        $product = $productId > 0 ? Product::query()->find($productId) : null;

        if (! $product) {
            Notification::make()
                ->title('Produto não vinculado')
                ->body('Não é possível precificar um item sem produto.')
                ->warning()
                ->send();

            return;
        }

        $vlCustoBase = isset($row['vl_custo_base'])
            ? (float) $row['vl_custo_base']
            : BrDecimal::parse($row['vl_custo'] ?? 0, 4);
        $vlCusto = BrDecimal::parse($row['vl_custo'] ?? 0, 4);
        if ($vlCustoBase <= 0) {
            $vlCustoBase = $vlCusto > 0
                ? $vlCusto
                : (float) ($product->preco_compra ?? $product->preco_custo ?? 0);
        }
        if ($vlCusto <= 0) {
            $vlCusto = (float) ($product->preco_custo ?? $product->preco_compra ?? $vlCustoBase);
        }

        $extrasUnitarios = $this->lancamentoExtrasUnitariosDaLinha($row);

        $this->lancamentoPrecificacaoRowIndex = $index;
        $this->lancamentoModalItemIndex = $index;

        $snapshot = $row['precificacao_snapshot'] ?? null;
        if (is_array($snapshot) && $snapshot !== [] && isset($snapshot['niveis'])) {
            $this->precificacao = $snapshot;
            $this->resetPrecificacaoEnterGuard();
            // Mg% da grade e Margem da modal são o mesmo campo: alinha a modal à grade.
            $this->sincronizarMargemModalComGradeLancamento($index);
            $this->touchPrecificacao();
            $this->productPrecificacaoOpen = true;
            $this->dispatch('erp-precif-focus', id: 'precif-compra');

            return;
        }

        $this->openProductPrecificacaoFromData([
            'codigo' => (string) ($product->codigo ?? ''),
            'codigo_barras' => (string) ($product->codigo_barras ?? ''),
            'referencia' => (string) ($product->referencia ?? ''),
            'descricao' => (string) ($product->descricao ?? $row['produto'] ?? ''),
            // Pr. Compra = custo da nota (sem extras); Frete/Seguro/Outras vêm do rateio.
            'preco_compra' => $vlCustoBase,
            'preco_custo' => $vlCusto,
            'frete_rs' => $extrasUnitarios['frete'],
            'seguro_rs' => $extrasUnitarios['seguro'],
            'outras_desp' => $extrasUnitarios['outras'],
            'pct_custos' => (float) ($product->pct_custos ?? 0),
            // Mg% da própria linha do lançamento (não o % antigo do cadastro).
            'pct_lucro' => BrDecimal::parse($row['margem_varejo'] ?? $product->pct_lucro ?? 0, 2),
            'preco_venda' => BrDecimal::parse($row['preco_venda'] ?? $product->preco_venda ?? 0, 2),
            'preco_atacado' => BrDecimal::parse($row['preco_atacado'] ?? $product->preco_atacado ?? 0, 2),
            'preco_especial' => BrDecimal::parse($row['preco_especial'] ?? $product->preco_especial ?? 0, 2),
        ]);
    }

    /**
     * Frete / Seguro / Outras (unitário) da linha, a partir do rateio dos totais do lançamento.
     *
     * @param  array<string, mixed>  $row
     * @return array{frete: float, seguro: float, outras: float}
     */
    protected function lancamentoExtrasUnitariosDaLinha(array $row): array
    {
        $empty = ['frete' => 0.0, 'seguro' => 0.0, 'outras' => 0.0];
        $qtd = (float) ($row['qtd_num'] ?? 0);
        $totalLinha = (float) ($row['total_num'] ?? 0);
        $baseCusto = isset($row['vl_custo_base'])
            ? (float) $row['vl_custo_base']
            : BrDecimal::parse($row['vl_custo'] ?? 0, 4);
        $custoAtual = BrDecimal::parse($row['vl_custo'] ?? 0, 4);

        $freteTotal = BrDecimal::parse($this->lancamentoModalTotais['frete'] ?? 0, 2);
        $seguroTotal = BrDecimal::parse($this->lancamentoModalTotais['seguro'] ?? 0, 2);
        $outrasTotal = BrDecimal::parse($this->lancamentoModalTotais['outras'] ?? 0, 2);
        $extrasTotal = round($freteTotal + $seguroTotal + $outrasTotal, 2);

        if ($extrasTotal <= 0 || $qtd <= 0) {
            return $empty;
        }

        // Preferência: diferença já rateada no V. Custo (bate com a grade).
        $extrasUnit = max(0.0, round($custoAtual - $baseCusto, 4));
        if ($extrasUnit <= 0) {
            $baseSum = 0.0;
            foreach ($this->lancamentoModalRows as $r) {
                $baseSum += (float) ($r['total_num'] ?? 0);
            }
            if ($baseSum <= 0) {
                return $empty;
            }
            $extrasUnit = round(($extrasTotal * ($totalLinha / $baseSum)) / $qtd, 4);
        }

        $freteUnit = round($extrasUnit * ($freteTotal / $extrasTotal), 2);
        $seguroUnit = round($extrasUnit * ($seguroTotal / $extrasTotal), 2);
        $outrasUnit = round($extrasUnit - $freteUnit - $seguroUnit, 2);

        return [
            'frete' => max(0.0, $freteUnit),
            'seguro' => max(0.0, $seguroUnit),
            'outras' => max(0.0, $outrasUnit),
        ];
    }

    public function aplicarProductPrecificacao(): void
    {
        if ($this->lancamentoPrecificacaoRowIndex === null
            || ! isset($this->lancamentoModalRows[$this->lancamentoPrecificacaoRowIndex])) {
            Notification::make()
                ->title('Não foi possível aplicar a precificação.')
                ->body('Abra a precificação pela linha do lançamento e tente novamente.')
                ->danger()
                ->send();

            return;
        }

        // Alinha praticado/sugerido à margem digitada (mesmo sem Enter/blur antes do Aplicar).
        foreach (['varejo', 'atacado', 'especial'] as $nivel) {
            $this->recalcularNivelPrecificacao($nivel, 'margem');
        }

        // Lançamento: não recalcular custo pelas linhas % da modal (muitas vezes 0
        // enquanto a margem/praticado já estão preenchidos) — isso zerava o Aplicar.
        $this->applyPrecificacaoToHost();
        $this->closeProductPrecificacao();

        Notification::make()
            ->title('Precificação aplicada.')
            ->body($this->precificacaoAplicadaBody())
            ->success()
            ->send();

        $this->handlePrecificacaoReplicaAposAplicar();
    }

    protected function applyPrecificacaoToHost(): void
    {
        $index = $this->lancamentoPrecificacaoRowIndex;
        if ($index === null || ! isset($this->lancamentoModalRows[$index])) {
            return;
        }

        $custo = BrDecimal::parse($this->precificacao['preco_custo'] ?? 0, 2);
        $margemVarejo = BrDecimal::parse($this->precificacao['niveis']['varejo']['margem'] ?? 0, 2);
        $varejo = BrDecimal::parse($this->precificacao['niveis']['varejo']['praticado'] ?? 0, 2);
        $atacado = BrDecimal::parse($this->precificacao['niveis']['atacado']['praticado'] ?? 0, 2);
        $especial = BrDecimal::parse($this->precificacao['niveis']['especial']['praticado'] ?? 0, 2);

        // Se o praticado não sincronizou no Livewire, monta a partir da margem + custo.
        if ($varejo <= 0 && $margemVarejo > 0 && $custo > 0) {
            $varejo = round($custo * (1 + ($margemVarejo / 100)), 2);
        }

        $this->lancamentoSuppressRowSync = true;

        try {
            // Snapshot da formação completa (frete/comissao/margem da modal) para reabrir igual.
            $this->lancamentoModalRows[$index]['precificacao_snapshot'] = json_decode(
                json_encode($this->precificacao),
                true
            ) ?? [];

            if ($custo > 0) {
                $this->lancamentoModalRows[$index]['vl_custo'] = number_format($custo, 2, ',', '.');
            }

            $this->lancamentoModalRows[$index]['preco_venda'] = number_format(max(0, $varejo), 2, ',', '.');
            $this->lancamentoModalRows[$index]['preco_venda_base'] = number_format(max(0, $varejo), 2, '.', '');
            $this->lancamentoModalRows[$index]['preco_atacado'] = number_format(max(0, $atacado), 2, ',', '.');
            $this->lancamentoModalRows[$index]['preco_especial'] = number_format(max(0, $especial), 2, ',', '.');

            // Mg% da grade = mesmas % gravadas na precificação (varejo/atacado/especial).
            $margemAtacado = BrDecimal::parse($this->precificacao['niveis']['atacado']['margem'] ?? 0, 2);
            $margemEspecial = BrDecimal::parse($this->precificacao['niveis']['especial']['margem'] ?? 0, 2);
            $this->lancamentoModalRows[$index]['margem_varejo'] = number_format(max(0, $margemVarejo), 2, ',', '.');
            $this->lancamentoModalRows[$index]['margem_atacado'] = number_format(max(0, $margemAtacado), 2, ',', '.');
            $this->lancamentoModalRows[$index]['margem_especial'] = number_format(max(0, $margemEspecial), 2, ',', '.');
        } finally {
            $this->lancamentoSuppressRowSync = false;
        }

        $this->lancamentoGridRevision++;
        $this->refreshLancamentoMargemFromRows();
        $this->lancamentoPrecificacaoRowIndex = null;
        $this->dispatch('erp-masks-refresh');
        $this->autosaveLancamentoDraft();
    }

    /**
     * Copia Mg% da grade para a Margem da modal e recalcula os níveis (mesmo campo).
     */
    protected function sincronizarMargemModalComGradeLancamento(int $index): void
    {
        if (! isset($this->lancamentoModalRows[$index], $this->precificacao['niveis'])) {
            return;
        }

        $mapa = [
            'varejo' => 'margem_varejo',
            'atacado' => 'margem_atacado',
            'especial' => 'margem_especial',
        ];

        foreach ($mapa as $nivel => $campoGrade) {
            if (! isset($this->precificacao['niveis'][$nivel])) {
                continue;
            }

            $margemGrade = BrDecimal::parse($this->lancamentoModalRows[$index][$campoGrade] ?? 0, 2);
            $this->precificacao['niveis'][$nivel]['margem'] = $this->formatBrDecimal($margemGrade, 2);
            $this->recalcularNivelPrecificacao($nivel, 'margem');
        }
    }

    protected function precificacaoAplicadaBody(): string
    {
        return 'Preços atualizados no lançamento. Finalize a compra para gravar no cadastro.';
    }

    protected function handlePrecificacaoReplicaAposAplicar(): void
    {
        // Lançamento não replica preços entre empresas.
    }

    public function closeProductPrecificacao(): void
    {
        $this->productPrecificacaoOpen = false;
        $this->precificacao = [];
        $this->lancamentoPrecificacaoRowIndex = null;
        $this->resetPrecificacaoEnterGuard();
        $this->dispatch('erp-precif-reset');
    }

    protected function formatBrDecimal(mixed $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return $decimals > 0 ? '0,'.str_repeat('0', $decimals) : '0';
        }

        $number = is_numeric($value) && ! is_string($value)
            ? (float) $value
            : BrDecimal::parse($value, $decimals);

        return number_format($number, $decimals, ',', '.');
    }

    /** Margem % = (preço ÷ vL.custo × 100) − 100 */
    public function lancamentoMargemPctLabel(mixed $preco, mixed $vlCusto): string
    {
        $custo = BrDecimal::parse($vlCusto ?? 0, 4);
        $valor = BrDecimal::parse($preco ?? 0, 2);

        if ($custo <= 0 || $valor <= 0) {
            return '0,00';
        }

        return number_format(round((($valor * 100) / $custo) - 100, 2), 2, ',', '.');
    }

    public function recalcularVlCustoLancamentoItem(int $index): void
    {
        if ($this->lancamentoSkipQtdBlur) {
            $this->lancamentoSkipQtdBlur = false;

            return;
        }

        $this->commitQtdAndGoNext(
            $index,
            (string) ($this->lancamentoModalRows[$index]['qtd'] ?? '0')
        );
    }

    /**
     * Enter na grade (via wire:keydown.enter) — grava e não remonta (foco no JS).
     */
    public function lancamentoGridEnter(int $index, string $col, string $value): void
    {
        if ($this->lancamentoModalStatus === 'FECHADA' || $this->lancamentoModalStatus === 'CANCELADA') {
            return;
        }

        if (! isset($this->lancamentoModalRows[$index])) {
            return;
        }

        $next = $this->proximoCampoLancamento($col, $index);

        if ($col === 'qtd') {
            $this->commitQtdAndGoNext($index, $value);
        } else {
            $this->commitLancamentoPrecoAndGoNext(
                $index,
                $col,
                $value,
                $next['col'] ?? $col,
                $next['index'] ?? $index,
            );
        }

        if ($next !== null) {
            $this->dispatch('erp-lanc-focus', col: $next['col'], index: $next['index']);
        }
    }

    /**
     * @return array{col: string, index: int}|null
     */
    protected function proximoCampoLancamento(string $col, int $rowIndex): ?array
    {
        $last = count($this->lancamentoModalRows) - 1;
        $existe = fn (int $i): bool => $i >= 0 && $i <= $last;

        return match ($col) {
            'qtd' => ['col' => 'mg_venda', 'index' => $rowIndex],
            'mg_venda' => ['col' => 'venda', 'index' => $rowIndex],
            'venda' => $existe($rowIndex + 1)
                ? ['col' => 'mg_venda', 'index' => $rowIndex + 1]
                : ($last >= 0 ? ['col' => 'mg_atacado', 'index' => 0] : null),
            'mg_atacado' => ['col' => 'atacado', 'index' => $rowIndex],
            'atacado' => $existe($rowIndex + 1)
                ? ['col' => 'mg_atacado', 'index' => $rowIndex + 1]
                : ($last >= 0 ? ['col' => 'mg_especial', 'index' => 0] : null),
            'mg_especial' => ['col' => 'especial', 'index' => $rowIndex],
            'especial' => $existe($rowIndex + 1)
                ? ['col' => 'mg_especial', 'index' => $rowIndex + 1]
                : null,
            default => null,
        };
    }

    /**
     * Enter/blur em Qtd.Compra: formata 0,000 e recalcula V. Custo = Preço ÷ Qtd (+ frete).
     * Não mexe em preços de venda — só quantidade, custo e Mg% derivada.
     */
    public function commitQtdAndGoNext(int $index, string $qtd): void
    {
        if (! isset($this->lancamentoModalRows[$index])) {
            return;
        }

        if ($this->lancamentoModalStatus === 'FECHADA' || $this->lancamentoModalStatus === 'CANCELADA') {
            return;
        }

        // Evita commit duplo no mesmo request (vários listeners de Enter).
        if ($this->lancamentoQtdCommitting) {
            return;
        }
        $this->lancamentoQtdCommitting = true;

        $this->lancamentoSkipQtdBlur = true;
        $this->lancamentoModalRows[$index]['qtd'] = $qtd;
        $this->aplicarRecalculoVlCustoItem($index);
        $this->aplicarRateioExtrasNoVlCusto();
        $this->sincronizarTotalRomaneio();
        $this->lancamentoModalItemIndex = $index;
        // Remonta o tbody para refletir 8,000 e o V. Custo diluído na tela.
        $this->lancamentoGridEpoch++;
        $this->refreshLancamentoMargemFromRows();
        $this->dispatch('erp-masks-refresh');
        $this->autosaveLancamentoDraft();
    }

    /**
     * Enter em Varejo/Atacado/Especial ou Mg%: formata, sincroniza preço↔margem e foca o próximo.
     */
    public function commitLancamentoPrecoAndGoNext(
        int $index,
        string $col,
        string $value,
        string $nextCol,
        int $nextIndex,
    ): void {
        if ($this->lancamentoModalStatus === 'FECHADA' || $this->lancamentoModalStatus === 'CANCELADA') {
            return;
        }

        if (! isset($this->lancamentoModalRows[$index])) {
            return;
        }

        $this->lancamentoSkipPrecoBlur = true;

        if (str_starts_with($col, 'mg_')) {
            $nivel = match ($col) {
                'mg_venda' => 'varejo',
                'mg_atacado' => 'atacado',
                'mg_especial' => 'especial',
                default => null,
            };

            if ($nivel === null) {
                return;
            }

            $this->aplicarMargemPctNaLinha($index, $nivel, $value);
        } else {
            $campo = match ($col) {
                'venda' => 'preco_venda',
                'atacado' => 'preco_atacado',
                'especial' => 'preco_especial',
                default => null,
            };

            if ($campo === null) {
                return;
            }

            $parsed = BrDecimal::parse($value, 2);
            $formatted = number_format($parsed, 2, ',', '.');
            $this->lancamentoModalRows[$index][$campo] = $formatted;

            if ($col === 'venda') {
                $this->lancamentoModalRows[$index]['preco_venda_base'] = number_format($parsed, 2, '.', '');
            }

            $this->sincronizarMargemDaLinha($index, $col);
        }

        $focusIndex = isset($this->lancamentoModalRows[$nextIndex]) ? $nextIndex : $index;
        $this->lancamentoModalItemIndex = $focusIndex;
        $this->refreshLancamentoMargemFromRows();
        $this->autosaveLancamentoDraft();
        // Sem remount: o JS já atualizou o DOM e o foco — evita travada no Enter
        $this->skipRender();
    }

    public function updatedLancamentoModalRows(mixed $value, ?string $key = null): void
    {
        if ($key === null || $this->lancamentoSuppressRowSync) {
            return;
        }

        if (preg_match('/^(\d+)\.(margem_varejo|margem_atacado|margem_especial)$/', $key, $matches)) {
            $index = (int) $matches[1];
            $field = $matches[2];

            if (! isset($this->lancamentoModalRows[$index])) {
                return;
            }

            if ($this->lancamentoSkipPrecoBlur) {
                $this->lancamentoSkipPrecoBlur = false;
                $this->skipRender();

                return;
            }

            $nivel = match ($field) {
                'margem_varejo' => 'varejo',
                'margem_atacado' => 'atacado',
                'margem_especial' => 'especial',
                default => null,
            };

            if ($nivel === null) {
                return;
            }

            $this->aplicarMargemPctNaLinha($index, $nivel, (string) $value);
            $this->lancamentoModalItemIndex = $index;
            $this->refreshLancamentoMargemFromRows();
            $this->dispatch('erp-masks-refresh');
            $this->autosaveLancamentoDraft();

            return;
        }

        if (! preg_match('/^(\d+)\.(preco_venda|preco_atacado|preco_especial)$/', $key, $matches)) {
            return;
        }

        $index = (int) $matches[1];
        $field = $matches[2];

        if (! isset($this->lancamentoModalRows[$index])) {
            return;
        }

        if ($this->lancamentoSkipPrecoBlur) {
            $this->lancamentoSkipPrecoBlur = false;
            $this->skipRender();

            return;
        }

        $parsed = BrDecimal::parse($value, 2);
        $formatted = number_format($parsed, 2, ',', '.');
        $this->lancamentoModalRows[$index][$field] = $formatted;

        if ($field === 'preco_venda') {
            $this->lancamentoModalRows[$index]['preco_venda_base'] = number_format($parsed, 2, '.', '');
            $this->sincronizarMargemDaLinha($index, 'venda');
            $this->sincronizarMargemDaLinha($index, 'atacado');
            $this->sincronizarMargemDaLinha($index, 'especial');
        } else {
            $col = match ($field) {
                'preco_atacado' => 'atacado',
                'preco_especial' => 'especial',
                default => null,
            };

            if ($col !== null) {
                $this->sincronizarMargemDaLinha($index, $col);
            }
        }

        $this->lancamentoModalItemIndex = $index;
        $this->refreshLancamentoMargemFromRows();
        $this->dispatch('erp-masks-refresh');
        $this->autosaveLancamentoDraft();
    }
    protected function aplicarMargemPctNaLinha(int $index, string $nivel, string $pctRaw): void
    {
        if (! isset($this->lancamentoModalRows[$index])) {
            return;
        }

        $campoPreco = match ($nivel) {
            'varejo' => 'preco_venda',
            'atacado' => 'preco_atacado',
            'especial' => 'preco_especial',
            default => null,
        };
        $campoMargem = match ($nivel) {
            'varejo' => 'margem_varejo',
            'atacado' => 'margem_atacado',
            'especial' => 'margem_especial',
            default => null,
        };

        if ($campoPreco === null || $campoMargem === null) {
            return;
        }

        $pct = BrDecimal::parse($pctRaw, 2);
        $base = $this->basePrecoMargemLinha($index, $nivel);
        $preco = round($base * (1 + ($pct / 100)), 2);

        $this->lancamentoModalRows[$index][$campoMargem] = number_format($pct, 2, ',', '.');
        $this->lancamentoModalRows[$index][$campoPreco] = number_format($preco, 2, ',', '.');

        if ($nivel === 'varejo') {
            $this->lancamentoModalRows[$index]['preco_venda_base'] = number_format($preco, 2, '.', '');
            // Atacado/Especial passam a medir % sobre o novo varejo
            $this->sincronizarMargemDaLinha($index, 'atacado');
            $this->sincronizarMargemDaLinha($index, 'especial');
        }
    }

    /** Base da margem: Varejo = V. Custo; Atacado/Especial = Varejo (nunca custo). */
    protected function basePrecoMargemLinha(int $index, string $nivel): float
    {
        if (! isset($this->lancamentoModalRows[$index])) {
            return 0.0;
        }

        if ($nivel === 'varejo') {
            return BrDecimal::parse($this->lancamentoModalRows[$index]['vl_custo'] ?? 0, 4);
        }

        return BrDecimal::parse($this->lancamentoModalRows[$index]['preco_venda'] ?? 0, 2);
    }

    /** Atualiza Mg% a partir do preço da coluna. */
    protected function sincronizarMargemDaLinha(int $index, string $col): void
    {
        if (! isset($this->lancamentoModalRows[$index])) {
            return;
        }

        match ($col) {
            'venda' => $this->lancamentoModalRows[$index]['margem_varejo'] = $this->lancamentoMargemPctLabel(
                $this->lancamentoModalRows[$index]['preco_venda'] ?? 0,
                $this->lancamentoModalRows[$index]['vl_custo'] ?? 0,
            ),
            'atacado' => $this->lancamentoModalRows[$index]['margem_atacado'] = $this->lancamentoMargemPctLabel(
                $this->lancamentoModalRows[$index]['preco_atacado'] ?? 0,
                $this->lancamentoModalRows[$index]['preco_venda'] ?? 0,
            ),
            'especial' => $this->lancamentoModalRows[$index]['margem_especial'] = $this->lancamentoMargemPctLabel(
                $this->lancamentoModalRows[$index]['preco_especial'] ?? 0,
                $this->lancamentoModalRows[$index]['preco_venda'] ?? 0,
            ),
            default => null,
        };
    }

    protected function sincronizarTodasMargensDaLinha(int $index): void
    {
        $this->sincronizarMargemDaLinha($index, 'venda');
        $this->sincronizarMargemDaLinha($index, 'atacado');
        $this->sincronizarMargemDaLinha($index, 'especial');
    }

    protected function aplicarRecalculoVlCustoItem(int $index): void
    {
        if (! isset($this->lancamentoModalRows[$index])) {
            return;
        }

        $qtd = BrDecimal::parse($this->lancamentoModalRows[$index]['qtd'] ?? 0, 3);
        if ($qtd < 0) {
            $qtd = 0.0;
        }

        $valorCheio = BrDecimal::parse($this->lancamentoModalRows[$index]['preco'] ?? 0, 4);
        $vlCustoBase = $qtd > 0 ? round($valorCheio / $qtd, 4) : 0.0;

        $this->lancamentoModalRows[$index]['qtd'] = number_format($qtd, 3, ',', '.');
        $this->lancamentoModalRows[$index]['qtd_num'] = number_format($qtd, 3, '.', '');
        $this->lancamentoModalRows[$index]['vl_custo_base'] = number_format($vlCustoBase, 4, '.', '');
        $this->lancamentoModalRows[$index]['vl_custo'] = number_format($vlCustoBase, 2, ',', '.');
        $this->lancamentoModalRows[$index]['total_num'] = number_format($valorCheio, 2, '.', '');
        $this->sincronizarTodasMargensDaLinha($index);
    }

    public function closeCompraLancamento(): void
    {
        // Autosave silencioso — evita toast duplicado ao fechar e reabrir.
        $this->persistLancamentoDraft(notify: false);

        $this->lancamentoModalOpen = false;
        $this->lancamentoModalCompraId = null;
        $this->lancamentoRomaneio = false;
        $this->lancamentoRomaneioFornecedorBusca = '';
        $this->lancamentoRomaneioFornecedores = [];
        $this->lancamentoRomaneioProdutoBusca = '';
        $this->lancamentoRomaneioProdutos = [];
        $this->lancamentoModalStatus = '';
        $this->lancamentoModalHeader = [];
        $this->lancamentoModalRows = [];
        $this->lancamentoModalValorCompra = '0,00';
        $this->lancamentoModalValorMargemVarejo = '0,00';
        $this->lancamentoModalValorVarejo = '0,00';
        $this->lancamentoModalValorMargemAtacado = '0,00';
        $this->lancamentoModalValorAtacado = '0,00';
        $this->lancamentoModalValorMargemEspecial = '0,00';
        $this->lancamentoModalValorEspecial = '0,00';
        $this->lancamentoModalTotais = [];
        $this->lancamentoMargemEscopo = 'item';
        $this->lancamentoMargemPercentVarejo = '0,00';
        $this->lancamentoMargemPercentAtacado = '0,00';
        $this->lancamentoMargemPercentEspecial = '0,00';
        $this->lancamentoParamAjustaPreco = true;
        $this->lancamentoParamGerarFinanceiro = true;
        $this->lancamentoParamGeraEstoque = true;
        $this->lancamentoModalItemIndex = 0;
        $this->lancamentoSkipQtdBlur = false;
        $this->lancamentoSkipTotaisBlur = false;
        $this->lancamentoSkipPrecoBlur = false;
        $this->productPrecificacaoOpen = false;
        $this->precificacao = [];
        $this->lancamentoPrecificacaoRowIndex = null;
        $this->lancamentoFinalizarConfirmOpen = false;
        $this->lancamentoParcelasOpen = false;
        $this->lancamentoParcelasRows = [];
        $this->lancamentoParcelasSelectedIndex = null;
        $this->lancamentoParcelasSubtotal = '0,00';
        $this->lancamentoParcelasEntrada = '0,00';
        $this->lancamentoParcelasTotal = '0,00';
        $this->lancamentoParcelasQtd = '1';
        $this->lancamentoParcelasIntervalo = '30';
    }

    /**
     * Grava rascunho do lançamento (grade/totais/params). Não dá entrada nem atualiza cadastro.
     */
    public function persistLancamentoDraft(bool $notify = false): bool
    {
        if (! $this->canPersistLancamentoDraft()) {
            return false;
        }

        $compra = $this->scopedCompraQuery()->find($this->lancamentoModalCompraId);

        if (! $compra || $compra->status !== Compra::STATUS_ABERTA) {
            return false;
        }

        $service = new CompraLancamentoDraftService();
        $payload = $service->buildPayload(
            $this->lancamentoModalRows,
            $this->lancamentoModalTotais,
            [
                'ajusta_preco' => $this->lancamentoParamAjustaPreco,
                'gerar_financeiro' => $this->lancamentoParamGerarFinanceiro,
                'gera_estoque' => $this->lancamentoParamGeraEstoque,
                'margem_escopo' => $this->lancamentoMargemEscopo,
                'margem_percent_varejo' => $this->lancamentoMargemPercentVarejo,
                'margem_percent_atacado' => $this->lancamentoMargemPercentAtacado,
                'margem_percent_especial' => $this->lancamentoMargemPercentEspecial,
                'item_index' => $this->lancamentoModalItemIndex,
            ],
        );

        try {
            $service->save($compra, $payload);
        } catch (\Throwable $e) {
            report($e);

            if ($notify) {
                Notification::make()
                    ->title('Não foi possível salvar o rascunho')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            return false;
        }

        if ($notify) {
            Notification::make()
                ->title('Rascunho salvo')
                ->body('Ao reabrir esta compra, a grade retoma de onde parou. Cadastro e estoque só atualizam ao Finalizar.')
                ->success()
                ->send();
        }

        return true;
    }

    protected function canPersistLancamentoDraft(): bool
    {
        return $this->lancamentoModalCompraId !== null
            && in_array($this->lancamentoModalStatus, ['ALTERANDO', 'ROMANEIO'], true)
            && $this->lancamentoModalRows !== [];
    }

    /**
     * Autosave silencioso após edições relevantes (queda de energia / fechar sem querer).
     */
    protected function autosaveLancamentoDraft(): void
    {
        $this->persistLancamentoDraft(notify: false);
    }

    protected function restoreLancamentoDraftIfAny(Compra $compra): void
    {
        if ($compra->status !== Compra::STATUS_ABERTA) {
            return;
        }

        $service = new CompraLancamentoDraftService();
        $draft = $service->read($compra);

        if ($draft === null) {
            return;
        }

        if (! $service->isCompatible($compra, $draft)) {
            $service->clear($compra);

            Notification::make()
                ->title('Rascunho ignorado')
                ->body('Os itens da compra mudaram desde a última gravação. A grade foi recarregada do XML/cadastro.')
                ->warning()
                ->send();

            return;
        }

        $estadoAtual = [
            'rows' => $this->lancamentoModalRows,
            'totais' => $this->lancamentoModalTotais,
            'params' => [
                'ajusta_preco' => $this->lancamentoParamAjustaPreco,
                'gerar_financeiro' => $this->lancamentoParamGerarFinanceiro,
                'gera_estoque' => $this->lancamentoParamGeraEstoque,
            ],
            'margem' => [
                'escopo' => $this->lancamentoMargemEscopo,
                'percent_varejo' => $this->lancamentoMargemPercentVarejo,
                'percent_atacado' => $this->lancamentoMargemPercentAtacado,
                'percent_especial' => $this->lancamentoMargemPercentEspecial,
            ],
        ];

        if (! $this->lancamentoDraftDiffersFromEstado($draft, $estadoAtual)) {
            return;
        }

        $this->lancamentoModalRows = array_values($draft['rows']);
        $this->lancamentoModalTotais = is_array($draft['totais'] ?? null) ? $draft['totais'] : $this->lancamentoModalTotais;

        $params = is_array($draft['params'] ?? null) ? $draft['params'] : [];
        $this->lancamentoParamAjustaPreco = (bool) ($params['ajusta_preco'] ?? true);
        $this->lancamentoParamGerarFinanceiro = (bool) ($params['gerar_financeiro'] ?? true);
        $this->lancamentoParamGeraEstoque = (bool) ($params['gera_estoque'] ?? true);

        $margem = is_array($draft['margem'] ?? null) ? $draft['margem'] : [];
        $this->lancamentoMargemEscopo = (string) ($margem['escopo'] ?? 'item');
        $this->lancamentoMargemPercentVarejo = (string) ($margem['percent_varejo'] ?? '0,00');
        $this->lancamentoMargemPercentAtacado = (string) ($margem['percent_atacado'] ?? '0,00');
        $this->lancamentoMargemPercentEspecial = (string) ($margem['percent_especial'] ?? '0,00');

        $itemIndex = (int) ($draft['item_index'] ?? 0);
        $this->lancamentoModalItemIndex = isset($this->lancamentoModalRows[$itemIndex]) ? $itemIndex : 0;
        $this->lancamentoGridEpoch++;
        $this->refreshLancamentoMargemFromRows();

        $savedLabel = $this->formatLancamentoDraftSavedLabel((string) ($draft['saved_at'] ?? ''));

        Notification::make()
            ->title('Precificação retomada')
            ->body($savedLabel !== ''
                ? 'Grade restaurada conforme a última gravação ('.$savedLabel.').'
                : 'Grade restaurada conforme a última gravação.')
            ->success()
            ->send();
    }

    protected function formatLancamentoDraftSavedLabel(string $savedAt): string
    {
        if ($savedAt === '') {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($savedAt)
                ->timezone(config('app.timezone'))
                ->format('d/m/Y \à\s H:i');
        } catch (\Throwable) {
            return $savedAt;
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array{
     *     rows: list<array<string, mixed>>,
     *     totais: array<string, mixed>,
     *     params: array{ajusta_preco: bool, gerar_financeiro: bool, gera_estoque: bool},
     *     margem: array{escopo: string, percent_varejo: string, percent_atacado: string, percent_especial: string}
     * }  $estadoAtual
     */
    protected function lancamentoDraftDiffersFromEstado(array $draft, array $estadoAtual): bool
    {
        $draftRows = array_values($draft['rows'] ?? []);
        $currentRows = array_values($estadoAtual['rows'] ?? []);

        if (count($draftRows) !== count($currentRows)) {
            return true;
        }

        $rowKeys = [
            'preco_venda',
            'preco_atacado',
            'preco_especial',
            'margem_varejo',
            'margem_atacado',
            'margem_especial',
            'vl_custo',
            'qtd',
        ];

        foreach ($draftRows as $index => $draftRow) {
            $currentRow = $currentRows[$index] ?? [];

            foreach ($rowKeys as $key) {
                $draftValue = BrDecimal::parse($draftRow[$key] ?? 0, 4);
                $currentValue = BrDecimal::parse($currentRow[$key] ?? 0, 4);

                if (abs($draftValue - $currentValue) > 0.009) {
                    return true;
                }
            }
        }

        $totalKeys = ['frete', 'seguro', 'outras', 'total'];
        $draftTotais = is_array($draft['totais'] ?? null) ? $draft['totais'] : [];
        $currentTotais = $estadoAtual['totais'] ?? [];

        foreach ($totalKeys as $key) {
            $draftValue = BrDecimal::parse($draftTotais[$key] ?? 0, 2);
            $currentValue = BrDecimal::parse($currentTotais[$key] ?? 0, 2);

            if (abs($draftValue - $currentValue) > 0.009) {
                return true;
            }
        }

        $draftParams = is_array($draft['params'] ?? null) ? $draft['params'] : [];
        $currentParams = $estadoAtual['params'];

        foreach (['ajusta_preco', 'gerar_financeiro', 'gera_estoque'] as $flag) {
            if ((bool) ($draftParams[$flag] ?? false) !== (bool) ($currentParams[$flag] ?? false)) {
                return true;
            }
        }

        $draftMargem = is_array($draft['margem'] ?? null) ? $draft['margem'] : [];
        $currentMargem = $estadoAtual['margem'];

        if ((string) ($draftMargem['escopo'] ?? 'item') !== (string) ($currentMargem['escopo'] ?? 'item')) {
            return true;
        }

        foreach (['percent_varejo', 'percent_atacado', 'percent_especial'] as $key) {
            $draftValue = BrDecimal::parse($draftMargem[$key] ?? 0, 2);
            $currentValue = BrDecimal::parse($currentMargem[$key] ?? 0, 2);

            if (abs($draftValue - $currentValue) > 0.009) {
                return true;
            }
        }

        return false;
    }

    public function printCompraDanfe(): void
    {
        if (! $this->lancamentoModalCompraId) {
            Notification::make()
                ->title('Nenhuma compra selecionada.')
                ->warning()
                ->send();

            return;
        }

        $this->dispatch(
            'erp-compras-open-danfe',
            url: route('erp.reports.compra-danfe', ['compra' => $this->lancamentoModalCompraId]),
        );
    }

    public function finalizarCompraLancamento(): void
    {
        if (! $this->lancamentoModalCompraId) {
            Notification::make()
                ->title('Nenhuma compra selecionada.')
                ->warning()
                ->send();

            return;
        }

        if ($this->lancamentoModalStatus === 'FECHADA') {
            Notification::make()
                ->title('Compra já finalizada')
                ->body('Esta compra já está fechada.')
                ->warning()
                ->send();

            return;
        }

        if ($this->lancamentoModalStatus === 'CANCELADA') {
            Notification::make()
                ->title('Compra cancelada')
                ->body('Não é possível finalizar uma compra cancelada.')
                ->warning()
                ->send();

            return;
        }

        if ($this->lancamentoRomaneio) {
            $compra = $this->scopedCompraQuery()
                ->with('fornecedor')
                ->find($this->lancamentoModalCompraId);

            if (! $compra?->fornecedor_id) {
                Notification::make()
                    ->title('Informe o fornecedor do romaneio.')
                    ->warning()
                    ->send();

                return;
            }

            if ($this->lancamentoModalRows === []) {
                Notification::make()
                    ->title('Adicione ao menos um produto ao romaneio.')
                    ->warning()
                    ->send();

                return;
            }
        }

        $this->lancamentoFinalizarConfirmOpen = true;
    }

    public function cancelarFinalizarCompraLancamento(): void
    {
        $this->lancamentoFinalizarConfirmOpen = false;
    }

    public function confirmarFinalizarCompraLancamento(): void
    {
        $this->lancamentoFinalizarConfirmOpen = false;

        if ($this->lancamentoParamGerarFinanceiro) {
            $this->abrirLancamentoParcelasModal();

            return;
        }

        $this->executarFinalizarCompraLancamento(null);
    }

    public function abrirLancamentoParcelasModal(): void
    {
        $total = BrDecimal::parse($this->lancamentoModalTotais['total'] ?? 0, 2);
        if ($total <= 0 && $this->lancamentoModalCompraId) {
            $compra = $this->scopedCompraQuery()->find($this->lancamentoModalCompraId);
            $total = (float) ($compra?->total ?? 0);
        }

        $totalFmt = number_format(max(0, $total), 2, ',', '.');
        $this->lancamentoParcelasSubtotal = $totalFmt;
        $this->lancamentoParcelasEntrada = '0,00';
        $this->lancamentoParcelasTotal = $totalFmt;
        $this->lancamentoParcelasQtd = '1';
        $this->lancamentoParcelasIntervalo = '30';
        $this->lancamentoParcelasSelectedIndex = null;
        $this->lancamentoParcelasRows = [];

        $fromXml = $this->parcelasFinanceiroDoXmlCompra();
        if ($fromXml !== []) {
            $this->lancamentoParcelasRows = $fromXml;
            $this->lancamentoParcelasQtd = (string) count($fromXml);
            $somaXml = 0.0;
            foreach ($fromXml as $row) {
                $somaXml += BrDecimal::parse($row['valor'] ?? 0, 2);
            }
            $this->lancamentoParcelasTotal = number_format(round($somaXml, 2), 2, ',', '.');
            $this->lancamentoParcelasSelectedIndex = 0;
        } else {
            $this->gerarLancamentoParcelas();
        }

        $this->lancamentoParcelasOpen = true;
    }

    public function cancelarLancamentoParcelas(): void
    {
        $this->lancamentoParcelasOpen = false;
        $this->lancamentoParcelasRows = [];
        $this->lancamentoParcelasSelectedIndex = null;
    }

    public function gerarLancamentoParcelas(): void
    {
        $subtotal = BrDecimal::parse($this->lancamentoParcelasSubtotal, 2);
        $entrada = BrDecimal::parse($this->lancamentoParcelasEntrada, 2);
        if ($entrada < 0) {
            $entrada = 0.0;
        }
        if ($entrada > $subtotal) {
            $entrada = $subtotal;
        }

        $financiado = round(max(0, $subtotal - $entrada), 2);
        $this->lancamentoParcelasTotal = number_format(round($subtotal, 2), 2, ',', '.');

        $qtd = max(1, min(120, (int) preg_replace('/\D/', '', $this->lancamentoParcelasQtd) ?: 1));
        $intervalo = max(0, min(3650, (int) preg_replace('/\D/', '', $this->lancamentoParcelasIntervalo) ?: 0));
        $this->lancamentoParcelasQtd = (string) $qtd;
        $this->lancamentoParcelasIntervalo = (string) $intervalo;

        $formaDinheiroId = (string) ($this->formaPagamentoIdPorTipo('dinheiro') ?? '');
        $baseDate = now()->startOfDay();
        $rows = [];

        if ($entrada > 0) {
            $rows[] = [
                'documento' => 'ENTRADA',
                'vencimento' => $baseDate->format('d/m/Y'),
                'forma_pagamento_id' => $formaDinheiroId,
                'caixa_conta_id' => '',
                'valor' => number_format($entrada, 2, ',', '.'),
            ];
        }

        if ($financiado > 0 || $rows === []) {
            $centavos = (int) round(($financiado > 0 ? $financiado : $subtotal) * 100);
            $base = $qtd > 0 ? intdiv($centavos, $qtd) : 0;
            $resto = $qtd > 0 ? $centavos % $qtd : 0;

            for ($i = 0; $i < $qtd; $i++) {
                $valor = round(($base + ($i < $resto ? 1 : 0)) / 100, 2);
                if ($valor <= 0) {
                    continue;
                }

                $venc = $intervalo === 0
                    ? $baseDate->copy()
                    : $baseDate->copy()->addDays($intervalo * ($i + 1));

                $rows[] = [
                    'documento' => ($i + 1).'/'.$qtd,
                    'vencimento' => $venc->format('d/m/Y'),
                    'forma_pagamento_id' => '',
                    'caixa_conta_id' => '',
                    'valor' => number_format($valor, 2, ',', '.'),
                ];
            }
        }

        $this->lancamentoParcelasRows = $rows;
        $this->lancamentoParcelasSelectedIndex = $rows !== [] ? 0 : null;
    }

    public function selectLancamentoParcela(int $index): void
    {
        if (! isset($this->lancamentoParcelasRows[$index])) {
            return;
        }

        $this->lancamentoParcelasSelectedIndex = $index;
    }

    public function excluirLancamentoParcelaSelecionada(): void
    {
        $index = $this->lancamentoParcelasSelectedIndex;
        if ($index === null || ! isset($this->lancamentoParcelasRows[$index])) {
            return;
        }

        $rows = $this->lancamentoParcelasRows;
        array_splice($rows, $index, 1);
        $parcelasNormais = 0;
        foreach ($rows as $row) {
            if (mb_strtoupper(trim((string) ($row['documento'] ?? '')), 'UTF-8') !== 'ENTRADA') {
                $parcelasNormais++;
            }
        }
        $seq = 1;
        foreach ($rows as $i => $row) {
            if (mb_strtoupper(trim((string) ($row['documento'] ?? '')), 'UTF-8') === 'ENTRADA') {
                continue;
            }
            $rows[$i]['documento'] = $seq.'/'.max(1, $parcelasNormais);
            $seq++;
        }
        $this->lancamentoParcelasRows = $rows;
        $this->lancamentoParcelasQtd = (string) max(1, $parcelasNormais > 0 ? $parcelasNormais : count($rows));
        $this->lancamentoParcelasSelectedIndex = $rows !== []
            ? min($index, count($rows) - 1)
            : null;
    }

    public function updatedLancamentoParcelasRows(mixed $value, ?string $key = null): void
    {
        if (! is_string($key)) {
            return;
        }

        $parts = explode('.', $key);
        $index = (int) ($parts[0] ?? -1);
        if ($index < 0 || ! isset($this->lancamentoParcelasRows[$index])) {
            return;
        }

        if (str_ends_with($key, '.forma_pagamento_id')) {
            $formaId = (int) ($this->lancamentoParcelasRows[$index]['forma_pagamento_id'] ?? 0);
            if (! $this->lancamentoParcelaExigeSubcaixa($formaId)) {
                $this->lancamentoParcelasRows[$index]['caixa_conta_id'] = '';
            }

            return;
        }

        if (str_ends_with($key, '.valor')) {
            $this->recalcularSaldoLancamentoParcelas($index);
        }
    }

    protected function recalcularSaldoLancamentoParcelas(int $alteradaIndex): void
    {
        $rows = $this->lancamentoParcelasRows;
        $indicesAjustaveis = array_keys($rows);

        if (count($indicesAjustaveis) < 2) {
            return;
        }

        $indiceSaldo = end($indicesAjustaveis);
        if ($indiceSaldo === $alteradaIndex) {
            $indiceSaldo = $indicesAjustaveis[count($indicesAjustaveis) - 2];
        }

        $total = BrDecimal::parse($this->lancamentoParcelasTotal, 2);
        $valorAlterado = BrDecimal::parse($rows[$alteradaIndex]['valor'] ?? 0, 2);
        $rows[$alteradaIndex]['valor'] = number_format(max(0, $valorAlterado), 2, ',', '.');
        $somaSemSaldo = 0.0;

        foreach ($rows as $index => $row) {
            if ($index === $indiceSaldo) {
                continue;
            }

            $somaSemSaldo += BrDecimal::parse($row['valor'] ?? 0, 2);
        }

        $saldo = round(max(0, $total - $somaSemSaldo), 2);
        $rows[$indiceSaldo]['valor'] = number_format($saldo, 2, ',', '.');
        $this->lancamentoParcelasRows = $rows;
    }

    public function concluirLancamentoParcelas(): void
    {
        if ($this->lancamentoParcelasRows === []) {
            Notification::make()
                ->title('Gere as parcelas')
                ->body('Informe parcelas/intervalo e clique em Gerar, ou use as duplicatas do XML.')
                ->warning()
                ->send();

            return;
        }

        $esperado = BrDecimal::parse($this->lancamentoParcelasTotal, 2);
        $soma = 0.0;
        foreach ($this->lancamentoParcelasRows as $i => $row) {
            $soma += BrDecimal::parse($row['valor'] ?? 0, 2);
            $formaId = (int) ($row['forma_pagamento_id'] ?? 0);

            if ($formaId <= 0) {
                Notification::make()
                    ->title('Meio de pagamento obrigatório')
                    ->body('Informe o meio de pagamento na parcela '.($i + 1).'.')
                    ->warning()
                    ->send();

                return;
            }

            if ($this->lancamentoParcelaExigeSubcaixa($formaId)) {
                $caixaId = (int) ($row['caixa_conta_id'] ?? 0);
                if ($caixaId <= 0) {
                    Notification::make()
                        ->title('Subcaixa obrigatória')
                        ->body('Selecione o subcaixa de onde sai o dinheiro na parcela '.($i + 1).'.')
                        ->warning()
                        ->send();

                    return;
                }
            }
        }
        $soma = round($soma, 2);

        if (abs($soma - $esperado) > 0.05) {
            Notification::make()
                ->title('Total das parcelas divergente')
                ->body('Soma das parcelas ('.number_format($soma, 2, ',', '.').') difere do total ('
                    .number_format($esperado, 2, ',', '.').').')
                ->warning()
                ->send();

            return;
        }

        $parcelas = [];
        foreach ($this->lancamentoParcelasRows as $row) {
            $parcelas[] = [
                'documento' => (string) ($row['documento'] ?? ''),
                'vencimento' => (string) ($row['vencimento'] ?? ''),
                'valor' => (string) ($row['valor'] ?? '0'),
                'forma_pagamento_id' => (int) ($row['forma_pagamento_id'] ?? 0),
                'caixa_conta_id' => (int) ($row['caixa_conta_id'] ?? 0) ?: null,
            ];
        }

        $this->lancamentoParcelasOpen = false;
        $this->executarFinalizarCompraLancamento($parcelas);
    }

    #[Computed]
    public function lancamentoParcelasPodeConcluir(): bool
    {
        if ($this->lancamentoParcelasRows === []) {
            return false;
        }

        foreach ($this->lancamentoParcelasRows as $row) {
            $formaId = (int) ($row['forma_pagamento_id'] ?? 0);

            if ($formaId <= 0) {
                return false;
            }

            if ($this->lancamentoParcelaExigeSubcaixa($formaId)
                && (int) ($row['caixa_conta_id'] ?? 0) <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{id: int, label: string, tipo: string|null}>
     */
    #[Computed]
    public function lancamentoParcelasFormasOptions(): array
    {
        return FormaPagamento::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->orderBy('descricao')
            ->get(['id', 'codigo', 'descricao', 'tipo'])
            ->map(function (FormaPagamento $forma): array {
                $codigo = (int) ($forma->codigo ?? 0);
                $descricao = trim((string) ($forma->descricao ?? ''));
                $label = $codigo > 0
                    ? str_pad((string) $codigo, 2, '0', STR_PAD_LEFT).' — '.($descricao !== '' ? $descricao : 'Sem descrição')
                    : ($descricao !== '' ? $descricao : 'Forma #'.$forma->id);

                return [
                    'id' => (int) $forma->id,
                    'label' => $label,
                    'tipo' => $forma->tipo,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    #[Computed]
    public function lancamentoParcelasSubcaixasOptions(): array
    {
        return CaixaConta::query()
            ->where('ativo', true)
            ->where('tipo', CaixaConta::TIPO_SUBCAIXA)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome'])
            ->map(fn (CaixaConta $caixa): array => [
                'id' => (int) $caixa->id,
                'label' => trim((string) $caixa->codigo).' — '.mb_strtoupper((string) $caixa->nome, 'UTF-8'),
            ])
            ->values()
            ->all();
    }

    public function lancamentoParcelaExigeSubcaixa(int $formaPagamentoId): bool
    {
        if ($formaPagamentoId <= 0) {
            return false;
        }

        $tipo = FormaPagamento::query()->whereKey($formaPagamentoId)->value('tipo');

        return mb_strtolower(trim((string) $tipo), 'UTF-8') === 'dinheiro';
    }

    protected function formaPagamentoIdPorTipo(string $tipo): ?int
    {
        $id = FormaPagamento::query()
            ->where('ativo', true)
            ->where('tipo', $tipo)
            ->orderBy('codigo')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * @param  list<array{documento?: string, vencimento: string, valor: float|string, forma_pagamento_id?: int|null, caixa_conta_id?: int|null}>|null  $parcelasFinanceiro
     */
    protected function executarFinalizarCompraLancamento(?array $parcelasFinanceiro): void
    {
        if (! $this->lancamentoModalCompraId) {
            return;
        }

        $compra = $this->scopedCompraQuery()
            ->with(['itens.product', 'fornecedor'])
            ->find($this->lancamentoModalCompraId);

        if (! $compra) {
            Notification::make()
                ->title('Compra não encontrada.')
                ->danger()
                ->send();

            return;
        }

        $totalOverride = BrDecimal::parse($this->lancamentoModalTotais['total'] ?? 0, 2);
        if ($totalOverride <= 0) {
            $totalOverride = (float) ($compra->total ?? 0);
        }

        try {
            (new FinalizarCompraLancamentoService())->finalizar(
                $compra,
                $this->lancamentoModalRows,
                $this->lancamentoParamAjustaPreco,
                $this->lancamentoParamGerarFinanceiro,
                $this->lancamentoParamGeraEstoque,
                $parcelasFinanceiro,
                $totalOverride > 0 ? $totalOverride : null,
            );
        } catch (DomainException|\InvalidArgumentException $e) {
            Notification::make()
                ->title('Não foi possível finalizar')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->lancamentoFinalizarConfirmOpen = false;
        $this->lancamentoParcelasOpen = false;
        $this->closeCompraLancamento();
        $this->resetTable();

        Notification::make()
            ->title('Compra finalizada')
            ->body('Compra #'.$compra->numero.' fechada com sucesso.')
            ->success()
            ->send();
    }

    /**
     * Duplicatas do XML da nota vinculada à compra (se existir).
     *
     * @return list<array{documento: string, vencimento: string, forma_pagamento_id: string, caixa_conta_id: string, valor: string}>
     */
    protected function parcelasFinanceiroDoXmlCompra(): array
    {
        if (! $this->lancamentoModalCompraId) {
            return [];
        }

        $nota = NotaFornecedor::query()
            ->where('compra_id', $this->lancamentoModalCompraId)
            ->whereNotNull('xml')
            ->orderByDesc('id')
            ->first();

        if (! $nota || ! is_string($nota->xml) || trim($nota->xml) === '') {
            return [];
        }

        $parsed = (new NotaFornecedorDanfeReportService())->parseXml($nota->xml);
        if (! is_array($parsed)) {
            return [];
        }

        $duplicatas = is_array($parsed['duplicatas'] ?? null) ? $parsed['duplicatas'] : [];
        if ($duplicatas === []) {
            return [];
        }

        $total = count($duplicatas);
        $rows = [];

        foreach ($duplicatas as $i => $dup) {
            $numero = trim((string) ($dup['numero'] ?? ''));
            $venc = trim((string) ($dup['vencimento'] ?? ''));
            $valor = trim((string) ($dup['valor'] ?? '0,00'));
            if ($venc === '' || $venc === '—') {
                continue;
            }

            $rows[] = [
                'documento' => $numero !== '' ? $numero : (($i + 1).'/'.$total),
                'vencimento' => $venc,
                'forma_pagamento_id' => '',
                'caixa_conta_id' => '',
                'valor' => $valor !== '' ? $valor : '0,00',
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function buildLancamentoModalRows(Compra $compra): array
    {
        $rows = [];
        $index = 1;

        foreach ($compra->itens as $item) {
            $codigo = $item->product?->codigo;
            $codigoFormatado = '—';

            if ($codigo !== null && $codigo !== '') {
                $trimmed = ltrim((string) $codigo, '0');
                $codigoFormatado = $trimmed !== '' ? $trimmed : '0';
            }

            $referencia = trim((string) ($item->product?->referencia ?? ''));

            $qtd = (float) $item->quantidade;
            $valorCheio = (float) $item->total;
            $vlCusto = (float) $item->valor_unitario;
            if ($qtd > 0 && $valorCheio > 0) {
                $vlCusto = round($valorCheio / $qtd, 4);
            }
            $precoVendaBase = (float) ($item->product?->preco_venda ?? 0);
            $precoAtacado = (float) ($item->product?->preco_atacado ?? 0);
            $precoEspecial = (float) ($item->product?->preco_especial ?? 0);

            $rows[] = [
                'item' => (string) $index++,
                'product_id' => (string) ((int) ($item->product_id ?? 0)),
                'codigo' => $codigoFormatado,
                'referencia' => $referencia,
                'produto' => $item->product?->descricao ?? '—',
                'qtd' => number_format($qtd, 3, ',', '.'),
                'qtd_num' => number_format($qtd, 3, '.', ''),
                // Preço = valor cheio da nota (linha).
                'preco' => number_format($valorCheio, 2, ',', '.'),
                // vL. custo base (sem frete/seguro/outras) = valor cheio ÷ Qtd.Compra.
                'vl_custo_base' => number_format($vlCusto, 4, '.', ''),
                'vl_custo' => number_format($vlCusto, 2, ',', '.'),
                'total_num' => number_format($valorCheio, 2, '.', ''),
                'preco_venda_base' => number_format($precoVendaBase, 2, '.', ''),
                'preco_venda' => number_format($precoVendaBase, 2, ',', '.'),
                'preco_atacado' => number_format($precoAtacado, 2, ',', '.'),
                'preco_especial' => number_format($precoEspecial, 2, ',', '.'),
                'margem_varejo' => $this->lancamentoMargemPctLabel($precoVendaBase, $vlCusto),
                'margem_atacado' => $this->lancamentoMargemPctLabel($precoAtacado, $precoVendaBase),
                'margem_especial' => $this->lancamentoMargemPctLabel($precoEspecial, $precoVendaBase),
            ];
        }

        return $rows;
    }

    public function lancamentoTotaisEnter(string $key, string $value): void
    {
        if (! in_array($key, ['frete', 'seguro', 'outras'], true)) {
            return;
        }

        $this->lancamentoSkipTotaisBlur = true;
        $this->commitLancamentoTotaisExtra($key, $value);
        // Re-render para atualizar V. Custo na grade; o JS refoca o próximo campo.
    }

    public function lancamentoTotaisBlur(string $key, string $value): void
    {
        if (! in_array($key, ['frete', 'seguro', 'outras'], true)) {
            return;
        }

        if ($this->lancamentoSkipTotaisBlur) {
            $this->lancamentoSkipTotaisBlur = false;
            $this->skipRender();

            return;
        }

        $this->commitLancamentoTotaisExtra($key, $value);
    }

    protected function commitLancamentoTotaisExtra(string $key, mixed $value): void
    {
        $parsed = BrDecimal::parse($value, 2);
        $this->lancamentoModalTotais[$key] = number_format($parsed, 2, ',', '.');
        $this->recalcularTotalLancamentoAposExtras();
        $this->aplicarRateioExtrasNoVlCusto();
        $this->autosaveLancamentoDraft();
    }

    protected function sincronizarTotalRomaneio(): void
    {
        if (! $this->lancamentoRomaneio || ! $this->lancamentoModalCompraId) {
            return;
        }

        $subtotal = 0.0;

        foreach ($this->lancamentoModalRows as $row) {
            $subtotal += BrDecimal::parse($row['preco'] ?? 0, 2);
        }

        $frete = BrDecimal::parse($this->lancamentoModalTotais['frete'] ?? 0, 2);
        $seguro = BrDecimal::parse($this->lancamentoModalTotais['seguro'] ?? 0, 2);
        $outras = BrDecimal::parse($this->lancamentoModalTotais['outras'] ?? 0, 2);
        $total = round($subtotal + $frete + $seguro + $outras, 2);

        $this->lancamentoModalTotais['subtotal'] = number_format($subtotal, 2, ',', '.');
        $this->lancamentoModalTotais['total'] = number_format($total, 2, ',', '.');
        $this->lancamentoModalValorCompra = number_format($total, 2, ',', '.');

        $this->scopedCompraQuery()
            ->whereKey($this->lancamentoModalCompraId)
            ->update(['total' => $total]);
    }

    /**
     * Rateia frete + seguro + outras no V. Custo (preço de compra) de cada item,
     * proporcional ao valor da linha. Usa vl_custo_base para não acumular em edições.
     */
    protected function aplicarRateioExtrasNoVlCusto(): void
    {
        if ($this->lancamentoModalRows === []) {
            return;
        }

        $extras = BrDecimal::parse($this->lancamentoModalTotais['frete'] ?? 0, 2)
            + BrDecimal::parse($this->lancamentoModalTotais['seguro'] ?? 0, 2)
            + BrDecimal::parse($this->lancamentoModalTotais['outras'] ?? 0, 2);

        $baseSum = 0.0;
        foreach ($this->lancamentoModalRows as $row) {
            $baseSum += (float) ($row['total_num'] ?? 0);
        }

        $rateado = 0.0;
        $lastIndex = array_key_last($this->lancamentoModalRows);

        foreach ($this->lancamentoModalRows as $index => &$row) {
            $totalLinha = (float) ($row['total_num'] ?? 0);
            $qtd = (float) ($row['qtd_num'] ?? 0);
            $baseCusto = isset($row['vl_custo_base'])
                ? (float) $row['vl_custo_base']
                : ($qtd > 0 ? round($totalLinha / $qtd, 4) : 0.0);

            if ($extras <= 0 || $baseSum <= 0 || $qtd <= 0) {
                $row['vl_custo'] = number_format($baseCusto, 2, ',', '.');

                continue;
            }

            if ($index === $lastIndex) {
                $share = round($extras - $rateado, 2);
            } else {
                $share = round($extras * ($totalLinha / $baseSum), 2);
                $rateado += $share;
            }

            $acrescimoUnitario = round($share / $qtd, 4);
            $row['vl_custo'] = number_format($baseCusto + $acrescimoUnitario, 2, ',', '.');
        }
        unset($row);

        foreach (array_keys($this->lancamentoModalRows) as $rowIndex) {
            $this->sincronizarTodasMargensDaLinha((int) $rowIndex);
        }

        $this->refreshLancamentoMargemFromRows();
    }

    /**
     * Recalcula o Total da nota após editar frete/seguro/outras,
     * preservando impostos já carregados do XML.
     */
    protected function recalcularTotalLancamentoAposExtras(): void
    {
        $subtotal = BrDecimal::parse($this->lancamentoModalTotais['subtotal'] ?? 0, 2);
        $desconto = BrDecimal::parse($this->lancamentoModalTotais['desconto'] ?? 0, 2);
        $frete = BrDecimal::parse($this->lancamentoModalTotais['frete'] ?? 0, 2);
        $seguro = BrDecimal::parse($this->lancamentoModalTotais['seguro'] ?? 0, 2);
        $outras = BrDecimal::parse($this->lancamentoModalTotais['outras'] ?? 0, 2);
        $ipi = BrDecimal::parse($this->lancamentoModalTotais['valor_ipi'] ?? 0, 2);
        $st = BrDecimal::parse($this->lancamentoModalTotais['valor_st'] ?? 0, 2);

        $total = round($subtotal - $desconto + $frete + $seguro + $outras + $ipi + $st, 2);
        $this->lancamentoModalTotais['total'] = number_format($total, 2, ',', '.');
    }

    protected function refreshLancamentoMargemFromRows(): void
    {
        $index = $this->lancamentoModalItemIndex ?? 0;
        $row = $this->lancamentoModalRows[$index] ?? $this->lancamentoModalRows[0] ?? null;

        if ($row === null) {
            return;
        }

        $valorCompra = BrDecimal::parse($row['vl_custo'] ?? $row['preco'] ?? 0, 4);
        $varejo = BrDecimal::parse($row['preco_venda'] ?? 0, 2);
        $atacado = BrDecimal::parse($row['preco_atacado'] ?? 0, 2);
        $especial = BrDecimal::parse($row['preco_especial'] ?? 0, 2);

        $this->lancamentoModalValorCompra = number_format($valorCompra, 2, ',', '.');
        $this->lancamentoModalValorVarejo = number_format($varejo, 2, ',', '.');
        $this->lancamentoModalValorAtacado = number_format($atacado, 2, ',', '.');
        $this->lancamentoModalValorEspecial = number_format($especial, 2, ',', '.');
        // Varejo: % sobre V. Custo | Atacado/Especial: % sobre Varejo (nunca custo)
        $this->lancamentoModalValorMargemVarejo = $this->lancamentoMargemPctLabel($varejo, $valorCompra);
        $this->lancamentoModalValorMargemAtacado = $this->lancamentoMargemPctLabel($atacado, $varejo);
        $this->lancamentoModalValorMargemEspecial = $this->lancamentoMargemPctLabel($especial, $varejo);
    }

    /**
     * Aplica % de margem sobre V. Custo no nível informado (varejo|atacado|especial).
     */
    public function aplicarLancamentoMargem(string $nivel): void
    {
        if ($this->lancamentoModalStatus === 'FECHADA' || $this->lancamentoModalStatus === 'CANCELADA') {
            return;
        }

        if (! in_array($nivel, ['varejo', 'atacado', 'especial'], true)) {
            return;
        }

        $pctRaw = match ($nivel) {
            'varejo' => $this->lancamentoMargemPercentVarejo,
            'atacado' => $this->lancamentoMargemPercentAtacado,
            'especial' => $this->lancamentoMargemPercentEspecial,
            default => '0,00',
        };

        $indices = $this->lancamentoMargemEscopo === 'todos'
            ? array_keys($this->lancamentoModalRows)
            : [max(0, (int) ($this->lancamentoModalItemIndex ?? 0))];

        foreach ($indices as $index) {
            $this->aplicarMargemPctNaLinha((int) $index, $nivel, (string) $pctRaw);
        }

        $this->refreshLancamentoMargemFromRows();
        $this->lancamentoGridEpoch++;
        $this->dispatch('erp-masks-refresh');
        $this->autosaveLancamentoDraft();

        Notification::make()
            ->title(match ($nivel) {
                'varejo' => 'Margem varejo aplicada.',
                'atacado' => 'Margem atacado aplicada.',
                'especial' => 'Margem especial aplicada.',
                default => 'Margem aplicada.',
            })
            ->success()
            ->send();
    }

    /**
     * @return array{compra: string, margem: string, venda: string}
     */
    protected function buildLancamentoModalMargem(Compra $compra): array
    {
        $item = $compra->itens->first();

        if (! $item) {
            return [
                'compra' => '0,00',
                'margem' => '0,00',
                'venda' => '0,00',
            ];
        }

        $valorCompra = (float) $item->valor_unitario;
        $valorVenda = (float) ($item->product?->preco_venda ?? $valorCompra);
        $valorMargem = $valorVenda - $valorCompra;

        return [
            'compra' => number_format($valorCompra, 2, ',', '.'),
            'margem' => number_format($valorMargem, 2, ',', '.'),
            'venda' => number_format($valorVenda, 2, ',', '.'),
        ];
    }

    /**
     * Totais do lançamento a partir do ICMSTot do XML da nota vinculada.
     *
     * @return array<string, string>
     */
    protected function buildLancamentoModalTotais(Compra $compra): array
    {
        $zero = '0,00';
        $defaults = [
            'subtotal' => $zero,
            'base_icms' => $zero,
            'valor_icms' => $zero,
            'base_ipi' => $zero,
            'valor_ipi' => $zero,
            'base_cofins' => $zero,
            'valor_cofins' => $zero,
            'base_pis' => $zero,
            'valor_pis' => $zero,
            'base_st' => $zero,
            'valor_st' => $zero,
            'desconto' => $zero,
            'frete' => $zero,
            'seguro' => $zero,
            'outras' => $zero,
            'total' => $zero,
        ];

        $fromXml = $this->totaisFromNotaXml($compra);

        if ($fromXml !== null) {
            return array_merge($defaults, $fromXml);
        }

        $subtotal = (float) $compra->itens->sum('total');
        $total = (float) $compra->total;

        return array_merge($defaults, [
            'subtotal' => number_format($subtotal, 2, ',', '.'),
            'total' => number_format($total > 0 ? $total : $subtotal, 2, ',', '.'),
        ]);
    }

    /**
     * Lê ICMSTot do XML da NotaFornecedor ligada à compra.
     *
     * @return array<string, string>|null
     */
    protected function totaisFromNotaXml(Compra $compra): ?array
    {
        $nota = NotaFornecedor::query()
            ->where('compra_id', $compra->id)
            ->first();

        $xml = is_string($nota?->xml) ? trim($nota->xml) : '';

        if ($xml === '') {
            return null;
        }

        $parsed = (new NotaFornecedorDanfeReportService())->parseXml($xml);
        $totais = is_array($parsed['totais'] ?? null) ? $parsed['totais'] : null;

        if ($totais === null) {
            return null;
        }

        $pick = static function (array $src, string ...$keys) use ($totais): string {
            foreach ($keys as $key) {
                $value = trim((string) ($src[$key] ?? ''));
                if ($value !== '' && $value !== '—') {
                    return $value;
                }
            }

            return '0,00';
        };

        return [
            // vProd
            'subtotal' => $pick($totais, 'subtotal', 'total_produtos'),
            // vBC / vICMS
            'base_icms' => $pick($totais, 'base_icms'),
            'valor_icms' => $pick($totais, 'total_icms', 'valor_icms'),
            // IPI
            'base_ipi' => $pick($totais, 'base_ipi'),
            'valor_ipi' => $pick($totais, 'total_ipi'),
            // PIS / COFINS
            'base_pis' => $pick($totais, 'base_pis'),
            'valor_pis' => $pick($totais, 'total_pis'),
            'base_cofins' => $pick($totais, 'base_cofins'),
            'valor_cofins' => $pick($totais, 'total_cofins'),
            // ST
            'base_st' => $pick($totais, 'base_st', 'base_icms_st'),
            'valor_st' => $pick($totais, 'total_st', 'valor_icms_st'),
            // vDesc / vFrete / vSeg / vOutro
            'desconto' => $pick($totais, 'desconto'),
            'frete' => $pick($totais, 'frete'),
            'seguro' => $pick($totais, 'seguro'),
            'outras' => $pick($totais, 'outras', 'despesas'),
            // vNF
            'total' => $pick($totais, 'total', 'total_nota'),
        ];
    }

    /**
     * @return array{modelo: string, serie: string}
     */
    protected function extractNfeKeyParts(?string $chave): array
    {
        $digits = preg_replace('/\D/', '', (string) $chave) ?? '';

        if (strlen($digits) !== 44) {
            return [
                'modelo' => '—',
                'serie' => '—',
            ];
        }

        $modelo = ltrim(substr($digits, 20, 2), '0');
        $serie = ltrim(substr($digits, 22, 3), '0');

        return [
            'modelo' => $modelo !== '' ? $modelo : '0',
            'serie' => $serie !== '' ? $serie : '0',
        ];
    }

    protected function formatCompraNumero(?string $numero): string
    {
        if ($numero === null || $numero === '') {
            return '—';
        }

        $trimmed = ltrim($numero, '0');

        return $trimmed !== '' ? $trimmed : '0';
    }

    protected function formatCpfCnpj(?string $value): string
    {
        if (! filled($value)) {
            return '—';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digits) ?: $value;
        }

        return $value;
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        $allowed = [
            'todas',
            Compra::STATUS_ABERTA,
            Compra::STATUS_FECHADA,
            Compra::STATUS_CANCELADA,
        ];

        return in_array($value, $allowed, true) ? (string) $value : 'todas';
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->localSearchDe = '';
        $this->localSearchAte = '';
        $this->searchColumn = 'fornecedor';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->localSearchDe = '';
        $this->localSearchAte = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearch(): void
    {
        if ($this->isDateSearchColumn()) {
            return;
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearchDe(): void
    {
        if (! $this->isDateSearchColumn()) {
            return;
        }

        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedLocalSearchAte(): void
    {
        if (! $this->isDateSearchColumn()) {
            return;
        }

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

    public function createCompra(): void
    {
        if (! $this->erpAuthorizeOrNotify('compras.import_xml')) {
            return;
        }

        $this->openImportarXmlModalVazio();
    }

    public function openLerXmlFromCompraSelecionada(): void
    {
        if ($this->abortIfHighlightedCompraDevolvida('ler XML de')) {
            return;
        }

        $this->openImportarXmlModalVazio();
    }

    public function editCompra(): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        if ($this->abortIfHighlightedCompraDevolvida('alterar')) {
            return;
        }

        $this->openCompraLancamento((int) $recordId, 'alterando');
    }

    public function cancelCompra(): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('cancel');

        if (! $recordId) {
            return;
        }

        if ($this->abortIfHighlightedCompraDevolvida('cancelar')) {
            return;
        }

        $compra = $this->scopedCompraQuery()->find($recordId);

        if (! $compra) {
            return;
        }

        try {
            (new CancelarCompraService())->cancelar($compra);
        } catch (DomainException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->warning()
                ->send();

            return;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível cancelar a compra.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Compra cancelada.')
            ->body('Se havia entrada de estoque, o saldo foi estornado.')
            ->success()
            ->send();
    }

    public function reabrirCompra(): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('reabrir');

        if (! $recordId) {
            return;
        }

        if ($this->abortIfHighlightedCompraDevolvida('reabrir')) {
            return;
        }

        $compra = $this->scopedCompraQuery()->find($recordId);

        if (! $compra) {
            return;
        }

        if ($compra->status !== Compra::STATUS_FECHADA) {
            Notification::make()
                ->title('Compra não está fechada')
                ->body('Só é possível reabrir compras com situação Fechada.')
                ->warning()
                ->send();

            return;
        }

        try {
            (new ReabrirCompraLancamentoService())->reabrir($compra);
        } catch (DomainException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->warning()
                ->send();

            return;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível reabrir a compra.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Compra reaberta')
            ->body('Estoque, preços e financeiro da finalização foram estornados. Use Alterar (F3) para editar.')
            ->success()
            ->send();
    }
}
