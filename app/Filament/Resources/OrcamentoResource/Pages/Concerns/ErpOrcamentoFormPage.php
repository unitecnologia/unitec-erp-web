<?php

namespace App\Filament\Resources\OrcamentoResource\Pages\Concerns;

use App\Filament\Concerns\InteractsWithErpFormReturnUrl;
use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\PersonResource;
use App\Filament\Resources\ProductResource;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductGrade;
use App\Models\Vendedor;
use App\Support\Erp\ErpFormReturnUrl;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Orcamento\OrcamentoDescontoService;
use App\Support\Erp\Orcamento\OrcamentoPrecoService;
use App\Support\Erp\Orcamento\OrcamentoTotaisService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ErpOrcamentoFormPage
{
    use InteractsWithErpFormReturnUrl;
    use ManagesOrcamentoPrecoDivergencia;

    public string $activeFormTab = 'itens';

    public string $clienteSearch = '';

    public bool $clienteLookupOpen = false;

    /** @var array<int, array<string, mixed>> */
    public array $clienteResults = [];

    public ?int $selectedClienteIndex = null;

    public ?int $clienteId = null;

    public string $clienteCpfCnpj = '';

    public string $clienteEndereco = '';

    public string $clienteNumero = '';

    public string $clienteBairro = '';

    public string $clienteCep = '';

    public string $clienteCidade = '';

    public string $clienteUf = 'SC';

    public string $clienteFone = '';

    public string $clienteWhatsapp = '';

    public ?int $vendedorId = null;

    public string $formaPagamento = '';

    public string $validadeDias = '0';

    public string $observacoes = '';

    public string $subtotalDisplay = '0,00';

    public string $percentualDescontoDisplay = '0,00';

    public string $descontoValorDisplay = '0,00';

    public string $totalDisplay = '0,00';

    public string $barcodeInput = '';

    public string $itemCodigoInput = '';

    public string $itemProdutoSearch = '';

    public bool $produtoLookupOpen = false;

    /** @var array<int, array<string, mixed>> */
    public array $produtoResults = [];

    public ?int $selectedProdutoIndex = null;

    public ?int $itemPendingProductId = null;

    public string $itemQuantidadeInput = '1,000';

    public string $itemUnidadeDisplay = '';

    public string $itemPrecoDisplay = '';

    public string $itemPrecoInput = '';

    public bool $itemPendingPrecoVariavel = false;

    public string $itemTotalEntryDisplay = '';

    /** @var array<int, array<string, mixed>> */
    public array $itens = [];

    public ?int $selectedItemIndex = null;

    public bool $overlayProductOpen = false;

    public bool $overlayPersonOpen = false;

    public bool $postSavePromptOpen = false;

    public ?int $itemDeleteConfirmIndex = null;

    public bool $isConfirmingPendingItem = false;

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-orcamentos-form-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.orcamentos.form.window'),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName())
                    ->extraAttributes(['class' => 'erp-pcad__filament-hidden']),
            ]);
    }

    public function setActiveFormTab(string $tab): void
    {
        if (in_array($tab, ['itens', 'observacoes'], true)) {
            $this->activeFormTab = $tab;
        }
    }

    public function isEditingOrcamento(): bool
    {
        return $this instanceof EditRecord;
    }

    public function orcamentoNumeroDisplay(): string
    {
        if ($this->isEditingOrcamento()) {
            return (string) ($this->record?->numero ?? '');
        }

        return Orcamento::nextNumero();
    }

    public function orcamentoReadOnly(): bool
    {
        return $this->isEditingOrcamento() && ! ($this->record?->isEditable() ?? true);
    }

    /**
     * @return array<int, array{id: int, nome: string}>
     */
    public function vendedorOptions(): array
    {
        return Vendedor::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (Vendedor $vendedor): array => [
                'id' => $vendedor->id,
                'nome' => mb_strtoupper((string) $vendedor->nome, 'UTF-8'),
            ])
            ->all();
    }

    protected function initializeOrcamentoFormDefaults(): void
    {
        // Padrão: vendedor amarrado ao usuário logado (mesma fonte do PDV/App).
        $this->vendedorId = auth()->user()?->vendedor_id;
        $this->data = [
            'numero' => Orcamento::nextNumero(),
            'data' => now()->format('Y-m-d'),
            'status' => Orcamento::STATUS_ABERTO,
        ];
        $this->form->fill($this->data);
        $this->syncTotaisDisplay(0, 0, 0);
        $this->applyOrcamentoShortcutReturnContext();
    }

    protected function loadOrcamentoFormFromRecord(Orcamento $orcamento): void
    {
        $orcamento->load(['cliente', 'itens.product', 'itens.grade']);

        $this->data = [
            'numero' => $orcamento->numero,
            'data' => $orcamento->data?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'status' => $orcamento->status,
        ];
        $this->form->fill($this->data);

        $cliente = $orcamento->cliente;
        $this->clienteId = $cliente?->id;
        $this->clienteSearch = mb_strtoupper($cliente?->nome_razao ?? '', 'UTF-8');
        $this->applyClienteFields($cliente);

        $this->vendedorId = $orcamento->vendedor_id;
        $this->formaPagamento = mb_strtoupper((string) ($orcamento->forma_pagamento ?? ''), 'UTF-8');
        $this->validadeDias = (string) ($orcamento->validade_dias ?? 0);
        $this->observacoes = (string) ($orcamento->observacoes ?? '');

        $this->itens = $orcamento->itens
            ->sortByDesc('item')
            ->values()
            ->map(fn (OrcamentoItem $item): array => $this->mapItemToRow($item))
            ->all();

        $this->syncTotaisDisplay(
            (float) $orcamento->subtotal,
            (float) $orcamento->desconto_valor,
            (float) $orcamento->total,
            (float) $orcamento->percentual_desconto,
        );

        $this->sincronizarPrecosComCadastro(notify: true);
        $this->applyOrcamentoShortcutReturnContext();
    }

    protected function orcamentoFormReturnUrl(): string
    {
        if ($this->isEditingOrcamento()) {
            return ErpFormReturnUrl::normalize(
                OrcamentoResource::getUrl('edit', ['record' => $this->record->getKey()]),
            );
        }

        return ErpFormReturnUrl::normalize(OrcamentoResource::getUrl('create'));
    }

    public function applyOrcamentoShortcutReturnContext(): void
    {
        $clienteId = session()->pull(ErpFormReturnUrl::SESSION_NEW_CLIENTE_ID);

        if ($clienteId) {
            $person = Person::query()->find((int) $clienteId);

            if ($person) {
                $this->clienteId = $person->id;
                $this->clienteSearch = mb_strtoupper($person->nome_razao, 'UTF-8');
                $this->applyClienteFields($person);
            }
        }

        $produtoCodigo = session()->pull(ErpFormReturnUrl::SESSION_NEW_PRODUTO_CODIGO);

        if (filled($produtoCodigo)) {
            $this->itemCodigoInput = (string) $produtoCodigo;
            $this->dispatch('erp-orcamento-focus-item-codigo');

            return;
        }

        if ($clienteId) {
            $this->dispatch('erp-orcamento-focus-item-codigo');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapItemToRow(OrcamentoItem $item): array
    {
        return [
            'id' => $item->id,
            'key' => 'item-' . $item->id,
            'item' => (int) $item->item,
            'product_id' => $item->product_id,
            'product_codigo' => $item->product?->codigo ?? '',
            'descricao' => mb_strtoupper((string) ($item->descricao ?? $item->product?->descricao ?? ''), 'UTF-8'),
            'quantidade' => ErpMoney::formatBr((float) $item->quantidade, 3),
            'unidade' => mb_strtoupper((string) ($item->product?->unidade ?? 'UN'), 'UTF-8'),
            'preco_unitario' => ErpMoney::formatBr((float) $item->preco_unitario),
            'total' => ErpMoney::formatBr((float) $item->total),
            'preco_variavel' => (bool) ($item->product?->preco_variavel ?? false),
            'product_grade_id' => $item->product_grade_id,
            'grade_descricao' => mb_strtoupper((string) ($item->grade?->descricao ?? ''), 'UTF-8'),
        ];
    }

    protected function syncTotaisDisplay(
        float $subtotal,
        float $desconto,
        float $total,
        ?float $percentual = null,
    ): void {
        $this->subtotalDisplay = ErpMoney::formatBr($subtotal);
        $this->descontoValorDisplay = ErpMoney::formatBr($desconto);
        $this->totalDisplay = ErpMoney::formatBr($total);

        if ($percentual === null) {
            $percentual = $subtotal > 0
                ? round(($desconto / $subtotal) * 100, 2)
                : 0.0;
        }

        $this->percentualDescontoDisplay = ErpMoney::formatBr($percentual, 2);
    }

    public function updatedClienteSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->clienteSearch !== $upper) {
            $this->clienteSearch = $upper;
        }

        $this->clienteLookupOpen = true;
        $this->refreshClienteResults();
    }

    public function openClienteLookup(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        $this->clienteLookupOpen = true;

        if (filled(trim($this->clienteSearch))) {
            $this->refreshClienteResults();
        }
    }

    public function refreshClienteResults(): void
    {
        $term = trim($this->clienteSearch);

        $query = Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true);

        if ($term !== '') {
            $like = '%' . $term . '%';
            $digits = preg_replace('/\D/', '', $term) ?? '';

            $query->where(function ($sub) use ($like, $digits, $term): void {
                $sub->where('nome_razao', 'like', $like)
                    ->orWhere('apelido_fantasia', 'like', $like)
                    ->orWhere('cpf_cnpj', 'like', $like);

                if (strlen($digits) >= 2) {
                    $digitsLike = '%' . $digits . '%';
                    $sub->orWhereRaw(
                        "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
                        [$digitsLike]
                    );
                }

                if (ctype_digit($term)) {
                    $sub->orWhere('codigo', 'like', $like);
                }
            });
        }

        $this->clienteResults = $query
            ->orderBy('nome_razao')
            ->limit(50)
            ->get()
            ->map(fn (Person $person): array => [
                'id' => $person->id,
                'nome' => mb_strtoupper($person->nome_razao, 'UTF-8'),
                'fantasia' => mb_strtoupper((string) ($person->apelido_fantasia ?? ''), 'UTF-8'),
                'cpf_cnpj' => $person->cpf_cnpj ?? '',
            ])
            ->all();

        $this->selectedClienteIndex = $this->clienteResults === [] ? null : 0;
    }

    public function moveClienteSelection(int $delta): void
    {
        if ($this->clienteResults === []) {
            return;
        }

        $index = ($this->selectedClienteIndex ?? 0) + $delta;
        $count = count($this->clienteResults);
        $this->selectedClienteIndex = max(0, min($count - 1, $index));
    }

    public function selectClienteResult(int $index): void
    {
        if (! isset($this->clienteResults[$index])) {
            return;
        }

        $this->selectedClienteIndex = $index;
        $this->confirmClienteSelection();
    }

    public function confirmClienteSelection(): void
    {
        $index = $this->selectedClienteIndex;

        if ($index === null || ! isset($this->clienteResults[$index])) {
            $this->clienteLookupOpen = false;

            return;
        }

        $row = $this->clienteResults[$index];
        $person = Person::query()->find($row['id']);

        if (! $person) {
            return;
        }

        $this->clienteId = $person->id;
        $this->clienteSearch = mb_strtoupper($person->nome_razao, 'UTF-8');
        $this->applyClienteFields($person);
        $this->clienteLookupOpen = false;
        $this->clienteResults = [];
        $this->selectedClienteIndex = null;
        $this->dispatch('erp-orcamento-masks-refresh');
        $this->dispatch('erp-orcamento-focus-item-codigo');
    }

    public function handleClienteEnter(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        if ($this->clienteLookupOpen) {
            $this->confirmClienteSelection();
        }

        if ($this->clienteId !== null) {
            $this->dispatch('erp-orcamento-focus-item-codigo');
        }
    }

    protected function applyClienteFields(?Person $person): void
    {
        if (! $person) {
            $this->clienteCpfCnpj = '';
            $this->clienteEndereco = '';
            $this->clienteNumero = '';
            $this->clienteBairro = '';
            $this->clienteCep = '';
            $this->clienteCidade = '';
            $this->clienteUf = 'SC';
            $this->clienteFone = '';
            $this->clienteWhatsapp = '';

            return;
        }

        $this->clienteCpfCnpj = $person->cpf_cnpj ?? '';
        $this->clienteEndereco = mb_strtoupper((string) ($person->endereco ?? ''), 'UTF-8');
        $this->clienteNumero = (string) ($person->numero ?? '');
        $this->clienteBairro = mb_strtoupper((string) ($person->bairro ?? ''), 'UTF-8');
        $this->clienteCep = $person->cep ?? '';
        $this->clienteCidade = mb_strtoupper((string) ($person->cidade_nome ?? ''), 'UTF-8');
        $this->clienteUf = mb_strtoupper((string) ($person->uf ?? 'SC'), 'UTF-8');
        $this->clienteFone = $person->fone1 ?? '';
        $this->clienteWhatsapp = $person->celular1 ?? $person->whatsapp ?? '';

        if ($person->vendedor_loja_id) {
            $this->vendedorId = $person->vendedor_loja_id;
        }
    }

    public function closeClienteLookup(): void
    {
        $this->clienteLookupOpen = false;
    }

    public function confirmClienteSelectionOnBlur(): void
    {
        if (! $this->clienteLookupOpen) {
            return;
        }

        if ($this->selectedClienteIndex !== null && isset($this->clienteResults[$this->selectedClienteIndex])) {
            $this->confirmClienteSelection();

            return;
        }

        $this->closeClienteLookup();
    }

    public function applyDescontoFromPercentual(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        $subtotal = ErpMoney::parseBr($this->subtotalDisplay);
        $percentual = ErpMoney::parseBr($this->percentualDescontoDisplay);
        $desconto = round($subtotal * $percentual / 100, 2);
        $total = round(max(0, $subtotal - $desconto), 2);

        $this->descontoValorDisplay = ErpMoney::formatBr($desconto);
        $this->totalDisplay = ErpMoney::formatBr($total);
    }

    public function applyDescontoFromValor(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        $subtotal = ErpMoney::parseBr($this->subtotalDisplay);
        $desconto = ErpMoney::parseBr($this->descontoValorDisplay);
        $total = round(max(0, $subtotal - $desconto), 2);

        $percentual = $subtotal > 0
            ? round(100 - (($total * 100) / $subtotal), 2)
            : 0.0;

        $this->percentualDescontoDisplay = ErpMoney::formatBr($percentual, 2);
        $this->totalDisplay = ErpMoney::formatBr($total);
    }

    protected function recalcHeaderFromItens(): void
    {
        $subtotal = 0.0;

        foreach ($this->itens as $row) {
            $subtotal += ErpMoney::parseBr($row['total'] ?? 0);
        }

        $desconto = ErpMoney::parseBr($this->descontoValorDisplay);
        $total = round(max(0, $subtotal - $desconto), 2);
        $percentual = $subtotal > 0 ? round(($desconto / $subtotal) * 100, 2) : 0.0;

        $this->syncTotaisDisplay($subtotal, $desconto, $total, $percentual);
    }

    public function selectItemRow(int $index): void
    {
        $this->selectedItemIndex = $index;
    }

    public function updateItemField(int $index, string $field, string $value): void
    {
        if ($this->orcamentoReadOnly() || ! isset($this->itens[$index])) {
            return;
        }

        if (! in_array($field, ['quantidade', 'preco_unitario', 'descricao'], true)) {
            return;
        }

        if ($field === 'preco_unitario' && ! ($this->itens[$index]['preco_variavel'] ?? false)) {
            return;
        }

        $itens = $this->itens;
        $itens[$index][$field] = $field === 'descricao'
            ? mb_strtoupper(trim($value), 'UTF-8')
            : $value;

        if ($field !== 'descricao') {
            $itens[$index] = $this->recalcItemRowData($itens[$index]);
        }

        $this->itens = $itens;
        $this->recalcHeaderFromItens();
    }

    public function blurItemFieldByKey(string $key, string $field, string $value): void
    {
        $index = $this->findItemIndexByKey($key);

        if ($index === null) {
            return;
        }

        $this->updateItemField($index, $field, $value);
    }

    protected function findItemIndexByKey(string $key): ?int
    {
        foreach ($this->itens as $index => $row) {
            if (($row['key'] ?? '') === $key) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function recalcItemRowData(array $row): array
    {
        $qtd = ErpMoney::parseBr($row['quantidade'] ?? 0, 3);
        $preco = ErpMoney::parseBr($row['preco_unitario'] ?? 0);

        if ($qtd < 0) {
            $qtd = 0;
        }

        if ($preco < 0) {
            $preco = 0;
        }

        $row['quantidade'] = ErpMoney::formatBr($qtd, 3);
        $row['preco_unitario'] = ErpMoney::formatBr($preco);
        $row['total'] = ErpMoney::formatBr(round($qtd * $preco, 2));

        return $row;
    }

    protected function recalcItemRow(int $index): void
    {
        if (! isset($this->itens[$index])) {
            return;
        }

        $itens = $this->itens;
        $itens[$index] = $this->recalcItemRowData($itens[$index]);
        $this->itens = $itens;
    }

    public function resolveItemDisplayNumber(int $index, ?int $total = null): int
    {
        $total ??= count($this->itens);

        return max(1, $total - $index);
    }

    /**
     * @param  array<int, array<string, mixed>>  $itens
     * @return array<int, array<string, mixed>>
     */
    protected function renumberItens(array $itens): array
    {
        $total = count($itens);

        foreach (array_keys($itens) as $index) {
            $itens[$index]['item'] = $this->resolveItemDisplayNumber($index, $total);
        }

        return $itens;
    }

    protected function recalcAllItens(): void
    {
        $itens = array_values($this->itens);

        foreach (array_keys($itens) as $index) {
            $itens[$index] = $this->recalcItemRowData($itens[$index]);
        }

        $this->itens = $this->renumberItens($itens);
    }

    public function deleteSelectedItem(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        if ($this->selectedItemIndex === null || ! isset($this->itens[$this->selectedItemIndex])) {
            Notification::make()
                ->title('Selecione um item para excluir.')
                ->warning()
                ->send();

            return;
        }

        $this->requestDeleteItem($this->selectedItemIndex);
    }

    public function requestDeleteItem(int $index): void
    {
        if ($this->orcamentoReadOnly() || ! isset($this->itens[$index])) {
            return;
        }

        $this->selectedItemIndex = $index;
        $this->itemDeleteConfirmIndex = $index;
        $this->dispatch('erp-orcamento-item-delete-opened');
    }

    public function confirmDeleteItem(): void
    {
        if ($this->itemDeleteConfirmIndex === null || ! isset($this->itens[$this->itemDeleteConfirmIndex])) {
            $this->itemDeleteConfirmIndex = null;

            return;
        }

        $index = $this->itemDeleteConfirmIndex;
        $this->itemDeleteConfirmIndex = null;

        $itens = $this->itens;
        array_splice($itens, $index, 1);
        $this->itens = array_values($itens);
        $this->selectedItemIndex = null;
        $this->recalcAllItens();
        $this->recalcHeaderFromItens();
        $this->dispatch('erp-orcamento-focus-item-codigo');
    }

    public function cancelDeleteItem(): void
    {
        $this->itemDeleteConfirmIndex = null;
    }

    public function submitBarcodeItem(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        $raw = trim($this->barcodeInput);

        if ($raw === '') {
            return;
        }

        $qtd = 1.0;
        $term = $raw;

        if (str_contains($raw, '*')) {
            [$qtdPart, $codePart] = explode('*', $raw, 2);
            $qtd = max(0.001, ErpMoney::parseBr($qtdPart, 3));
            $term = trim($codePart);
        }

        $term = mb_strtoupper($term, 'UTF-8');
        $product = $this->findProductByTerm($term);

        if (! $product) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Verifique o código ou código de barras informado.')
                ->warning()
                ->send();

            return;
        }

        $this->appendProductItem($product, $qtd);
        $this->barcodeInput = '';
        $this->clearItemEntryRow();
        $this->dispatch('erp-orcamento-focus-item-codigo');
    }

    public function handleItemCodigoEnter(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        if (blank(trim($this->itemCodigoInput))) {
            $this->dispatch('erp-orcamento-focus-item-descricao');

            return;
        }

        $this->submitItemByCodigo();
    }

    public function submitItemByCodigo(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        $codigo = mb_strtoupper(trim($this->itemCodigoInput), 'UTF-8');

        if ($codigo === '') {
            return;
        }

        $product = $this->findProductByCodigo($codigo);

        if (! $product) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Verifique o código informado.')
                ->warning()
                ->send();
            $this->dispatch('erp-orcamento-focus-item-codigo');

            return;
        }

        $this->stageProductForEntry($product);
    }

    public function updatedItemQuantidadeInput(): void
    {
        $this->recalcEntryRowFromPending();
    }

    public function updatedItemPrecoInput(): void
    {
        $this->recalcEntryRowFromPending();
    }

    public function handleItemQuantidadeEnter(): void
    {
        if ($this->itemPendingPrecoVariavel) {
            $this->dispatch('erp-orcamento-focus-item-preco');

            return;
        }

        $this->confirmPendingItemEntry();
    }

    public function confirmPendingItemEntry(): void
    {
        if ($this->orcamentoReadOnly() || $this->isConfirmingPendingItem) {
            return;
        }

        if ($this->itemPendingProductId === null) {
            return;
        }

        $product = Product::query()->find($this->itemPendingProductId);

        if (! $product) {
            $this->clearItemEntryRow();

            return;
        }

        $qtd = ErpMoney::parseBr($this->itemQuantidadeInput, 3);

        if ($qtd <= 0) {
            Notification::make()
                ->title('Informe a quantidade do item.')
                ->warning()
                ->send();
            $this->dispatch('erp-orcamento-focus-item-quantidade');

            return;
        }

        $preco = ErpMoney::parseBr($this->itemPrecoInput);
        $precoVariavel = $this->itemPendingPrecoVariavel;

        if ($precoVariavel && $preco <= 0) {
            Notification::make()
                ->title('Informe o preço do item.')
                ->warning()
                ->send();
            $this->dispatch('erp-orcamento-focus-item-preco');

            return;
        }

        $this->isConfirmingPendingItem = true;

        try {
            $this->itemPendingProductId = null;
            $this->itemPendingPrecoVariavel = false;
            $this->appendProductItem($product, $qtd, $precoVariavel ? $preco : null);
            $this->clearItemEntryRow();
            $this->dispatch('erp-orcamento-focus-item-codigo');
        } finally {
            $this->isConfirmingPendingItem = false;
        }
    }

    protected function stageProductForEntry(Product $product): void
    {
        $this->itemPendingProductId = $product->id;
        $this->itemPendingPrecoVariavel = (bool) $product->preco_variavel;
        $this->itemCodigoInput = (string) $product->codigo;
        $this->itemProdutoSearch = mb_strtoupper($product->descricao, 'UTF-8');
        $this->itemQuantidadeInput = ErpMoney::formatBr(1, 3);
        $this->recalcEntryRowFromPending();
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->selectedProdutoIndex = null;
        $this->dispatch('erp-orcamento-focus-item-quantidade');
    }

    protected function recalcEntryRowFromPending(): void
    {
        if ($this->itemPendingProductId === null) {
            return;
        }

        $product = Product::query()->find($this->itemPendingProductId);

        if (! $product) {
            $this->clearItemEntryRow();

            return;
        }

        $qtd = ErpMoney::parseBr($this->itemQuantidadeInput, 3);

        if ($qtd <= 0) {
            $qtd = 1;
        }

        $precoService = app(OrcamentoPrecoService::class);
        $precoSugerido = $precoService->resolvePreco($product, $qtd);

        if (! $this->itemPendingPrecoVariavel || ErpMoney::parseBr($this->itemPrecoInput) <= 0) {
            $this->itemPrecoInput = ErpMoney::formatBr($precoSugerido);
        }

        $preco = ErpMoney::parseBr($this->itemPrecoInput);
        $total = round($qtd * $preco, 2);

        $this->itemQuantidadeInput = ErpMoney::formatBr($qtd, 3);
        $this->itemUnidadeDisplay = mb_strtoupper((string) ($product->unidade ?: 'UN'), 'UTF-8');
        $this->itemPrecoDisplay = ErpMoney::formatBr($preco);
        $this->itemTotalEntryDisplay = ErpMoney::formatBr($total);
    }

    protected function clearItemEntryRow(): void
    {
        $this->itemPendingProductId = null;
        $this->itemPendingPrecoVariavel = false;
        $this->itemCodigoInput = '';
        $this->itemProdutoSearch = '';
        $this->itemQuantidadeInput = '1,000';
        $this->itemUnidadeDisplay = '';
        $this->itemPrecoDisplay = '';
        $this->itemPrecoInput = '';
        $this->itemTotalEntryDisplay = '';
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->selectedProdutoIndex = null;
    }

    public function searchItemProduto(string $value): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        $this->itemProdutoSearch = mb_strtoupper($value, 'UTF-8');
        $this->produtoLookupOpen = true;
        $this->refreshProdutoResults();
    }

    public function updatedItemProdutoSearch(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        $this->produtoLookupOpen = true;
        $this->refreshProdutoResults();
    }

    public function openProdutoLookup(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        $this->produtoLookupOpen = true;

        if (filled(trim($this->itemProdutoSearch))) {
            $this->refreshProdutoResults();
        }
    }

    public function refreshProdutoResults(): void
    {
        $term = trim($this->itemProdutoSearch);

        if ($term === '') {
            $this->produtoResults = [];
            $this->selectedProdutoIndex = null;

            return;
        }

        $like = '%' . $term . '%';

        $this->produtoResults = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($like, $term): void {
                $query->where('codigo', 'like', $like)
                    ->orWhere('descricao', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('codigo_barras_caixa', 'like', $like);

                if (ctype_digit($term)) {
                    $query->orWhere('codigo', $term);
                }
            })
            ->orderBy('descricao')
            ->limit(50)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'codigo' => mb_strtoupper((string) $product->codigo, 'UTF-8'),
                'descricao' => mb_strtoupper($product->descricao, 'UTF-8'),
            ])
            ->all();

        $this->selectedProdutoIndex = $this->produtoResults === [] ? null : 0;
    }

    public function moveProdutoSelection(int $delta): void
    {
        if ($this->produtoResults === []) {
            return;
        }

        $index = ($this->selectedProdutoIndex ?? 0) + $delta;
        $count = count($this->produtoResults);
        $this->selectedProdutoIndex = max(0, min($count - 1, $index));
    }

    public function selectProdutoResult(int $index): void
    {
        if (! isset($this->produtoResults[$index])) {
            return;
        }

        $this->selectedProdutoIndex = $index;
        $this->confirmProdutoSelection();
    }

    public function confirmProdutoSelection(): void
    {
        $index = $this->selectedProdutoIndex;

        if ($index === null || ! isset($this->produtoResults[$index])) {
            return;
        }

        $row = $this->produtoResults[$index];
        $product = Product::query()->find($row['id']);

        if (! $product) {
            return;
        }

        $this->stageProductForEntry($product);
    }

    public function submitItemProdutoSearch(?string $term = null): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        if ($term !== null) {
            $this->itemProdutoSearch = mb_strtoupper($term, 'UTF-8');
        }

        $term = trim($this->itemProdutoSearch);

        if ($term === '') {
            return;
        }

        $this->refreshProdutoResults();

        if ($this->produtoResults === []) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Verifique o código ou a descrição informada.')
                ->warning()
                ->send();
            $this->dispatch('erp-orcamento-focus-item-descricao');

            return;
        }

        if (count($this->produtoResults) === 1) {
            $this->selectProdutoResult(0);

            return;
        }

        if ($this->selectedProdutoIndex !== null && isset($this->produtoResults[$this->selectedProdutoIndex])) {
            $this->confirmProdutoSelection();

            return;
        }

        $this->produtoLookupOpen = true;
        $this->selectedProdutoIndex = 0;
        $this->dispatch('erp-orcamento-focus-item-descricao');
    }

    public function closeProdutoLookup(): void
    {
        $this->produtoLookupOpen = false;
    }

    protected function appendProductItem(Product $product, float $qtd = 1.0, ?float $preco = null): void
    {
        $precoService = app(OrcamentoPrecoService::class);
        $preco ??= $precoService->resolvePreco($product, $qtd);
        $total = round($qtd * $preco, 2);

        $itens = $this->itens;
        $newItem = [
            'id' => null,
            'key' => 'new-' . Str::uuid()->toString(),
            'item' => count($itens) + 1,
            'product_id' => $product->id,
            'product_codigo' => $product->codigo,
            'descricao' => mb_strtoupper($product->descricao, 'UTF-8'),
            'quantidade' => ErpMoney::formatBr($qtd, 3),
            'unidade' => mb_strtoupper((string) ($product->unidade ?: 'UN'), 'UTF-8'),
            'preco_unitario' => ErpMoney::formatBr($preco),
            'total' => ErpMoney::formatBr($total),
            'preco_variavel' => (bool) $product->preco_variavel,
            'product_grade_id' => null,
            'grade_descricao' => '',
        ];
        array_unshift($itens, $newItem);
        $this->itens = $this->renumberItens($itens);

        $this->selectedItemIndex = 0;
        $this->recalcHeaderFromItens();
    }

    protected function findProductByCodigo(string $codigo): ?Product
    {
        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($codigo): void {
                $query->where('codigo', $codigo)
                    ->orWhere('referencia', $codigo)
                    ->orWhere('codigo_barras', $codigo)
                    ->orWhere('codigo_barras_caixa', $codigo);
            })
            ->first();
    }

    protected function findProductByTerm(string $term): ?Product
    {
        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term): void {
                $query->where('codigo', $term)
                    ->orWhere('codigo_barras', $term)
                    ->orWhere('codigo_barras_caixa', $term)
                    ->orWhere('referencia', $term)
                    ->orWhere('descricao', 'like', '%' . $term . '%');
            })
            ->first();
    }

    protected function validateBeforeSave(bool $finalizar): bool
    {
        if ($this->clienteId === null || blank($this->clienteSearch)) {
            Notification::make()->title('Informe o Cliente!')->warning()->send();

            return false;
        }

        if ($this->vendedorId === null) {
            Notification::make()->title('Informe o Vendedor!')->warning()->send();

            return false;
        }

        if ($finalizar && $this->itens === []) {
            Notification::make()->title('Informe os Itens do Orçamento!')->warning()->send();

            return false;
        }

        foreach ($this->itens as $index => $row) {
            if (blank($row['descricao'] ?? null)) {
                Notification::make()
                    ->title('Informe a Descrição do Produto')
                    ->body('Item ' . $this->resolveItemDisplayNumber($index))
                    ->warning()
                    ->send();

                return false;
            }
        }

        return true;
    }

    public function gravarOrcamento(): void
    {
        if (! $this->validateBeforeSave(finalizar: false)) {
            return;
        }

        if (! $this->persistOrcamento(finalizar: false)) {
            return;
        }

        if ($this->isEditingOrcamento()) {
            $this->notifyOrcamentoGravado();
            $this->openPostSavePrompt();
        }
    }

    public function finalizarOrcamento(): void
    {
        if (! $this->validateBeforeSave(finalizar: true)) {
            return;
        }

        $this->sincronizarPrecosComCadastro(notify: true);

        if (! $this->persistOrcamento(finalizar: true)) {
            return;
        }

        Notification::make()
            ->title('Orçamento finalizado.')
            ->success()
            ->send();

        $this->redirectToErpFormReturnOr(
            OrcamentoResource::getUrl('index'),
            'Orçamentos',
        );
    }

    protected function persistOrcamento(bool $finalizar): bool
    {
        $subtotal = ErpMoney::parseBr($this->subtotalDisplay);
        $desconto = ErpMoney::parseBr($this->descontoValorDisplay);
        $percentual = ErpMoney::parseBr($this->percentualDescontoDisplay);
        $total = ErpMoney::parseBr($this->totalDisplay);
        $createdId = null;

        try {
            DB::transaction(function () use ($subtotal, $desconto, $percentual, $total, $finalizar, &$createdId): void {
                $attributes = [
                    'data' => $this->data['data'] ?? now()->format('Y-m-d'),
                    'cliente_id' => $this->clienteId,
                    'vendedor_id' => $this->vendedorId,
                    'subtotal' => $subtotal,
                    'percentual_desconto' => $percentual,
                    'desconto_valor' => $desconto,
                    'forma_pagamento' => mb_strtoupper(trim($this->formaPagamento), 'UTF-8') ?: null,
                    'validade_dias' => max(0, (int) $this->validadeDias),
                    'observacoes' => trim($this->observacoes) ?: null,
                    'total' => $total,
                    'status' => $finalizar ? Orcamento::STATUS_FECHADO : Orcamento::STATUS_ABERTO,
                ];

                if ($this->isEditingOrcamento()) {
                    /** @var Orcamento $orcamento */
                    $orcamento = $this->record;
                    $orcamento->update($attributes);
                } else {
                    $orcamento = Orcamento::query()->create([
                        'numero' => Orcamento::nextNumero(),
                        ...$attributes,
                    ]);
                    $createdId = $orcamento->getKey();
                }

                $keptIds = [];

                foreach ($this->itens as $index => $row) {
                    $itemData = [
                        'item' => (int) ($row['item'] ?? $this->resolveItemDisplayNumber($index)),
                        'product_id' => (int) $row['product_id'],
                        'product_grade_id' => filled($row['product_grade_id'] ?? null)
                            ? (int) $row['product_grade_id']
                            : null,
                        'quantidade' => ErpMoney::parseBr($row['quantidade'] ?? 0, 3),
                        'preco_unitario' => ErpMoney::parseBr($row['preco_unitario'] ?? 0),
                        'total' => ErpMoney::parseBr($row['total'] ?? 0),
                        'descricao' => mb_strtoupper((string) ($row['descricao'] ?? ''), 'UTF-8'),
                        'desconto' => 0,
                    ];

                    if (filled($row['id'] ?? null)) {
                        $item = OrcamentoItem::query()->find($row['id']);

                        if ($item && $item->orcamento_id === $orcamento->id) {
                            $item->update($itemData);
                            $keptIds[] = $item->id;
                            $this->itens[$index]['id'] = $item->id;

                            continue;
                        }
                    }

                    $item = $orcamento->itens()->create($itemData);
                    $keptIds[] = $item->id;
                    $this->itens[$index]['id'] = $item->id;
                    $this->itens[$index]['key'] = 'item-' . $item->id;
                }

                $orcamento->itens()->whereNotIn('id', $keptIds)->delete();

                app(OrcamentoTotaisService::class)->recalcular($orcamento->fresh(['itens']));

                if ($desconto > 0) {
                    app(OrcamentoDescontoService::class)->ratearDesconto($orcamento->fresh(['itens']));
                }
            });
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível salvar o orçamento.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return false;
        }

        if ($createdId !== null && ! $finalizar) {
            session()->flash('erp_orcamento_post_save_prompt', true);

            $this->redirect(
                $this->orcamentoUrlWithReturn(
                    OrcamentoResource::getUrl('edit', ['record' => $createdId]),
                ),
                navigate: false,
            );

            return true;
        }

        if ($this->isEditingOrcamento()) {
            $this->loadOrcamentoFormFromRecord($this->record->fresh(['cliente', 'itens.product', 'itens.grade']));
        }

        return true;
    }

    protected function orcamentoUrlWithReturn(string $url): string
    {
        $returnUrl = $this->resolveErpFormReturnUrl();

        if ($returnUrl !== null && ErpFormReturnUrl::isMonitorUrl($returnUrl)) {
            return ErpFormReturnUrl::appendToUrl($url, $returnUrl);
        }

        return $url;
    }

    public function getProductOverlayUrlProperty(): string
    {
        return ProductResource::getUrl('create') . '?orcamento=1';
    }

    public function getPersonOverlayUrlProperty(): string
    {
        return PersonResource::getUrl('create') . '?tipo=clientes&orcamento=1';
    }

    public function closeProductOverlay(): void
    {
        if (! $this->overlayProductOpen) {
            return;
        }

        $this->overlayProductOpen = false;
        ErpScreen::set('Lançamento de Orçamento');
    }

    public function closePersonOverlay(): void
    {
        if (! $this->overlayPersonOpen) {
            return;
        }

        $this->overlayPersonOpen = false;
        ErpScreen::set('Lançamento de Orçamento');
    }

    public function applyOverlayProdutoSaved(string $codigo): void
    {
        if (filled($codigo)) {
            $this->itemCodigoInput = mb_strtoupper(trim($codigo), 'UTF-8');
        }

        $this->closeProductOverlay();
        $this->dispatch('erp-orcamento-focus-item-codigo');
    }

    public function applyOverlayPersonSaved(int $clienteId): void
    {
        $person = Person::query()->find($clienteId);

        if ($person) {
            $this->clienteId = $person->id;
            $this->clienteSearch = mb_strtoupper($person->nome_razao, 'UTF-8');
            $this->applyClienteFields($person);
        }

        $this->closePersonOverlay();
        $this->dispatch('erp-orcamento-focus-item-codigo');
    }

    public function handleOrcamentoFormEscape(): void
    {
        if ($this->postSavePromptOpen) {
            $this->sairAposGravarOrcamento();

            return;
        }

        if ($this->overlayProductOpen) {
            $this->closeProductOverlay();

            return;
        }

        if ($this->overlayPersonOpen) {
            $this->closePersonOverlay();

            return;
        }

        $this->cancelForm();
    }

    public function handlePostSavePromptEscape(): void
    {
        $this->sairAposGravarOrcamento();
    }

    protected function notifyOrcamentoGravado(): void
    {
        Notification::make()
            ->title('Orçamento gravado com sucesso!')
            ->success()
            ->send();
    }

    public function openPostSavePromptFromSession(): void
    {
        $this->notifyOrcamentoGravado();
        $this->openPostSavePrompt();
    }

    protected function openPostSavePrompt(): void
    {
        $this->postSavePromptOpen = true;
        $this->dispatch('erp-orcamento-post-save-prompt-opened');
    }

    public function continuarOrcamentoAposGravar(): void
    {
        $this->postSavePromptOpen = false;
        $this->dispatch('erp-orcamento-focus-item-codigo');
    }

    public function sairAposGravarOrcamento(): void
    {
        $this->postSavePromptOpen = false;

        $this->redirectToErpFormReturnOr(
            OrcamentoResource::getUrl('index'),
            'Orçamentos',
        );
    }

    public function iniciarNovoOrcamento(): void
    {
        $this->postSavePromptOpen = false;
        ErpScreen::set('Lançamento de Orçamento');
        $this->redirect(
            $this->orcamentoUrlWithReturn(OrcamentoResource::getUrl('create')),
            navigate: false,
        );
    }

    public function cancelForm(): void
    {
        $this->redirectToErpFormReturnOr(
            OrcamentoResource::getUrl('index'),
            'Orçamentos',
        );
    }

    public function openProdutosCadastro(): void
    {
        ErpScreen::set('Cadastro de Produtos');
        $this->overlayPersonOpen = false;
        $this->overlayProductOpen = true;
    }

    public function openPessoasCadastro(): void
    {
        ErpScreen::set('Cadastro de Pessoas');
        $this->overlayProductOpen = false;
        $this->overlayPersonOpen = true;
    }

    public function openProdutoFromItem(int $index): void
    {
        $productId = (int) ($this->itens[$index]['product_id'] ?? 0);

        if ($productId <= 0) {
            return;
        }

        $this->redirect(ProductResource::getUrl('edit', ['record' => $productId]));
    }
}
