<?php

namespace App\Filament\Resources\OrcamentoResource\Pages\Concerns;

use App\Filament\Concerns\InteractsWithErpFormReturnUrl;
use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\PersonResource;
use App\Filament\Resources\ProductResource;
use App\Models\FormaPagamento;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductGrade;
use App\Models\Vendedor;
use App\Support\Erp\CepLookupService;
use App\Support\Erp\ErpFormReturnUrl;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\MunicipioLookupService;
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
use RuntimeException;

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

    public bool $clienteAvulsoMode = false;

    public string $clienteCpfCnpj = '';

    public string $clienteEndereco = '';

    public string $clienteNumero = '';

    public string $clienteBairro = '';

    public string $clienteCep = '';

    public string $clienteCidade = '';

    public string $clienteUf = 'SC';

    /** @var list<array{codigo: string, nome: string, uf: string}> */
    public array $orcCidadeSugestoes = [];

    public bool $orcCidadeSugestoesOpen = false;

    public int $orcCidadeSugestaoIndex = -1;

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

    public string $itemPrecoInput = '0,00';

    public bool $itemPendingPrecoVariavel = false;

    public string $itemTotalEntryDisplay = '0,00';

    public string $itemPendingDesconto = '0,00';

    public string $itemPendingAcrescimo = '0,00';

    public ?string $produtoAtualFoto = null;

    public string $produtoAtualNome = '';

    public bool $descontoModalOpen = false;

    public ?string $itemAjusteAlvo = null;

    public string $itemAjusteTipo = 'desconto';

    public string $itemAjusteModo = 'percentual';

    public string $itemAjusteValor = '0,00';

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

    /**
     * @return array<int, array{id: int, descricao: string}>
     */
    public function formaPagamentoOptions(): array
    {
        $options = FormaPagamento::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->orderBy('descricao')
            ->get(['id', 'descricao'])
            ->map(function (FormaPagamento $forma): array {
                $descricao = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');

                return [
                    'id' => (int) $forma->id,
                    'descricao' => $descricao,
                ];
            })
            ->filter(fn (array $row): bool => $row['descricao'] !== '')
            ->values()
            ->all();

        $atual = mb_strtoupper(trim($this->formaPagamento), 'UTF-8');

        if ($atual !== '' && ! collect($options)->contains(fn (array $row): bool => $row['descricao'] === $atual)) {
            array_unshift($options, [
                'id' => 0,
                'descricao' => $atual,
            ]);
        }

        return $options;
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

        $isCf = $cliente && (
            Person::isCodigoConsumidorFinal($cliente->codigo)
            || mb_strtoupper(trim((string) $cliente->nome_razao), 'UTF-8') === 'CONSUMIDOR FINAL'
        );
        $hasSnapshot = filled($orcamento->cliente_nome)
            || filled($orcamento->cliente_cpf_cnpj)
            || filled($orcamento->cliente_endereco)
            || filled($orcamento->cliente_fone)
            || filled($orcamento->cliente_whatsapp);

        if ($hasSnapshot) {
            $this->clienteAvulsoMode = true;
            $this->clienteSearch = mb_strtoupper((string) ($orcamento->cliente_nome ?: $cliente?->nome_razao), 'UTF-8');
            $this->clienteCpfCnpj = (string) ($orcamento->cliente_cpf_cnpj ?? '');
            $this->clienteEndereco = mb_strtoupper((string) ($orcamento->cliente_endereco ?? ''), 'UTF-8');
            $this->clienteNumero = (string) ($orcamento->cliente_numero ?? '');
            $this->clienteBairro = mb_strtoupper((string) ($orcamento->cliente_bairro ?? ''), 'UTF-8');
            $this->clienteCep = (string) ($orcamento->cliente_cep ?? '');
            $this->clienteCidade = mb_strtoupper((string) ($orcamento->cliente_cidade ?? ''), 'UTF-8');
            $this->clienteUf = mb_strtoupper((string) ($orcamento->cliente_uf ?: 'SC'), 'UTF-8');
            $this->clienteFone = (string) ($orcamento->cliente_fone ?? '');
            $this->clienteWhatsapp = (string) ($orcamento->cliente_whatsapp ?? '');
        } else {
            $this->clienteAvulsoMode = $isCf;
            $this->clienteSearch = mb_strtoupper($cliente?->nome_razao ?? '', 'UTF-8');
            $this->applyClienteFields($cliente, enterAvulso: $isCf);
        }

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
                $this->applyClienteFields($person, enterAvulso: true);
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
        $qtd = (float) $item->quantidade;
        $preco = (float) $item->preco_unitario;
        $desconto = (float) $item->desconto;
        $bruto = round($qtd * $preco, 2);
        $total = (float) $item->total;
        $acrescimo = max(0, round($total - ($bruto - $desconto), 2));

        return [
            'id' => $item->id,
            'key' => 'item-' . $item->id,
            'item' => (int) $item->item,
            'product_id' => $item->product_id,
            'product_codigo' => $item->product?->codigo ?? '',
            'descricao' => mb_strtoupper((string) ($item->descricao ?? $item->product?->descricao ?? ''), 'UTF-8'),
            'quantidade' => ErpMoney::formatBr($qtd, 3),
            'unidade' => mb_strtoupper((string) ($item->product?->unidade ?? 'UN'), 'UTF-8'),
            'preco_unitario' => ErpMoney::formatBr($preco),
            'acrescimo' => ErpMoney::formatBr($acrescimo),
            'desconto' => ErpMoney::formatBr($desconto),
            'total' => ErpMoney::formatBr($total),
            'preco_variavel' => (bool) ($item->product?->preco_variavel ?? false),
            'product_grade_id' => $item->product_grade_id,
            'grade_descricao' => mb_strtoupper((string) ($item->grade?->descricao ?? ''), 'UTF-8'),
            'foto' => $item->product?->fotoUrl(),
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

        $term = trim($this->clienteSearch);

        if ($term === '') {
            $this->clienteAvulsoMode = false;
            $this->clienteId = null;
            $this->applyClienteFields(null);
            $this->clienteLookupOpen = true;
            $this->refreshClienteResults();

            return;
        }

        // Em modo avulso o campo é o nome digitado — não abrir lookup a cada tecla.
        if ($this->clienteAvulsoMode && $this->clienteId !== null) {
            $this->clienteLookupOpen = false;

            return;
        }

        $this->clienteLookupOpen = true;
        $this->refreshClienteResults();
    }

    public function openClienteLookup(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        // Modo avulso: não abrir lookup no foco — usuário preenche dados manuais.
        if ($this->clienteAvulsoMode && $this->clienteId !== null) {
            return;
        }

        $this->clienteLookupOpen = true;
        $this->refreshClienteResults();
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

        // Garante CONSUMIDOR FINAL nas sugestões quando a busca bate.
        $termUpper = mb_strtoupper($term, 'UTF-8');
        $cfHint = $term === ''
            || str_contains('CONSUMIDOR FINAL', $termUpper)
            || str_contains($termUpper, 'CONSUMIDOR')
            || in_array($termUpper, ['CF', '000001'], true);

        if ($cfHint) {
            $cf = Person::resolveConsumidorFinal();
            $already = collect($this->clienteResults)->contains(fn (array $row): bool => (int) $row['id'] === (int) $cf->id);

            if (! $already) {
                array_unshift($this->clienteResults, [
                    'id' => $cf->id,
                    'nome' => mb_strtoupper($cf->nome_razao, 'UTF-8'),
                    'fantasia' => '',
                    'cpf_cnpj' => $cf->cpf_cnpj ?? '',
                ]);
                $this->clienteResults = array_slice($this->clienteResults, 0, 50);
            }
        }

        $this->selectedClienteIndex = $this->clienteResults === [] ? null : 0;
    }

    public function moveClienteSelection(int $delta): void
    {
        if ($this->clienteResults === [] && ! $this->clienteLookupOpen) {
            $this->clienteLookupOpen = true;
            $this->refreshClienteResults();
        }

        if ($this->clienteResults === []) {
            return;
        }

        $index = ($this->selectedClienteIndex ?? 0) + $delta;
        $count = count($this->clienteResults);
        $this->selectedClienteIndex = max(0, min($count - 1, $index));
        $this->clienteLookupOpen = true;
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

        $this->applyRegisteredOrAvulsoCliente($person);
    }

    /**
     * Enter no nome do cliente:
     * - lista aberta → confirma a linha destacada (inclusive a primeira);
     * - lista fechada (Esc) ou sem resultados → cadastro exato ou modo avulso;
     * - já em modo avulso → Enter avança campo a campo.
     */
    public function handleClienteEnter(?string $typed = null): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        if (is_string($typed)) {
            $this->clienteSearch = mb_strtoupper(trim($typed), 'UTF-8');
        }

        if ($this->clienteAvulsoMode) {
            $this->clienteLookupOpen = false;
            $this->focusNextClienteAvulsoField('orc-cliente');

            return;
        }

        $nome = mb_strtoupper(trim($this->clienteSearch), 'UTF-8');

        if ($nome === '') {
            $this->clienteLookupOpen = false;

            return;
        }

        if (
            $this->clienteLookupOpen
            && $this->clienteResults !== []
            && $this->selectedClienteIndex !== null
            && isset($this->clienteResults[$this->selectedClienteIndex])
        ) {
            $this->confirmClienteSelection();

            return;
        }

        $exact = $this->findExactClienteMatch($nome);

        if ($exact) {
            $this->applyRegisteredOrAvulsoCliente($exact);

            return;
        }

        $this->enterClienteAvulsoMode($nome);
    }

    public function focusNextClienteAvulsoField(string $fromId): void
    {
        if (! $this->clienteAvulsoMode || $this->orcamentoReadOnly()) {
            return;
        }

        $order = [
            'orc-cliente',
            'orc-cpf',
            'orc-fone',
            'orc-whatsapp',
            'orc-endereco',
            'orc-numero-end',
            'orc-bairro',
            'orc-cep',
            'orc-cidade',
            'orc-uf',
        ];

        $index = array_search($fromId, $order, true);

        if ($index === false) {
            return;
        }

        // Evita remount e perda de foco no Enter.
        $this->skipRender();

        if ($index >= count($order) - 1) {
            $this->dispatch('orc-focus-barcode');
            $this->dispatch('erp-orcamento-focus-item-codigo');

            return;
        }

        $this->dispatch('orc-focus-field', id: $order[$index + 1]);
    }

    /**
     * Cliente cadastrado (não CF) ou CF → modo avulso.
     */
    protected function applyRegisteredOrAvulsoCliente(Person $person): void
    {
        $this->clienteId = $person->id;
        $this->clienteLookupOpen = false;
        $this->clienteResults = [];
        $this->selectedClienteIndex = null;
        $this->dispatch('erp-orcamento-masks-refresh');

        if (Person::isCodigoConsumidorFinal($person->codigo)) {
            $nome = mb_strtoupper(trim($this->clienteSearch), 'UTF-8');
            if ($nome === '' || in_array($nome, ['CF', '000001', 'CONSUMIDOR FINAL'], true)) {
                $nome = 'CONSUMIDOR FINAL';
            }
            $this->enterClienteAvulsoMode($nome);

            return;
        }

        $this->clienteSearch = mb_strtoupper($person->nome_razao, 'UTF-8');
        $this->applyClienteFields($person, enterAvulso: false);
        $this->fecharOrcCidadeSugestoes();
        $this->dispatch('erp-orcamento-focus-item-codigo');
    }

    /**
     * Nome avulso: vincula CONSUMIDOR FINAL, libera campos e foca o CPF.
     */
    protected function enterClienteAvulsoMode(string $nome): void
    {
        $cf = Person::resolveConsumidorFinal();
        $this->clienteId = $cf->id;
        $this->clienteSearch = mb_strtoupper(trim($nome), 'UTF-8');
        $this->clienteAvulsoMode = true;
        $this->clienteCpfCnpj = '';
        $this->clienteEndereco = '';
        $this->clienteNumero = '';
        $this->clienteBairro = '';
        $this->clienteCep = '';
        $this->clienteCidade = '';
        $this->clienteUf = 'SC';
        $this->clienteFone = '';
        $this->clienteWhatsapp = '';
        $this->clienteLookupOpen = false;
        $this->clienteResults = [];
        $this->selectedClienteIndex = null;
        $this->fecharOrcCidadeSugestoes();
        $this->dispatch('erp-orcamento-masks-refresh');
        $this->dispatch('orc-focus-field', id: 'orc-cpf');
    }

    protected function findExactClienteMatch(string $nome): ?Person
    {
        $nome = mb_strtoupper(trim($nome), 'UTF-8');

        if ($nome === '') {
            return null;
        }

        $base = Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true);

        $byNome = (clone $base)
            ->whereRaw('UPPER(TRIM(nome_razao)) = ?', [$nome])
            ->orderBy('id')
            ->first();

        if ($byNome) {
            return $byNome;
        }

        $byFantasia = (clone $base)
            ->whereRaw('UPPER(TRIM(apelido_fantasia)) = ?', [$nome])
            ->orderBy('id')
            ->first();

        if ($byFantasia) {
            return $byFantasia;
        }

        $digits = preg_replace('/\D/', '', $nome) ?? '';

        if (strlen($digits) >= 11) {
            return (clone $base)
                ->whereRaw(
                    "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') = ?",
                    [$digits]
                )
                ->orderBy('id')
                ->first();
        }

        if (in_array($nome, ['CF', '000001'], true) || ctype_digit($nome)) {
            $byCodigo = (clone $base)
                ->where('codigo', $nome)
                ->orderBy('id')
                ->first();

            if ($byCodigo) {
                return $byCodigo;
            }
        }

        return null;
    }

    protected function applyClienteFields(?Person $person, bool $enterAvulso = false): void
    {
        if (! $person) {
            $this->clienteAvulsoMode = false;
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

        $isCf = Person::isCodigoConsumidorFinal($person->codigo);

        if ($isCf && $enterAvulso) {
            $this->clienteAvulsoMode = true;
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

        $this->clienteAvulsoMode = false;
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

    public function clienteCamposEditaveis(): bool
    {
        return $this->clienteAvulsoMode && ! $this->orcamentoReadOnly();
    }

    /**
     * @return array{cliente_nome: ?string, cliente_cpf_cnpj: ?string, cliente_endereco: ?string, cliente_numero: ?string, cliente_bairro: ?string, cliente_cep: ?string, cliente_cidade: ?string, cliente_uf: ?string, cliente_fone: ?string, cliente_whatsapp: ?string}
     */
    protected function clienteSnapshotAttributes(): array
    {
        if (! $this->clienteAvulsoMode) {
            return [
                'cliente_nome' => null,
                'cliente_cpf_cnpj' => null,
                'cliente_endereco' => null,
                'cliente_numero' => null,
                'cliente_bairro' => null,
                'cliente_cep' => null,
                'cliente_cidade' => null,
                'cliente_uf' => null,
                'cliente_fone' => null,
                'cliente_whatsapp' => null,
            ];
        }

        $nome = mb_strtoupper(trim($this->clienteSearch), 'UTF-8');

        return [
            'cliente_nome' => $nome !== '' ? $nome : null,
            'cliente_cpf_cnpj' => trim($this->clienteCpfCnpj) ?: null,
            'cliente_endereco' => mb_strtoupper(trim($this->clienteEndereco), 'UTF-8') ?: null,
            'cliente_numero' => trim($this->clienteNumero) ?: null,
            'cliente_bairro' => mb_strtoupper(trim($this->clienteBairro), 'UTF-8') ?: null,
            'cliente_cep' => trim($this->clienteCep) ?: null,
            'cliente_cidade' => mb_strtoupper(trim($this->clienteCidade), 'UTF-8') ?: null,
            'cliente_uf' => mb_strtoupper(trim($this->clienteUf), 'UTF-8') ?: null,
            'cliente_fone' => trim($this->clienteFone) ?: null,
            'cliente_whatsapp' => trim($this->clienteWhatsapp) ?: null,
        ];
    }

    public function closeClienteLookup(): void
    {
        $this->clienteLookupOpen = false;
    }

    public function updatedClienteCidade(?string $value): void
    {
        if (! $this->clienteAvulsoMode || $this->orcamentoReadOnly()) {
            $this->fecharOrcCidadeSugestoes();

            return;
        }

        $upper = mb_strtoupper((string) $value, 'UTF-8');

        if ($this->clienteCidade !== $upper) {
            $this->clienteCidade = $upper;
        }

        $this->buscarMunicipiosOrcamento($upper);
    }

    public function updatedClienteUf(?string $value): void
    {
        if (! $this->clienteAvulsoMode || $this->orcamentoReadOnly()) {
            return;
        }

        $uf = mb_strtoupper(trim((string) $value), 'UTF-8');
        $this->clienteUf = $uf;

        if (mb_strlen(trim($this->clienteCidade)) >= 2) {
            $this->buscarMunicipiosOrcamento($this->clienteCidade);

            return;
        }

        $this->fecharOrcCidadeSugestoes();
    }

    public function buscarMunicipiosOrcamento(?string $termo = null): void
    {
        $termo = trim((string) ($termo ?? $this->clienteCidade));
        $uf = strtoupper(trim($this->clienteUf));

        if (mb_strlen($termo) < 2) {
            $this->fecharOrcCidadeSugestoes();

            return;
        }

        try {
            $this->orcCidadeSugestoes = app(MunicipioLookupService::class)->search(
                $termo,
                strlen($uf) === 2 ? $uf : null,
                25,
            );
        } catch (RuntimeException $exception) {
            $this->fecharOrcCidadeSugestoes();
            Notification::make()
                ->title('Consulta de cidades')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $this->orcCidadeSugestoesOpen = $this->orcCidadeSugestoes !== [];
        $this->orcCidadeSugestaoIndex = $this->orcCidadeSugestoes !== [] ? 0 : -1;
    }

    public function handleCidadeEnter(): void
    {
        if (! $this->clienteAvulsoMode || $this->orcamentoReadOnly()) {
            return;
        }

        if ($this->orcCidadeSugestoesOpen && $this->orcCidadeSugestoes !== []) {
            $this->confirmarOrcCidadeSugestao();

            return;
        }

        $termo = trim($this->clienteCidade);

        if (mb_strlen($termo) >= 2) {
            $this->buscarMunicipiosOrcamento($termo);

            if ($this->orcCidadeSugestoes !== []) {
                $this->confirmarOrcCidadeSugestao();

                return;
            }
        }

        $this->focusNextClienteAvulsoField('orc-cidade');
    }

    public function confirmarOrcCidadeSugestao(): void
    {
        if ($this->orcCidadeSugestoes === []) {
            $this->focusNextClienteAvulsoField('orc-cidade');

            return;
        }

        $index = $this->orcCidadeSugestaoIndex;

        if ($index < 0 || ! isset($this->orcCidadeSugestoes[$index])) {
            $index = 0;
        }

        $sug = $this->orcCidadeSugestoes[$index];
        $this->selecionarOrcCidade(
            (string) $sug['codigo'],
            (string) $sug['nome'],
            (string) ($sug['uf'] ?? ''),
        );
    }

    public function selecionarOrcCidade(string $codigo, string $nome, ?string $uf = null): void
    {
        $this->clienteCidade = mb_strtoupper(trim($nome), 'UTF-8');

        $uf = strtoupper(trim((string) $uf));

        if (strlen($uf) === 2) {
            $this->clienteUf = $uf;
        }

        $this->fecharOrcCidadeSugestoes();
        $this->dispatch('orc-focus-field', id: 'orc-uf');
    }

    public function moverOrcCidadeSugestao(int $delta): void
    {
        if (! $this->orcCidadeSugestoesOpen || $this->orcCidadeSugestoes === []) {
            return;
        }

        $count = count($this->orcCidadeSugestoes);
        $current = $this->orcCidadeSugestaoIndex < 0 ? 0 : $this->orcCidadeSugestaoIndex;
        $this->orcCidadeSugestaoIndex = ($current + $delta + $count) % $count;
        $this->dispatch('orc-cidade-scroll-selected');
    }

    public function fecharOrcCidadeSugestoes(): void
    {
        $this->orcCidadeSugestoes = [];
        $this->orcCidadeSugestoesOpen = false;
        $this->orcCidadeSugestaoIndex = -1;
    }

    public function buscarCepOrcamento(bool $silentIncompleteCep = false): void
    {
        if (! $this->clienteAvulsoMode || $this->orcamentoReadOnly()) {
            return;
        }

        $cep = (string) $this->clienteCep;

        if (strlen(preg_replace('/\D/', '', $cep) ?? '') !== 8) {
            if (! $silentIncompleteCep) {
                Notification::make()
                    ->title('Informe um CEP completo.')
                    ->warning()
                    ->send();
            }

            return;
        }

        try {
            $fields = app(CepLookupService::class)->lookup($cep);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Consulta de CEP')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $this->clienteCep = $fields['cep'];

        if (filled($fields['endereco'])) {
            $this->clienteEndereco = $fields['endereco'];
        }

        if (filled($fields['bairro'])) {
            $this->clienteBairro = $fields['bairro'];
        }

        $this->clienteCidade = $fields['cidade_nome'];
        $this->clienteUf = $fields['uf'] !== '' ? $fields['uf'] : $this->clienteUf;
        $this->fecharOrcCidadeSugestoes();
        $this->dispatch('erp-orcamento-masks-refresh');

        Notification::make()
            ->title('Endereço preenchido pelo CEP.')
            ->body('Confira cidade e número, se necessário.')
            ->success()
            ->send();
    }

    public function handleCepEnter(): void
    {
        if (! $this->clienteAvulsoMode || $this->orcamentoReadOnly()) {
            return;
        }

        $this->buscarCepOrcamento(silentIncompleteCep: true);
        // Render completo para mostrar endereço/cidade; depois foca a cidade.
        $this->dispatch('orc-focus-field', id: 'orc-cidade');
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

        if (! isset($this->itens[$index])) {
            return;
        }

        $row = $this->itens[$index];
        $this->produtoAtualNome = (string) ($row['descricao'] ?? '');
        $this->produtoAtualFoto = $row['foto'] ?? null;

        if ($this->produtoAtualFoto === null && filled($row['product_id'] ?? null)) {
            $product = Product::query()->find((int) $row['product_id']);
            $this->produtoAtualFoto = $product?->fotoUrl();
            if ($product && isset($this->itens[$index])) {
                $itens = $this->itens;
                $itens[$index]['foto'] = $this->produtoAtualFoto;
                $this->itens = $itens;
            }
        }
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
        $acrescimo = ErpMoney::parseBr($row['acrescimo'] ?? 0);
        $desconto = ErpMoney::parseBr($row['desconto'] ?? 0);

        if ($qtd < 0) {
            $qtd = 0;
        }

        if ($preco < 0) {
            $preco = 0;
        }

        if ($acrescimo < 0) {
            $acrescimo = 0;
        }

        if ($desconto < 0) {
            $desconto = 0;
        }

        $bruto = round($qtd * $preco, 2);
        $total = round(max(0, $bruto + $acrescimo - $desconto), 2);

        $row['quantidade'] = ErpMoney::formatBr($qtd, 3);
        $row['preco_unitario'] = ErpMoney::formatBr($preco);
        $row['acrescimo'] = ErpMoney::formatBr($acrescimo);
        $row['desconto'] = ErpMoney::formatBr($desconto);
        $row['total'] = ErpMoney::formatBr($total);

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

    public function confirmarCodigoProdutoBar(?string $typed = null): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        if (is_string($typed)) {
            $this->itemProdutoSearch = mb_strtoupper(trim($typed), 'UTF-8');
        }

        $term = mb_strtoupper(trim($this->itemProdutoSearch), 'UTF-8');

        if ($term === '') {
            return;
        }

        $navegouNaLista = $this->produtoLookupOpen
            && $this->produtoResults !== []
            && ($this->selectedProdutoIndex ?? 0) > 0;

        if ($navegouNaLista) {
            $this->selectProdutoResult((int) $this->selectedProdutoIndex);

            return;
        }

        $product = $this->findProductByCodigo($term);

        if ($product) {
            $this->stageProductForEntry($product);

            return;
        }

        if ($this->produtoLookupOpen && $this->produtoResults !== []) {
            $this->selectProdutoResult($this->selectedProdutoIndex ?? 0);

            return;
        }

        $product = $this->findProductByTerm($term);

        if ($product) {
            $this->stageProductForEntry($product);

            return;
        }

        $this->submitItemProdutoSearch($term);
    }

    public function updatedItemProdutoSearch(): void
    {
        if ($this->orcamentoReadOnly() || $this->itemPendingProductId !== null) {
            return;
        }

        $upper = mb_strtoupper($this->itemProdutoSearch, 'UTF-8');

        if ($this->itemProdutoSearch !== $upper) {
            $this->itemProdutoSearch = $upper;
        }

        $this->produtoLookupOpen = filled(trim($this->itemProdutoSearch));
        $this->refreshProdutoResults();
    }

    public function handleItemCodigoEnter(): void
    {
        $this->confirmarCodigoProdutoBar();
    }

    public function updatedItemQuantidadeInput(): void
    {
        $this->recalcEntryRowFromPending();
    }

    public function updatedItemPrecoInput(): void
    {
        $this->recalcEntryRowFromPending();
    }

    public function focoPrecoAposQtd(): void
    {
        if ($this->orcamentoReadOnly() || $this->itemPendingProductId === null) {
            return;
        }

        $this->recalcEntryRowFromPending();
        $this->dispatch('orc-focus-preco');
        $this->dispatch('erp-orcamento-focus-item-preco');
    }

    public function handleItemQuantidadeEnter(): void
    {
        $this->focoPrecoAposQtd();
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
            $this->dispatch('orc-focus-qtd');
            $this->dispatch('erp-orcamento-focus-item-quantidade');

            return;
        }

        $preco = ErpMoney::parseBr($this->itemPrecoInput);

        if ($preco <= 0) {
            Notification::make()
                ->title('Informe o preço do item.')
                ->warning()
                ->send();
            $this->dispatch('orc-focus-preco');
            $this->dispatch('erp-orcamento-focus-item-preco');

            return;
        }

        $acrescimo = ErpMoney::parseBr($this->itemPendingAcrescimo);
        $desconto = ErpMoney::parseBr($this->itemPendingDesconto);

        $this->isConfirmingPendingItem = true;

        try {
            $this->itemPendingProductId = null;
            $this->itemPendingPrecoVariavel = false;
            $this->appendProductItem($product, $qtd, $preco, $acrescimo, $desconto);
            $this->clearItemEntryRow();
            $this->dispatch('orc-focus-barcode');
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
        $this->itemPendingDesconto = '0,00';
        $this->itemPendingAcrescimo = '0,00';
        $this->produtoAtualNome = mb_strtoupper($product->descricao, 'UTF-8');
        $this->produtoAtualFoto = $product->fotoUrl();
        $this->recalcEntryRowFromPending();
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->selectedProdutoIndex = null;
        $this->dispatch('orc-focus-qtd');
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

        if (ErpMoney::parseBr($this->itemPrecoInput) <= 0) {
            $this->itemPrecoInput = ErpMoney::formatBr($precoSugerido);
        }

        $preco = ErpMoney::parseBr($this->itemPrecoInput);
        $acr = ErpMoney::parseBr($this->itemPendingAcrescimo);
        $desc = ErpMoney::parseBr($this->itemPendingDesconto);
        $total = round(max(0, ($qtd * $preco) + $acr - $desc), 2);

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
        $this->itemPrecoInput = '0,00';
        $this->itemTotalEntryDisplay = '0,00';
        $this->itemPendingDesconto = '0,00';
        $this->itemPendingAcrescimo = '0,00';
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->selectedProdutoIndex = null;

        if ($this->selectedItemIndex !== null && isset($this->itens[$this->selectedItemIndex])) {
            $this->selectItemRow($this->selectedItemIndex);
        } else {
            $this->produtoAtualFoto = null;
            $this->produtoAtualNome = '';
        }
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
        $prefix = $term . '%';

        $this->produtoResults = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($like, $term): void {
                $query->where('codigo', 'like', $like)
                    ->orWhere('descricao', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('codigo_barras_caixa', 'like', $like);

                if (ctype_digit($term)) {
                    $query->orWhere('codigo', $term)
                        ->orWhereRaw('CAST(codigo AS CHAR) = ?', [$term]);
                }
            })
            ->orderByRaw(
                'CASE WHEN codigo = ? OR CAST(codigo AS CHAR) = ? THEN 0 WHEN CAST(codigo AS CHAR) LIKE ? THEN 1 WHEN codigo_barras = ? OR codigo_barras_caixa = ? THEN 2 WHEN descricao LIKE ? THEN 3 ELSE 4 END',
                [$term, $term, $prefix, $term, $term, $prefix]
            )
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

    public function abrirModalDescontoItem(): void
    {
        if ($this->orcamentoReadOnly() || $this->descontoModalOpen) {
            return;
        }

        if ($this->itemPendingProductId !== null && ErpMoney::parseBr($this->itemPrecoInput) > 0) {
            $this->itemAjusteAlvo = 'form';
        } elseif ($this->selectedItemIndex !== null && isset($this->itens[$this->selectedItemIndex])) {
            $this->itemAjusteAlvo = 'grid';
        } else {
            Notification::make()
                ->title('Informe o produto (ou selecione um item) para desconto/acréscimo.')
                ->warning()
                ->send();

            return;
        }

        $this->itemAjusteTipo = 'desconto';
        $this->itemAjusteModo = 'percentual';
        $this->itemAjusteValor = '0,00';
        $this->descontoModalOpen = true;
    }

    public function fecharModalDescontoItem(): void
    {
        $this->descontoModalOpen = false;
        $this->itemAjusteAlvo = null;
    }

    public function setItemAjusteTipo(string $tipo): void
    {
        $this->itemAjusteTipo = $tipo === 'acrescimo' ? 'acrescimo' : 'desconto';
    }

    public function setItemAjusteModo(string $modo): void
    {
        $this->itemAjusteModo = $modo === 'valor' ? 'valor' : 'percentual';
    }

    /**
     * @return array{descricao: string, base: string, novoPreco: string, total: string, tipo: string, temAjuste: bool}
     */
    public function getItemAjustePreviewProperty(): array
    {
        $ctx = $this->contextoItemAjuste();

        if ($ctx === null) {
            return [
                'descricao' => '',
                'base' => ErpMoney::formatBr(0),
                'novoPreco' => ErpMoney::formatBr(0),
                'total' => ErpMoney::formatBr(0),
                'tipo' => $this->itemAjusteTipo,
                'temAjuste' => false,
            ];
        }

        $calc = $this->calcularItemAjuste($ctx['preco'], $ctx['quantidade']);

        return [
            'descricao' => $ctx['descricao'],
            'base' => ErpMoney::formatBr($calc['base']),
            'novoPreco' => ErpMoney::formatBr($calc['novoPreco']),
            'total' => ErpMoney::formatBr($calc['total']),
            'tipo' => $this->itemAjusteTipo,
            'temAjuste' => abs($calc['deltaUnit']) > 0.0001,
        ];
    }

    public function confirmarItemAjuste(): void
    {
        $ctx = $this->contextoItemAjuste();

        if ($ctx === null) {
            $this->fecharModalDescontoItem();

            return;
        }

        $calc = $this->calcularItemAjuste($ctx['preco'], $ctx['quantidade']);
        $ajusteLinha = round(abs($calc['deltaUnit']) * $ctx['quantidade'], 2);

        if ($this->itemAjusteTipo === 'desconto' && $calc['novoPreco'] < 0) {
            Notification::make()->title('Desconto inválido.')->warning()->send();

            return;
        }

        if ($this->itemAjusteAlvo === 'form') {
            if ($this->itemAjusteTipo === 'desconto') {
                $this->itemPendingDesconto = ErpMoney::formatBr($ajusteLinha);
                $this->itemPendingAcrescimo = '0,00';
            } else {
                $this->itemPendingAcrescimo = ErpMoney::formatBr($ajusteLinha);
                $this->itemPendingDesconto = '0,00';
            }

            $this->recalcEntryRowFromPending();
        } else {
            $index = (int) $this->selectedItemIndex;
            $itens = $this->itens;
            $item = $itens[$index];

            if ($this->itemAjusteTipo === 'desconto') {
                $item['desconto'] = ErpMoney::formatBr($ajusteLinha);
                $item['acrescimo'] = ErpMoney::formatBr(0);
            } else {
                $item['acrescimo'] = ErpMoney::formatBr($ajusteLinha);
                $item['desconto'] = ErpMoney::formatBr(0);
            }

            $itens[$index] = $this->recalcItemRowData($item);
            $this->itens = $itens;
            $this->recalcHeaderFromItens();
        }

        $tipo = $this->itemAjusteTipo;
        $this->fecharModalDescontoItem();
        Notification::make()
            ->title($tipo === 'acrescimo' ? 'Acréscimo aplicado.' : 'Desconto aplicado.')
            ->success()
            ->send();
    }

    /**
     * @return array{descricao: string, preco: float, quantidade: float}|null
     */
    protected function contextoItemAjuste(): ?array
    {
        if ($this->itemAjusteAlvo === 'form' && $this->itemPendingProductId !== null) {
            return [
                'descricao' => $this->produtoAtualNome !== '' ? $this->produtoAtualNome : trim($this->itemProdutoSearch),
                'preco' => ErpMoney::parseBr($this->itemPrecoInput),
                'quantidade' => max(0.0, ErpMoney::parseBr($this->itemQuantidadeInput, 3)),
            ];
        }

        if ($this->itemAjusteAlvo === 'grid' && $this->selectedItemIndex !== null && isset($this->itens[$this->selectedItemIndex])) {
            $item = $this->itens[$this->selectedItemIndex];

            return [
                'descricao' => (string) ($item['descricao'] ?? ''),
                'preco' => ErpMoney::parseBr($item['preco_unitario'] ?? 0),
                'quantidade' => ErpMoney::parseBr($item['quantidade'] ?? 0, 3),
            ];
        }

        return null;
    }

    /**
     * @return array{base: float, deltaUnit: float, novoPreco: float, total: float}
     */
    protected function calcularItemAjuste(float $base, float $quantidade): array
    {
        $valor = ErpMoney::parseBr($this->itemAjusteValor);

        if ($this->itemAjusteModo === 'percentual') {
            $deltaUnit = round($base * ($valor / 100), 2);
        } else {
            $deltaUnit = round($valor, 2);
        }

        $novoPreco = $this->itemAjusteTipo === 'acrescimo'
            ? round($base + $deltaUnit, 2)
            : round($base - $deltaUnit, 2);

        if ($novoPreco < 0) {
            $novoPreco = 0.0;
        }

        $total = round(max(0, $novoPreco * $quantidade), 2);

        return [
            'base' => $base,
            'deltaUnit' => abs($deltaUnit),
            'novoPreco' => $novoPreco,
            'total' => $total,
        ];
    }

    public function itensResumoBrutoDisplay(): string
    {
        $total = 0.0;

        foreach ($this->itens as $row) {
            $qtd = ErpMoney::parseBr($row['quantidade'] ?? 0, 3);
            $preco = ErpMoney::parseBr($row['preco_unitario'] ?? 0);
            $total += round($qtd * $preco, 2);
        }

        return ErpMoney::formatBr($total);
    }

    public function itensResumoAcrescimosDisplay(): string
    {
        $total = 0.0;

        foreach ($this->itens as $row) {
            $total += ErpMoney::parseBr($row['acrescimo'] ?? 0);
        }

        return ErpMoney::formatBr($total);
    }

    public function itensResumoDescontosDisplay(): string
    {
        $total = 0.0;

        foreach ($this->itens as $row) {
            $total += ErpMoney::parseBr($row['desconto'] ?? 0);
        }

        return ErpMoney::formatBr($total);
    }

    public function itensResumoLiquidoDisplay(): string
    {
        $total = 0.0;

        foreach ($this->itens as $row) {
            $total += ErpMoney::parseBr($row['total'] ?? 0);
        }

        return ErpMoney::formatBr($total);
    }

    protected function appendProductItem(
        Product $product,
        float $qtd = 1.0,
        ?float $preco = null,
        float $acrescimo = 0.0,
        float $desconto = 0.0,
    ): void {
        $precoService = app(OrcamentoPrecoService::class);
        $preco ??= $precoService->resolvePreco($product, $qtd);
        $bruto = round($qtd * $preco, 2);
        $total = round(max(0, $bruto + $acrescimo - $desconto), 2);

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
            'acrescimo' => ErpMoney::formatBr($acrescimo),
            'desconto' => ErpMoney::formatBr($desconto),
            'total' => ErpMoney::formatBr($total),
            'preco_variavel' => (bool) $product->preco_variavel,
            'product_grade_id' => null,
            'grade_descricao' => '',
            'foto' => $product->fotoUrl(),
        ];
        array_unshift($itens, $newItem);
        $this->itens = $this->renumberItens($itens);

        $this->selectedItemIndex = 0;
        $this->produtoAtualNome = $newItem['descricao'];
        $this->produtoAtualFoto = $newItem['foto'];
        $this->recalcHeaderFromItens();
    }

    protected function findProductByCodigo(string $codigo): ?Product
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        $base = Product::query()->where('ativo', true);

        $byCodigo = (clone $base)
            ->where('codigo', $codigo)
            ->orderBy('id')
            ->first();

        if ($byCodigo) {
            return $byCodigo;
        }

        if (ctype_digit($codigo)) {
            $byCast = (clone $base)
                ->whereRaw('CAST(codigo AS CHAR) = ?', [$codigo])
                ->orderBy('id')
                ->first();

            if ($byCast) {
                return $byCast;
            }
        }

        return (clone $base)
            ->where(function ($query) use ($codigo): void {
                $query->where('codigo_barras', $codigo)
                    ->orWhere('codigo_barras_caixa', $codigo)
                    ->orWhere('referencia', $codigo);
            })
            ->orderByRaw(
                'CASE WHEN codigo_barras = ? THEN 0 WHEN codigo_barras_caixa = ? THEN 1 ELSE 2 END',
                [$codigo, $codigo]
            )
            ->orderBy('id')
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
        if ($this->clienteId === null || blank(trim($this->clienteSearch))) {
            Notification::make()
                ->title($this->clienteAvulsoMode
                    ? 'Informe o nome do cliente no orçamento!'
                    : 'Informe o Cliente!')
                ->warning()
                ->send();

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
                    ...$this->clienteSnapshotAttributes(),
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
                    $momento = ErpTimezone::toLocal();

                    $orcamento = Orcamento::query()->create([
                        'numero' => Orcamento::nextNumero(),
                        ...$attributes,
                        'hora' => $momento->format('H:i:s'),
                        'plataforma' => Orcamento::PLATAFORMA_ERP,
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
                        'desconto' => ErpMoney::parseBr($row['desconto'] ?? 0),
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
            $codigo = mb_strtoupper(trim($codigo), 'UTF-8');
            $this->itemCodigoInput = $codigo;
            $this->itemProdutoSearch = $codigo;
            $product = $this->findProductByCodigo($codigo);

            if ($product) {
                $this->stageProductForEntry($product);
                $this->closeProductOverlay();

                return;
            }
        }

        $this->closeProductOverlay();
        $this->dispatch('orc-focus-barcode');
        $this->dispatch('erp-orcamento-focus-item-codigo');
    }

    public function applyOverlayPersonSaved(int $clienteId): void
    {
        $person = Person::query()->find($clienteId);

        if ($person) {
            $this->clienteId = $person->id;
            $this->clienteSearch = mb_strtoupper($person->nome_razao, 'UTF-8');
            $this->applyClienteFields($person, enterAvulso: true);
        }

        $this->closePersonOverlay();
        $this->dispatch('erp-orcamento-focus-item-codigo');
    }

    public function handleOrcamentoFormEscape(): void
    {
        if ($this->descontoModalOpen) {
            $this->fecharModalDescontoItem();

            return;
        }

        if ($this->produtoLookupOpen) {
            $this->closeProdutoLookup();

            return;
        }

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
