<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\PdvVendaPagamento;
use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\PdvNotaClienteService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait ManagesPdvVenda
{
    use ManagesPdvFinalizarTotais;
    /** @var array<int, array<string, mixed>> */
    public array $cupomItens = [];

    /** @var array<int, array<string, mixed>> */
    public array $pdvSearchResults = [];

    public ?int $selectedCupomIndex = null;

    public ?int $selectedSearchIndex = null;

    public string $pdvLaunchStep = 'search';

    public string $pdvLaunchQtd = '1';

    public string $pdvLaunchPreco = '0,00';

    public ?int $pdvPendingLaunchProductId = null;

    /** Momento do último item lançado (evita Enter vazio do leitor abrir fechamento). */
    public ?float $pdvItemAddedAt = null;

    /** @var array<string, string> */
    public array $finalizarForm = [
        'cliente' => 'CONSUMIDOR FINAL',
        'dividir_por' => '1',
        'cpf_nota' => '',
        'informacoes_adicionais' => '',
        'desconto_venda' => '0,00',
        'acrescimo_venda' => '0,00',
    ];

    public string $finalizarAba = 'totais';

    public bool $finalizarConfirmSair = false;

    public bool $finalizarConfirmImprimir = false;

    public ?string $finalizarOperacaoPendente = null;

    public ?string $finalizarAlertaTitulo = null;

    public ?string $finalizarAlertaDetalhe = null;

    /** @var array<int, array{forma: string, atalho: string, valor: string, tipo?: string}> */
    public array $finalizarPagamentos = [];

    public int $selectedPagamentoIndex = 0;

    public string $finalizarClienteSearch = 'CONSUMIDOR FINAL';

    public bool $finalizarClienteConsulta = false;

    public ?int $selectedFinalizarClienteIndex = null;

    /** @var array<int, array<string, mixed>> */
    public array $finalizarClienteResults = [];

    public ?int $finalizarClienteId = null;

    public ?string $pdvPreviewFotoUrl = null;

    public ?string $pdvPreviewProductName = null;

    protected string $finalizarClienteSnapshot = 'CONSUMIDOR FINAL';

    /**
     * @return array<int, array{forma: string, atalho: string, valor: string, tipo?: string}>
     */
    protected function defaultFinalizarPagamentos(): array
    {
        $formas = \App\Models\FormaPagamento::query()
            ->where('ativo', true)
            ->where('aparece_venda', true)
            ->orderBy('codigo')
            ->get(['descricao', 'atalho', 'tipo']);

        if ($formas->isEmpty()) {
            return $this->fallbackFinalizarPagamentos();
        }

        $usados = [];
        $pagamentos = [];

        foreach ($formas as $forma) {
            $descricao = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');

            if ($descricao === '') {
                continue;
            }

            $atalho = $this->resolveAtalhoForma((string) $forma->atalho, $descricao, $usados);

            $pagamentos[] = [
                'forma' => $descricao,
                'atalho' => $atalho,
                'tipo' => (string) ($forma->tipo ?? ''),
                'valor' => '0,00',
            ];
        }

        return $pagamentos !== [] ? $pagamentos : $this->fallbackFinalizarPagamentos();
    }

    /**
     * Define um atalho de letra único para a forma de pagamento.
     *
     * @param  array<int, string>  $usados
     */
    protected function resolveAtalhoForma(string $atalho, string $descricao, array &$usados): string
    {
        $atalho = mb_strtoupper(trim($atalho), 'UTF-8');

        if ($atalho !== '' && mb_strlen($atalho, 'UTF-8') === 1 && ! in_array($atalho, $usados, true)) {
            $usados[] = $atalho;

            return $atalho;
        }

        // Tenta cada letra A-Z da descrição; depois o alfabeto completo.
        $candidatos = preg_split('//u', $descricao, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $candidatos = array_merge($candidatos, str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ'));

        foreach ($candidatos as $letra) {
            $letra = mb_strtoupper($letra, 'UTF-8');

            if (preg_match('/^[A-Z]$/', $letra) && ! in_array($letra, $usados, true)) {
                $usados[] = $letra;

                return $letra;
            }
        }

        return '';
    }

    /**
     * Lista mínima usada caso o cadastro de formas de pagamento esteja vazio.
     *
     * @return array<int, array{forma: string, atalho: string, valor: string}>
     */
    protected function fallbackFinalizarPagamentos(): array
    {
        return [
            ['forma' => 'DINHEIRO', 'atalho' => 'A', 'valor' => '0,00'],
            ['forma' => 'PIX', 'atalho' => 'P', 'valor' => '0,00'],
            ['forma' => 'CARTÃO DÉBITO', 'atalho' => 'D', 'valor' => '0,00'],
            ['forma' => 'CARTÃO CRÉDITO', 'atalho' => 'C', 'valor' => '0,00'],
        ];
    }

    public function updatedFinalizarPagamentos(): void
    {
        $this->limparFinalizarAlerta();
    }

    public function fecharFinalizarAlerta(): void
    {
        $this->limparFinalizarAlerta();
        $this->dispatch('erp-pdv-focus-finalizar-pagamento', index: $this->selectedPagamentoIndex ?? 0);
    }

    protected function limparFinalizarAlerta(): void
    {
        $this->finalizarAlertaTitulo = null;
        $this->finalizarAlertaDetalhe = null;
    }

    protected function resetFinalizarForm(): void
    {
        $this->finalizarForm = [
            'cliente' => 'CONSUMIDOR FINAL',
            'dividir_por' => '1',
            'cpf_nota' => '',
            'informacoes_adicionais' => '',
            'desconto_venda' => '0,00',
            'acrescimo_venda' => '0,00',
        ];
        $this->finalizarAba = 'totais';
        $this->finalizarConfirmSair = false;
        $this->cancelFinalizarImprimir();
        $this->limparFinalizarAlerta();
        $this->finalizarPagamentos = $this->defaultFinalizarPagamentos();
        $this->selectedPagamentoIndex = 0;

        if ($this->pdvConfig()->pagamentoPadraoDinheiro()) {
            $total = ErpMoney::formatBr($this->finalizarTotalVendaValor());
            $pagamentos = $this->finalizarPagamentos;
            $pagamentos[0]['valor'] = $total;
            $this->finalizarPagamentos = $pagamentos;
        }

        $clienteNome = session('erp.pdv.import_cliente_nome');
        $clienteId = session('erp.pdv.import_cliente_id');

        if (filled($clienteNome)) {
            $this->finalizarForm['cliente'] = (string) $clienteNome;
            $this->finalizarClienteSearch = (string) $clienteNome;
            $this->finalizarClienteId = filled($clienteId) ? (int) $clienteId : null;
            $this->finalizarClienteSnapshot = (string) $clienteNome;
        } else {
            $this->finalizarClienteSearch = 'CONSUMIDOR FINAL';
            $this->finalizarClienteId = null;
            $this->finalizarClienteSnapshot = 'CONSUMIDOR FINAL';
        }

        $this->finalizarClienteConsulta = false;
        $this->finalizarClienteResults = [];
        $this->selectedFinalizarClienteIndex = null;
    }

    public function getFinalizarClienteEmConsultaProperty(): bool
    {
        return $this->finalizarClienteConsulta;
    }

    public function updatedFinalizarClienteSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->finalizarClienteSearch !== $upper) {
            $this->finalizarClienteSearch = $upper;
        }

        if (! $this->finalizarClienteConsulta) {
            $this->finalizarClienteConsulta = true;
            $this->finalizarClienteSnapshot = $this->finalizarForm['cliente'];
        }

        $this->refreshFinalizarClienteResults();
    }

    public function openFinalizarClienteConsulta(): void
    {
        $this->finalizarClienteSnapshot = $this->finalizarForm['cliente'];
        $this->finalizarClienteSearch = $this->finalizarForm['cliente'];
        $this->finalizarClienteConsulta = true;
        $this->refreshFinalizarClienteResults();
        $this->dispatch('erp-pdv-focus-finalizar-cliente');
    }

    public function refreshFinalizarClienteResults(): void
    {
        $term = trim($this->finalizarClienteSearch);
        $this->finalizarClienteResults = $this->queryClientesForPdv($term);
        $this->selectedFinalizarClienteIndex = $this->finalizarClienteResults === [] ? null : 0;
    }

    public function selectFinalizarClienteResult(int $index): void
    {
        if (! isset($this->finalizarClienteResults[$index])) {
            return;
        }

        $this->selectedFinalizarClienteIndex = $index;
    }

    public function moveFinalizarClienteSelection(int $delta): void
    {
        if (! $this->finalizarClienteConsulta || $this->finalizarClienteResults === []) {
            return;
        }

        $count = count($this->finalizarClienteResults);
        $index = ($this->selectedFinalizarClienteIndex ?? 0) + $delta;
        $index = max(0, min($count - 1, $index));
        $this->selectedFinalizarClienteIndex = $index;
    }

    public function confirmFinalizarCliente(): void
    {
        if (! $this->finalizarClienteConsulta) {
            return;
        }

        $index = $this->selectedFinalizarClienteIndex;

        if ($index !== null && isset($this->finalizarClienteResults[$index])) {
            $row = $this->finalizarClienteResults[$index];
            $this->finalizarForm['cliente'] = $row['nome'];
            $this->finalizarClienteId = $row['person_id'] ?? null;
            $this->syncCpfNotaFromClienteSelecionado($row);
        } else {
            $nome = trim($this->finalizarClienteSearch);

            $this->finalizarForm['cliente'] = $nome !== '' ? $nome : 'CONSUMIDOR FINAL';
            $this->finalizarClienteId = null;
        }

        $this->finalizarClienteSearch = $this->finalizarForm['cliente'];
        $this->finalizarClienteConsulta = false;
        $this->finalizarClienteResults = [];
        $this->selectedFinalizarClienteIndex = null;
        $this->dispatch('erp-pdv-focus-finalizar-pagamento', index: 0);
    }

    public function cancelFinalizarClienteConsulta(): void
    {
        if (! $this->finalizarClienteConsulta) {
            return;
        }

        $this->finalizarForm['cliente'] = $this->finalizarClienteSnapshot;
        $this->finalizarClienteSearch = $this->finalizarClienteSnapshot;
        $this->finalizarClienteConsulta = false;
        $this->finalizarClienteResults = [];
        $this->selectedFinalizarClienteIndex = null;
        $this->dispatch('erp-pdv-focus-finalizar-cliente');
    }

    /**
     * Preenche o CPF na nota apenas para pessoa física. PJ/CNPJ não pode ir para NFC-e (SC).
     *
     * @param  array<string, mixed>  $row
     */
    protected function syncCpfNotaFromClienteSelecionado(array $row): void
    {
        $pessoaTipo = (string) ($row['pessoa_tipo'] ?? Person::PESSOA_FISICA);
        $cpfCnpj = (string) ($row['cpf_cnpj'] ?? '');
        $clienteDigits = preg_replace('/\D/', '', $cpfCnpj) ?? '';
        $cpfNotaDigits = preg_replace('/\D/', '', $this->finalizarForm['cpf_nota'] ?? '') ?? '';
        $clienteEhPj = $pessoaTipo === Person::PESSOA_JURIDICA || strlen($clienteDigits) === 14;

        if ($clienteEhPj) {
            if (strlen($cpfNotaDigits) === 14) {
                $this->finalizarForm['cpf_nota'] = '';
            }

            return;
        }

        $cpfParaNota = PdvNotaClienteService::extrairCpfParaNota($cpfCnpj, $pessoaTipo);

        if ($cpfParaNota !== null && blank($this->finalizarForm['cpf_nota'])) {
            $this->finalizarForm['cpf_nota'] = $cpfParaNota;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function queryClientesForPdv(string $term, int $limit = 100): array
    {
        $results = [];
        $consumidor = 'CONSUMIDOR FINAL';

        if ($term === '' || mb_stripos($consumidor, $term, 0, 'UTF-8') !== false) {
            $results[] = [
                'person_id' => null,
                'nome' => $consumidor,
                'cpf_cnpj' => '',
                'pessoa_tipo' => Person::PESSOA_FISICA,
            ];
        }

        if ($term === '') {
            $people = Person::query()
                ->where('ativo', true)
                ->where('is_cliente', true)
                ->orderBy('nome_razao')
                ->limit($limit)
                ->get();
        } else {
            $like = '%' . $term . '%';

            $people = Person::query()
                ->where('ativo', true)
                ->where('is_cliente', true)
                ->where(function ($query) use ($like): void {
                    $query->where('nome_razao', 'like', $like)
                        ->orWhere('apelido_fantasia', 'like', $like)
                        ->orWhere('cpf_cnpj', 'like', $like);
                })
                ->orderBy('nome_razao')
                ->limit($limit)
                ->get();
        }

        foreach ($people as $person) {
            $results[] = [
                'person_id' => $person->id,
                'nome' => mb_strtoupper($person->nome_razao, 'UTF-8'),
                'cpf_cnpj' => $person->cpf_cnpj ?? '',
                'pessoa_tipo' => $person->pessoa_tipo ?: Person::PESSOA_FISICA,
            ];
        }

        return $results;
    }

    public function selectPagamentoRow(int $index): void
    {
        if (! isset($this->finalizarPagamentos[$index])) {
            return;
        }

        $this->selectedPagamentoIndex = $index;
    }

    public function movePagamentoSelection(int $delta): void
    {
        $count = count($this->finalizarPagamentos);

        if ($count === 0) {
            return;
        }

        $index = $this->selectedPagamentoIndex + $delta;
        $index = max(0, min($count - 1, $index));
        $this->selectedPagamentoIndex = $index;
        $this->dispatch('erp-pdv-focus-finalizar-pagamento', index: $index);
    }

    public function selectPagamentoByAtalho(string $atalho): void
    {
        $atalho = strtoupper(trim($atalho));

        foreach ($this->finalizarPagamentos as $index => $pagamento) {
            if (strtoupper($pagamento['atalho']) === $atalho) {
                $this->aplicarValorRestanteNaFormaPagamento($index);

                return;
            }
        }
    }

    protected function aplicarValorRestanteNaFormaPagamento(int $index): void
    {
        if (! isset($this->finalizarPagamentos[$index])) {
            return;
        }

        $outros = 0.0;

        foreach ($this->finalizarPagamentos as $i => $pagamento) {
            if ($i === $index) {
                continue;
            }

            $outros += ErpMoney::parseBr($pagamento['valor'] ?? '0');
        }

        $restante = max(0, round($this->finalizarTotalVendaValor() - $outros, 2));
        $valorFormatado = ErpMoney::formatBr($restante);
        $pagamentos = $this->finalizarPagamentos;
        $pagamentos[$index]['valor'] = $valorFormatado;
        $this->finalizarPagamentos = $pagamentos;
        $this->selectedPagamentoIndex = $index;
        $this->dispatch('erp-pdv-focus-finalizar-pagamento', index: $index, valor: $valorFormatado);
    }

    public function handlePdvFinalizarValorEnter(): void
    {
        $count = count($this->finalizarPagamentos);

        if ($this->selectedPagamentoIndex < $count - 1) {
            $this->movePagamentoSelection(1);
        }
    }

    public function finalizarTotalPagoValor(): float
    {
        return round(collect($this->finalizarPagamentos)->sum(
            fn (array $pagamento): float => ErpMoney::parseBr($pagamento['valor'] ?? '0')
        ), 2);
    }

    public function finalizarValorRestanteValor(): float
    {
        return max(0, round($this->finalizarTotalVendaValor() - $this->finalizarTotalPagoValor(), 2));
    }

    public function finalizarTrocoValor(): float
    {
        return max(0, round($this->finalizarTotalPagoValor() - $this->finalizarTotalVendaValor(), 2));
    }

    protected function resolveFormaPagamentoPrincipal(): string
    {
        $maior = 0.0;
        $forma = 'DINHEIRO';

        foreach ($this->finalizarPagamentos as $pagamento) {
            $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

            if ($valor > $maior) {
                $maior = $valor;
                $forma = $pagamento['forma'];
            }
        }

        $comValor = collect($this->finalizarPagamentos)
            ->filter(fn (array $pagamento): bool => ErpMoney::parseBr($pagamento['valor'] ?? '0') > 0)
            ->count();

        return $comValor > 1 ? 'MISTO' : $forma;
    }

    protected function validaPagamentosFinalizar(): ?string
    {
        foreach ($this->finalizarPagamentos as $pagamento) {
            $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

            if ($valor <= 0) {
                continue;
            }

            $forma = mb_strtoupper(trim($pagamento['forma'] ?? ''), 'UTF-8');
            $precisaCliente = str_contains($forma, 'CREDI')
                || str_contains($forma, 'CHEQUE')
                || str_contains($forma, 'BOLETO');

            if ($precisaCliente && ! $this->finalizarClienteId) {
                return 'Informe o cliente para pagamento ' . ($pagamento['forma'] ?? 'a prazo') . '.';
            }
        }

        return null;
    }

    public function getFinalizarTotalAPagarProperty(): string
    {
        return ErpMoney::formatBr($this->finalizarTotalVendaValor());
    }

    public function getFinalizarValorPorPessoaProperty(): string
    {
        $divisor = max(1, (int) ErpMoney::parseBr($this->finalizarForm['dividir_por'] ?? '1', 0));

        return ErpMoney::formatBr(round($this->finalizarTotalVendaValor() / $divisor, 2));
    }

    public function getFinalizarValorRestanteProperty(): string
    {
        return ErpMoney::formatBr($this->finalizarValorRestanteValor());
    }

    public function getFinalizarTrocoProperty(): string
    {
        return ErpMoney::formatBr($this->finalizarTrocoValor());
    }

    protected function loadCupomFromSession(): void
    {
        $stored = session('erp.pdv.cupom', []);

        $this->cupomItens = is_array($stored) ? $stored : [];
        $this->selectedCupomIndex = null;
        $this->syncPdvPreviewFotoFromCupomSelection();
    }

    protected function persistCupomToSession(): void
    {
        session(['erp.pdv.cupom' => $this->cupomItens]);
    }

    protected function limparCupom(): void
    {
        $this->cupomItens = [];
        $this->selectedCupomIndex = null;
        $this->clearPdvSearch();
        $this->pdvPreviewFotoUrl = null;
        $this->pdvPreviewProductName = null;
        session()->forget('erp.pdv.cupom');
        $this->clearImportSession();
    }

    public function clearPdvSearch(): void
    {
        $this->pdvSearch = '';
        $this->pdvSearchResults = [];
        $this->selectedSearchIndex = null;
        $this->resetLaunchFlow();
        $this->syncPdvPreviewFotoFromCupomSelection();
    }

    public ?string $produtoNaoEncontradoCodigo = null;

    public int $produtoNaoEncontradoCount = 0;

    protected function sinalizarProdutoNaoEncontrado(?string $codigo): void
    {
        $codigo = trim((string) $codigo);
        $this->produtoNaoEncontradoCodigo = $codigo !== '' ? mb_strtoupper($codigo, 'UTF-8') : '—';
        $this->produtoNaoEncontradoCount++;

        // Aviso central permanente + bip de erro (não bloqueia o scan).
        $this->dispatch('erp-pdv-erro-beep');
        $this->dispatch('erp-pdv-focus-search');
    }

    public function fecharProdutoNaoEncontrado(): void
    {
        $this->produtoNaoEncontradoCodigo = null;
        $this->produtoNaoEncontradoCount = 0;
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function resetLaunchFlow(): void
    {
        $this->pdvLaunchStep = 'search';
        $this->pdvPendingLaunchProductId = null;

        if ($this->pdvCaixaRapido) {
            $this->pdvLaunchQtd = '0';
            $this->pdvLaunchPreco = '0,00';

            return;
        }

        $this->pdvLaunchQtd = '1';
        $this->pdvLaunchPreco = '0,00';
    }

    protected function syncLaunchFieldsFromSelection(): void
    {
        if ($this->pdvCaixaRapido) {
            return;
        }

        if ($this->pdvLaunchStep === 'preco') {
            return;
        }

        if ($this->selectedSearchResult === null) {
            return;
        }

        $productId = $this->selectedSearchResult['product_id'] ?? null;
        $quantidade = ErpMoney::parseBr($this->pdvLaunchQtd, 3);

        if ($quantidade <= 0) {
            $this->pdvLaunchQtd = '1';
            $quantidade = 1;
        }

        $preco = (float) ($this->selectedSearchResult['preco'] ?? 0);

        if ($productId) {
            $product = Product::query()->find($productId);

            if ($product) {
                $preco = $this->pdvPriceService()->resolvePrecoVenda($product, $quantidade);
            }
        }

        $this->pdvLaunchPreco = ErpMoney::formatBr($preco);
    }

    protected function findProductForLaunch(): ?Product
    {
        $parsed = $this->parsePdvSearchTerm($this->pdvSearch);
        $term = $parsed['term'];

        if ($term === '') {
            return null;
        }

        $product = $this->findExactProductForPdv($term);

        if (! $product && $this->selectedSearchIndex !== null) {
            $productId = $this->pdvSearchResults[$this->selectedSearchIndex]['product_id'] ?? null;
            $product = filled($productId) ? Product::query()->find($productId) : null;
        }

        if (! $product && $this->pdvPendingLaunchProductId) {
            $product = Product::query()->find($this->pdvPendingLaunchProductId);
        }

        return $product;
    }

    protected function applyLaunchFieldsFromSearchTerm(): void
    {
        if ($this->pdvLaunchStep === 'preco') {
            return;
        }

        $parsed = $this->parsePdvSearchTerm($this->pdvSearch);
        $term = $parsed['term'];

        if ($term === '') {
            return;
        }

        if ($parsed['quantidade'] !== null) {
            $this->pdvLaunchQtd = ErpMoney::formatBr($parsed['quantidade'], 3);
        }

        $product = $this->findProductForLaunch();

        if (! $product || ! $this->isNumericPdvTerm($term) || ! $this->pdvConfig()->buscaBalancaBarras()) {
            return;
        }

        $scale = $this->pdvScaleService()->parse($product, $term);

        if ($scale !== null) {
            $this->pdvLaunchQtd = ErpMoney::formatBr($scale['quantidade'], 3);
            $this->pdvLaunchPreco = ErpMoney::formatBr($scale['preco']);

            return;
        }

        $qtd = ErpMoney::parseBr($this->pdvLaunchQtd, 3);
        $preco = $this->pdvPriceService()->resolvePrecoVenda($product, max(1, $qtd));
        $this->pdvLaunchPreco = ErpMoney::formatBr($preco);
    }

    protected function resolveProductForLaunch(): ?Product
    {
        $this->applyLaunchFieldsFromSearchTerm();

        return $this->findProductForLaunch();
    }

    protected function ensureSelectedSearchIndexForProduct(Product $product): void
    {
        if ($this->selectedSearchIndex !== null) {
            $selectedId = $this->pdvSearchResults[$this->selectedSearchIndex]['product_id'] ?? null;

            if ((int) $selectedId === (int) $product->id) {
                return;
            }
        }

        foreach ($this->pdvSearchResults as $index => $row) {
            if ((int) ($row['product_id'] ?? 0) === (int) $product->id) {
                $this->selectedSearchIndex = $index;

                return;
            }
        }
    }

    protected function beginPdvLaunchPriceConfirmation(Product $product): void
    {
        $this->pdvPendingLaunchProductId = $product->id;
        $quantidade = $this->parsePdvLaunchQtd();

        if ($quantidade <= 0) {
            $quantidade = 1.0;
            $this->pdvLaunchQtd = ErpMoney::formatBr(1, 3);
        }

        $preco = ErpMoney::parseBr($this->pdvLaunchPreco, 2);

        if ($preco <= 0) {
            $preco = $this->pdvPriceService()->resolvePrecoVenda($product, $quantidade);
            $this->pdvLaunchPreco = ErpMoney::formatBr($preco);
        }

        $this->pdvLaunchStep = 'preco';
        $this->dispatch('erp-pdv-focus-launch', field: 'preco');
    }

    public function getPdvShowLaunchFieldsProperty(): bool
    {
        if (in_array($this->pdvLaunchStep, ['qtd', 'preco'], true)) {
            return true;
        }

        return $this->pdvEmConsulta && $this->pdvSearchResults !== [];
    }

    public function refreshPdvSearchResults(): void
    {
        $parsed = $this->parsePdvSearchTerm($this->pdvSearch);
        $term = $parsed['term'];

        if ($term === '') {
            $this->pdvSearchResults = [];
            $this->selectedSearchIndex = null;
            $this->syncPdvPreviewFotoFromCupomSelection();

            return;
        }

        if ($this->isNumericPdvTerm($term)) {
            $this->pdvSearchResults = [];
            $this->selectedSearchIndex = null;

            return;
        }

        $this->pdvSearchResults = $this->queryProductsForPdv($term);
        $this->selectedSearchIndex = $this->pdvSearchResults === [] ? null : 0;
        $this->pdvLaunchStep = 'search';

        if ($parsed['quantidade'] !== null) {
            $this->pdvLaunchQtd = ErpMoney::formatBr($parsed['quantidade'], 3);
        }

        $this->syncLaunchFieldsFromSelection();
        $this->syncPdvPreviewFotoFromSearchSelection();
    }

    public function getPdvEmConsultaProperty(): bool
    {
        return trim($this->pdvSearch) !== '';
    }

    public function selectSearchResult(int $index): void
    {
        if (! isset($this->pdvSearchResults[$index])) {
            return;
        }

        $this->selectedSearchIndex = $index;
        $this->pdvLaunchStep = 'search';
        $this->syncLaunchFieldsFromSelection();
        $this->syncPdvPreviewFotoFromSearchSelection();
    }

    public function moveSearchSelection(int $delta): void
    {
        if (! $this->pdvEmConsulta || $this->pdvSearchResults === []) {
            return;
        }

        $count = count($this->pdvSearchResults);
        $index = ($this->selectedSearchIndex ?? 0) + $delta;
        $index = max(0, min($count - 1, $index));
        $this->selectedSearchIndex = $index;
        $this->pdvLaunchStep = 'search';
        $this->syncLaunchFieldsFromSelection();
        $this->syncPdvPreviewFotoFromSearchSelection();
    }

    public function cupomTemItens(): bool
    {
        return $this->cupomItens !== [];
    }

    public function selectCupomItem(int $index): void
    {
        if (! isset($this->cupomItens[$index])) {
            return;
        }

        $this->selectedCupomIndex = $index;
        $this->syncPdvPreviewFotoFromCupomSelection();
    }

    public function moveCupomSelection(int $delta): void
    {
        if ($this->pdvEmConsulta || $this->cupomItens === [] || $this->selectedCupomIndex === null) {
            return;
        }

        $count = count($this->cupomItens);
        $index = $this->selectedCupomIndex + $delta;
        $index = max(0, min($count - 1, $index));
        $this->selectedCupomIndex = $index;
        $this->syncPdvPreviewFotoFromCupomSelection();
    }

    public function deletarItemCupom(): void
    {
        if (! $this->caixaAberto) {
            Notification::make()
                ->title('Caixa fechado.')
                ->warning()
                ->send();

            return;
        }

        if ($this->pdvEmConsulta) {
            return;
        }

        if ($this->selectedCupomIndex === null || ! isset($this->cupomItens[$this->selectedCupomIndex])) {
            Notification::make()
                ->title('Selecione um item do cupom.')
                ->info()
                ->send();

            return;
        }

        if (! $this->requirePdvAutorizacao('excluir_item')) {
            return;
        }

        $this->openPdvModal('excluir_item');
    }

    public function confirmExcluirItemCupom(): void
    {
        if ($this->selectedCupomIndex === null || ! isset($this->cupomItens[$this->selectedCupomIndex])) {
            $this->closePdvModal();
            $this->dispatch('erp-pdv-focus-search');

            return;
        }

        $index = $this->selectedCupomIndex;
        unset($this->cupomItens[$index]);
        $this->cupomItens = array_values($this->cupomItens);
        $this->selectedCupomIndex = null;

        $this->persistCupomToSession();
        $this->closePdvModal();
        $this->clearPdvAutorizacao();
        $this->syncPdvPreviewFotoFromCupomSelection();
        $this->dispatch('erp-pdv-item-added');
    }

    public function cancelExcluirItemCupom(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    public function handlePdvSearchEnter(): void
    {
        if (! $this->caixaAberto) {
            Notification::make()
                ->title('Caixa fechado.')
                ->body('Abra o caixa com F2 antes de vender.')
                ->warning()
                ->send();

            return;
        }

        if (trim($this->pdvSearch) === '') {
            if ($this->shouldIgnoreEmptySearchEnterAfterItemAdded()) {
                $this->pdvItemAddedAt = null;

                return;
            }

            $this->pdvItemAddedAt = null;
            $this->openFinalizarVenda();

            return;
        }

        $product = $this->resolveProductForLaunch();

        if (! $product) {
            $termo = trim($this->pdvSearch);
            $this->clearPdvSearch();
            $this->sinalizarProdutoNaoEncontrado($termo);

            return;
        }

        if ($product->is_composicao) {
            $stockService = new \App\Support\Erp\Pdv\PdvStockService();
            $quantidadeEstoque = $this->pdvCaixaRapido
                ? $this->resolveCaixaRapidoLaunchQuantidade()
                : $this->parsePdvLaunchQtd();
            $msg = $stockService->validaEstoqueComposicao($product, $quantidadeEstoque);

            if ($msg) {
                $this->notifyPdvError($msg);
                $this->clearPdvSearch();

                return;
            }
        }

        $this->proceedAfterProductSelected($product);
    }

    protected function proceedAfterProductSelected(Product $product): void
    {
        $this->ensureSelectedSearchIndexForProduct($product);
        $this->syncLaunchFieldsFromSelection();
        $this->syncPdvPreviewFotoForProduct($product);

        if ($product->is_grade) {
            if ($this->pdvCaixaRapido) {
                $quantidade = $this->resolveCaixaRapidoLaunchQuantidade();
                $preco = $this->resolveCaixaRapidoLaunchPreco($product, $quantidade);
            } else {
                $quantidade = $this->parsePdvLaunchQtd();
                $preco = ErpMoney::parseBr($this->pdvLaunchPreco, 2);
            }

            $this->openPdvGradeModal($product, $quantidade, $preco);

            return;
        }

        if ($product->preco_variavel) {
            $this->beginPdvLaunchPriceConfirmation($product);

            return;
        }

        if ($this->pdvCaixaRapido) {
            $quantidade = $this->resolveCaixaRapidoLaunchQuantidade();
            $preco = $this->resolveCaixaRapidoLaunchPreco($product, $quantidade);

            if ($product->usa_imei) {
                $this->openPdvSerialModal($product, 1, $preco);

                return;
            }

            $this->confirmAddProduct($product, $quantidade, $preco);

            return;
        }

        // Registro direto ao dar Enter no código: usa a quantidade informada
        // (ex.: "3*codigo", senão 1) e o preço de venda padrão do produto.
        $quantidade = $this->parsePdvLaunchQtd();
        $quantidade = $quantidade > 0 ? $quantidade : 1.0;

        $preco = ErpMoney::parseBr($this->pdvLaunchPreco, 2);
        $preco = $preco > 0 ? $preco : $this->pdvPriceService()->resolvePrecoVenda($product, $quantidade);

        if ($product->usa_imei) {
            $this->openPdvSerialModal($product, 1, $preco);

            return;
        }

        $this->confirmAddProduct($product, $quantidade, $preco);
    }

    public function handlePdvLaunchQtdEnter(): void
    {
        if ($this->parsePdvLaunchQtd() <= 0) {
            Notification::make()
                ->title('Quantidade inválida.')
                ->warning()
                ->send();

            return;
        }

        $product = $this->resolveProductForLaunch();

        if ($product) {
            $quantidade = $this->parsePdvLaunchQtd();
            $preco = ErpMoney::parseBr($this->pdvLaunchPreco, 2);

            if ($preco <= 0) {
                $this->pdvLaunchPreco = ErpMoney::formatBr(
                    $this->pdvPriceService()->resolvePrecoVenda($product, $quantidade),
                );
            }
        }

        $this->pdvLaunchStep = 'preco';
        $this->dispatch('erp-pdv-focus-launch', field: 'preco');
    }

    public function handlePdvLaunchPrecoEnter(?string $precoInformado = null): void
    {
        if (! $this->caixaAberto) {
            return;
        }

        if (filled($precoInformado)) {
            $this->pdvLaunchPreco = trim($precoInformado);
        }

        $quantidade = $this->parsePdvLaunchQtd();
        $preco = ErpMoney::parseBr($this->pdvLaunchPreco, 2);

        if ($quantidade <= 0) {
            Notification::make()
                ->title('Quantidade inválida.')
                ->warning()
                ->send();

            return;
        }

        if ($preco <= 0) {
            Notification::make()
                ->title('Preço inválido.')
                ->warning()
                ->send();

            return;
        }

        $product = $this->resolveProductForLaunch();

        if (! $product) {
            $termo = trim($this->pdvSearch);
            $this->clearPdvSearch();
            $this->sinalizarProdutoNaoEncontrado($termo);

            return;
        }

        if ($product->is_grade) {
            $this->openPdvGradeModal($product, $quantidade, $preco);

            return;
        }

        if ($product->usa_imei) {
            $this->openPdvSerialModal($product, 1, $preco);

            return;
        }

        $this->confirmAddProduct($product, $quantidade, $preco);
    }

    public function beginLaunchFromSearch(int $index): void
    {
        if (! $this->caixaAberto || ! isset($this->pdvSearchResults[$index])) {
            return;
        }

        $this->selectedSearchIndex = $index;
        $productId = $this->pdvSearchResults[$index]['product_id'] ?? null;
        $this->pdvPendingLaunchProductId = filled($productId) ? (int) $productId : null;
        $this->syncLaunchFieldsFromSelection();
        $this->pdvLaunchStep = 'qtd';
        $this->dispatch('erp-pdv-focus-launch', field: 'qtd');
    }

    public function addProductFromSearch(): void
    {
        $this->handlePdvSearchEnter();
    }

    public function addSearchResultToCupom(int $index): void
    {
        $this->beginLaunchFromSearch($index);
    }

    protected function confirmAddProduct(
        Product $product,
        ?float $quantidade = null,
        ?float $preco = null,
        ?int $productGradeId = null,
        ?int $productSerialId = null,
        ?string $descricaoOverride = null,
    ): void {
        if (! $product->ativo) {
            Notification::make()
                ->title('Produto inativo.')
                ->warning()
                ->send();

            return;
        }

        $quantidade = $quantidade ?? $this->parsePdvLaunchQtd();
        $preco = $preco ?? ErpMoney::parseBr($this->pdvLaunchPreco, 2);

        $validator = $this->pdvItemValidator();

        if ($msg = $validator->validaQuantidade($quantidade)) {
            $this->notifyPdvError($msg);

            return;
        }

        if ($msg = $validator->validaPreco($product, $preco, $quantidade)) {
            $this->notifyPdvError($msg);

            return;
        }

        if ($msg = $validator->validaEstoque($product, $quantidade, $productGradeId)) {
            $this->notifyPdvError($msg);
            $this->clearPdvSearch();

            return;
        }

        $this->addProductToCupom(
            $product,
            $quantidade,
            $preco,
            $productGradeId,
            $productSerialId,
            $descricaoOverride,
        );
        $this->recheckAtacadoPrices((int) $product->id);
        $this->clearPdvSearch();
        // Mantém a foto do último produto registrado visível (sem depender de seleção).
        $this->syncPdvPreviewFotoForProduct($product);
        $this->pdvItemAddedAt = microtime(true);
        $this->persistCupomToSession();
        $this->dispatch('erp-pdv-item-added');
        $this->dispatch('erp-pdv-beep');
        $this->dispatch('erp-pdv-produto-confirmado', nome: mb_strtoupper(
            (string) ($descricaoOverride ?? $product->descricao),
            'UTF-8',
        ));
    }

    protected function shouldIgnoreEmptySearchEnterAfterItemAdded(): bool
    {
        if ($this->pdvItemAddedAt === null) {
            return false;
        }

        return (microtime(true) - $this->pdvItemAddedAt) < 0.75;
    }

    protected function recheckAtacadoPrices(int $productId): void
    {
        $product = Product::query()->find($productId);

        if (! $product || (float) ($product->qtd_atacado ?? 0) <= 0) {
            return;
        }

        $totalQty = round(collect($this->cupomItens)
            ->where('product_id', $productId)
            ->sum(fn (array $item): float => (float) ($item['quantidade'] ?? 0)), 3);

        if ($totalQty < (float) $product->qtd_atacado) {
            return;
        }

        $preco = $this->pdvPriceService()->resolvePrecoVenda($product, $totalQty);

        foreach ($this->cupomItens as $index => $item) {
            if ((int) ($item['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $qtd = (float) ($item['quantidade'] ?? 0);
            $this->cupomItens[$index]['preco'] = $preco;
            $this->cupomItens[$index]['total'] = round($qtd * $preco, 2);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function queryProductsForPdv(string $term, int $limit = 100): array
    {
        $config = $this->pdvConfig();
        $like = $config->pesquisaPartesDescricao() ? '%' . $term . '%' : $term . '%';

        $query = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($like): void {
                $query->where('codigo', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('codigo_barras_caixa', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('descricao', 'like', $like);
            });

        if (! $config->exibirEstoqueNegativo()) {
            $query->where(function ($q): void {
                $q->where('is_servico', true)
                    ->orWhere('estoque', '>', 0);
            });
        }

        $priceService = $this->pdvPriceService();

        return $query
            ->orderBy('descricao')
            ->limit($limit)
            ->get()
            ->map(function (Product $product) use ($priceService): array {
                $preco = $priceService->resolvePrecoVenda($product, 1);

                return [
                    'product_id' => $product->id,
                    'codigo' => filled($product->codigo_barras) ? $product->codigo_barras : $product->codigo,
                    'descricao' => mb_strtoupper($product->descricao, 'UTF-8'),
                    'preco' => $preco,
                    'estoque' => (float) $product->estoque,
                    'unidade' => $product->unidade ?: 'UN',
                    'localizacao' => filled($product->localizacao)
                        ? mb_strtoupper((string) $product->localizacao, 'UTF-8')
                        : '',
                    'preco_variavel' => (bool) $product->preco_variavel,
                ];
            })
            ->values()
            ->all();
    }

    protected function findExactProductForPdv(string $term): ?Product
    {
        $product = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term): void {
                $query->where('codigo', $term)
                    ->orWhere('codigo_barras', $term)
                    ->orWhere('codigo_barras_caixa', $term)
                    ->orWhere('referencia', $term);
            })
            ->first();

        if ($product) {
            return $product;
        }

        if (! $this->isNumericPdvTerm($term)) {
            return null;
        }

        if (! $this->pdvConfig()->buscaBalancaBarras()) {
            return null;
        }

        $prefix = substr($term, 0, min(7, strlen($term)));

        return Product::query()
            ->where('ativo', true)
            ->whereNotNull('prefixo_balanca')
            ->where('prefixo_balanca', '!=', '')
            ->where('prefixo_balanca', $prefix)
            ->first();
    }

    protected function parsePdvLaunchQtd(): float
    {
        return max(0, ErpMoney::parseBr($this->pdvLaunchQtd, 3));
    }

    protected function resolveCaixaRapidoLaunchQuantidade(): float
    {
        $quantidade = $this->parsePdvLaunchQtd();

        return $quantidade > 0 ? $quantidade : 1.0;
    }

    protected function resolveCaixaRapidoLaunchPreco(Product $product, float $quantidade): float
    {
        $preco = ErpMoney::parseBr($this->pdvLaunchPreco, 2);

        if ($preco > 0) {
            return $preco;
        }

        return $this->pdvPriceService()->resolvePrecoVenda($product, $quantidade);
    }

    protected function addProductToCupom(
        Product $product,
        ?float $quantidade = null,
        ?float $preco = null,
        ?int $productGradeId = null,
        ?int $productSerialId = null,
        ?string $descricaoOverride = null,
    ): void {
        $quantidade = max(0.001, $quantidade ?? 1);
        $preco = $preco ?? $this->pdvPriceService()->resolvePrecoVenda($product, $quantidade);

        if (! $productSerialId) {
            foreach ($this->cupomItens as $index => $item) {
                if ((int) ($item['product_id'] ?? 0) === (int) $product->id
                    && (int) ($item['product_grade_id'] ?? 0) === (int) ($productGradeId ?? 0)
                    && empty($item['product_serial_id'])
                    && round((float) ($item['preco'] ?? 0), 2) === round($preco, 2)) {
                    $novaQuantidade = round((float) ($item['quantidade'] ?? 0) + $quantidade, 3);
                    $this->cupomItens[$index]['quantidade'] = $novaQuantidade;
                    $this->cupomItens[$index]['total'] = round($novaQuantidade * $preco, 2);

                    return;
                }
            }
        }

        $descricao = $descricaoOverride
            ? mb_strtoupper($descricaoOverride, 'UTF-8')
            : mb_strtoupper($product->descricao, 'UTF-8');

        $this->cupomItens[] = [
            'product_id' => $product->id,
            'product_grade_id' => $productGradeId,
            'product_serial_id' => $productSerialId,
            'codigo' => $product->codigo,
            'codigo_barras' => $product->codigo_barras ?? '',
            'descricao' => $descricao,
            'unidade' => $product->unidade ?: 'UN',
            'quantidade' => $quantidade,
            'preco' => $preco,
            'total' => round($quantidade * $preco, 2),
        ];
    }

    public function getPdvRateioPessoaProperty(): bool
    {
        return $this->pdvConfig()->rateioPessoaPdv();
    }

    public function cancelarCupom(): void
    {
        if (! $this->cupomTemItens()) {
            Notification::make()
                ->title('Nenhum item no cupom.')
                ->info()
                ->send();

            return;
        }

        $this->limparCupom();

        Notification::make()
            ->title('Venda cancelada.')
            ->success()
            ->send();

        $this->dispatch('erp-pdv-caixa-opened');
    }

    public function openFinalizarVenda(): void
    {
        if (! $this->caixaAberto) {
            Notification::make()
                ->title('Caixa fechado.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->cupomTemItens()) {
            Notification::make()
                ->title('Informe os produtos da venda.')
                ->warning()
                ->send();

            return;
        }

        $this->resetFinalizarForm();
        $this->openPdvModal('finalizar');
        $this->dispatch('erp-pdv-focus-finalizar');
    }

    public function selectFinalizarAba(string $aba): void
    {
        if (! in_array($aba, ['totais', 'informacoes'], true)) {
            return;
        }

        $this->finalizarAba = $aba;

        if ($aba === 'informacoes') {
            $this->dispatch('erp-pdv-focus-finalizar-informacoes');
        }
    }

    public function requestCloseFinalizar(): void
    {
        if ($this->finalizarConfirmImprimir) {
            $this->cancelFinalizarImprimir();

            return;
        }

        if ($this->finalizarClienteConsulta) {
            $this->cancelFinalizarClienteConsulta();

            return;
        }

        $this->finalizarConfirmSair = true;
        $this->dispatch('erp-pdv-finalizar-sair-opened');
    }

    public function confirmCloseFinalizar(): void
    {
        $this->finalizarConfirmSair = false;
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    public function cancelCloseFinalizar(): void
    {
        $this->finalizarConfirmSair = false;
    }

    public function confirmFinalizarVenda(): void
    {
        $unica = $this->pdvFinalizarOperacaoUnica;

        if ($unica !== null) {
            $this->confirmFinalizarComOperacao($unica);

            return;
        }

        $this->notifyPdvError('Selecione o tipo de fechamento (NFC-e ou Pedido).');
    }

    public function confirmFinalizarComOperacao(string $operacao): void
    {
        if (! PdvFinalizarOperacao::operacaoPermitida($this->pdvConfig()->terminal(), $operacao)) {
            $this->notifyPdvError('Operação não permitida para este terminal.');

            return;
        }

        if (! $this->validarPreCondicoesFinalizarVenda()) {
            return;
        }

        if (PdvFinalizarOperacao::solicitaConfirmacaoImpressao($operacao)) {
            $this->finalizarOperacaoPendente = $operacao;
            $this->finalizarConfirmImprimir = true;
            $this->dispatch('erp-pdv-finalizar-imprimir-opened');

            return;
        }

        $this->executarFinalizarComOperacao($operacao, true);
    }

    public function cancelFinalizarImprimir(): void
    {
        $this->finalizarConfirmImprimir = false;
        $this->finalizarOperacaoPendente = null;
    }

    public function confirmFinalizarImprimir(bool $imprimir): void
    {
        $operacao = $this->finalizarOperacaoPendente;

        $this->cancelFinalizarImprimir();

        if ($operacao === null) {
            return;
        }

        $this->executarFinalizarComOperacao($operacao, $imprimir);
    }

    protected function executarFinalizarComOperacao(string $operacao, bool $imprimir): void
    {
        if (PdvFinalizarOperacao::isFiscal($operacao)) {
            $this->executarFinalizarVendaNfceSimulada($operacao, $imprimir);

            return;
        }

        $this->executarFinalizarVendaPedido($imprimir);
    }

    protected function validarPreCondicoesFinalizarVenda(): bool
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId || ! $this->cupomTemItens()) {
            return false;
        }

        if (! $this->validateFinalizarTotais()) {
            return false;
        }

        $total = $this->finalizarTotalVendaValor();

        if ($msg = $this->validaLimiteClienteFinalizar($total)) {
            $this->notifyPdvError($msg);

            return false;
        }

        $cpfNota = trim($this->finalizarForm['cpf_nota'] ?? '');

        if ($msg = (new PdvNotaClienteService($this->pdvConfig()))->validaCpfNota($this->finalizarClienteId, $cpfNota)) {
            $this->notifyPdvError($msg);

            return false;
        }

        if ($msg = $this->validaPagamentosFinalizar()) {
            $this->notifyPdvError($msg);

            return false;
        }

        $restante = $this->finalizarValorRestanteValor();

        if ($restante > 0) {
            if ($this->finalizarTotalPagoValor() <= 0) {
                $this->notifyPdvError('Informe o meio de pagamento.');
            } else {
                $this->notifyPdvError(
                    'Pagamento incompleto.',
                    'Valor restante: R$ ' . ErpMoney::formatBr($restante),
                );
            }

            return false;
        }

        return true;
    }

    protected function executarFinalizarVendaPedido(bool $imprimir = true): void
    {
        $imprimirCallback = null;

        if ($imprimir) {
            $imprimirCallback = function (int $vendaId): void {
                $this->imprimirCupomPosVenda(
                    $vendaId,
                    $this->pdvConfig()->pedidoDuasVias() ? 2 : 1,
                );
            };
        }

        $this->concluirVendaFinalizada(
            fiscal: false,
            nfceOperacao: null,
            tituloNotificacao: 'Venda finalizada.',
            imprimir: $imprimirCallback,
        );
    }

    protected function executarFinalizarVendaNfceSimulada(string $operacao, bool $imprimir = true): void
    {
        $imprimirCallback = null;

        if ($imprimir) {
            $imprimirCallback = function (int $vendaId): void {
                $this->imprimirNfceCupomPosVenda($vendaId, 1);
            };
        }

        $this->concluirVendaFinalizada(
            fiscal: true,
            nfceOperacao: $operacao,
            tituloNotificacao: 'NFC-e simulada finalizada.',
            imprimir: $imprimirCallback,
        );
    }

    /**
     * @param  (callable(int): void)|null  $imprimir
     */
    protected function concluirVendaFinalizada(
        bool $fiscal,
        ?string $nfceOperacao,
        string $tituloNotificacao,
        ?callable $imprimir,
    ): void {
        $formaPagamento = $this->resolveFormaPagamentoPrincipal();
        $observacoes = trim($this->finalizarForm['informacoes_adicionais'] ?? '');
        $cpfNota = trim($this->finalizarForm['cpf_nota'] ?? '');
        $subtotal = $this->finalizarSubtotalValor();
        $desconto = $this->finalizarDescontoValor();
        $acrescimo = $this->finalizarAcrescimoValor();
        $total = $this->finalizarTotalVendaValor();
        $troco = $this->finalizarTrocoValor();
        $dinheiroRow = collect($this->finalizarPagamentos)->firstWhere('forma', 'DINHEIRO');
        $dinheiro = ErpMoney::parseBr(is_array($dinheiroRow) ? ($dinheiroRow['valor'] ?? '0') : '0');
        $vendaId = null;

        DB::transaction(function () use ($fiscal, $nfceOperacao, $formaPagamento, $total, $subtotal, $desconto, $acrescimo, $observacoes, $cpfNota, $troco, $dinheiro, &$vendaId): void {
            $numero = PdvVenda::nextNumero($this->caixaSessaoId);
            $docSaida = 'PDV-' . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
            $stockService = new \App\Support\Erp\Pdv\PdvStockService();
            $orcamentoId = session('erp.pdv.orcamento_id');

            $venda = PdvVenda::query()->create([
                'pdv_caixa_sessao_id' => $this->caixaSessaoId,
                'user_id' => Auth::id(),
                'orcamento_id' => filled($orcamentoId) ? (int) $orcamentoId : null,
                'person_id' => $this->finalizarClienteId,
                'cpf_nota' => $cpfNota !== '' ? $cpfNota : null,
                'vendedor_id' => $this->vendedorId,
                'vendedor_nome' => $this->vendedor,
                'numero' => $numero,
                'subtotal' => $subtotal,
                'desconto' => $desconto,
                'acrescimo' => $acrescimo,
                'total' => $total,
                'forma_pagamento' => $formaPagamento,
                'fiscal' => $fiscal,
                'nfce_operacao' => $nfceOperacao,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'troco' => $troco,
                'dinheiro' => $dinheiro,
                'situacao' => 'F',
                'fechado_em' => now(),
            ]);

            foreach ($this->cupomItens as $item) {
                PdvVendaItem::query()->create([
                    'pdv_venda_id' => $venda->id,
                    'product_id' => $item['product_id'] ?? null,
                    'product_grade_id' => $item['product_grade_id'] ?? null,
                    'product_serial_id' => $item['product_serial_id'] ?? null,
                    'codigo' => $item['codigo'] ?? null,
                    'descricao' => $item['descricao'] ?? '',
                    'unidade' => $item['unidade'] ?? 'UN',
                    'quantidade' => $item['quantidade'] ?? 1,
                    'preco_unitario' => $item['preco'] ?? 0,
                    'total' => $item['total'] ?? 0,
                ]);

                if (filled($item['product_id'] ?? null)) {
                    $product = Product::query()->find($item['product_id']);

                    if ($product) {
                        $stockService->baixaItemVenda(
                            $product,
                            (float) ($item['quantidade'] ?? 1),
                            isset($item['product_grade_id']) ? (int) $item['product_grade_id'] : null,
                            isset($item['product_serial_id']) ? (int) $item['product_serial_id'] : null,
                            $docSaida,
                        );
                    }
                }
            }

            foreach ($this->finalizarPagamentos as $pagamento) {
                $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

                if ($valor <= 0) {
                    continue;
                }

                PdvVendaPagamento::query()->create([
                    'pdv_venda_id' => $venda->id,
                    'forma' => $pagamento['forma'],
                    'valor' => $valor,
                ]);
            }

            (new \App\Support\Erp\Pdv\PdvVendaFinanceiroService())->gerarContasReceber(
                $venda,
                $this->finalizarClienteId,
                $this->finalizarPagamentos,
            );

            (new \App\Support\Erp\Pdv\PdvCaixaMovimentoService($this->pdvConfig()))->registrarEntradasVenda(
                $this->caixaSessaoId,
                $venda,
                $this->finalizarPagamentos,
                $troco,
            );

            $venda->load('itens');
            $retaguardaVenda = (new \App\Support\Erp\Pdv\PdvVendaRetaguardaMirrorService())->espelhar($venda);

            if (filled($orcamentoId)) {
                (new \App\Support\VendasInternas\VendasInternasPdvHookService())
                    ->onVendaPdvFinalizada((int) $orcamentoId, (int) $retaguardaVenda->id);
            }

            if ($fiscal && filled($nfceOperacao)) {
                (new \App\Support\Erp\Pdv\PdvVendaNfceService())->registrarSimulada(
                    $venda,
                    $this->pdvConfig()->empresa(),
                    $nfceOperacao,
                );
            }

            $vendaId = $venda->id;
        });

        $totalFormatado = ErpMoney::formatBr($total);

        $this->limparCupom();
        $this->closePdvModal();

        $body = 'Total: R$ ' . $totalFormatado . ' — ' . $formaPagamento;
        if ($fiscal) {
            $body .= ' (cupom simulado, sem transmissão SEFAZ)';
        }

        Notification::make()
            ->title($tituloNotificacao)
            ->body($body)
            ->success()
            ->send();

        if ($vendaId && $imprimir !== null) {
            $imprimir((int) $vendaId);
        }

        $this->dispatch('erp-pdv-caixa-opened');
    }

    public function cupomTotalValor(): float
    {
        return round(collect($this->cupomItens)->sum(fn (array $item): float => (float) ($item['total'] ?? 0)), 2);
    }

    public function getCupomTotalProperty(): string
    {
        return ErpMoney::formatBr($this->cupomTotalValor());
    }

    public function getSelectedSearchResultProperty(): ?array
    {
        if ($this->selectedSearchIndex === null || ! isset($this->pdvSearchResults[$this->selectedSearchIndex])) {
            return null;
        }

        return $this->pdvSearchResults[$this->selectedSearchIndex];
    }

    public function getCupomItemSelecionadoProperty(): ?array
    {
        if ($this->selectedCupomIndex === null || ! isset($this->cupomItens[$this->selectedCupomIndex])) {
            return null;
        }

        return $this->cupomItens[$this->selectedCupomIndex];
    }

    public function getCupomItemQtdProperty(): string
    {
        $item = $this->cupomItemSelecionado;

        if (! $item) {
            return '0';
        }

        $qtd = (float) ($item['quantidade'] ?? 0);

        return fmod($qtd, 1.0) === 0.0
            ? (string) (int) $qtd
            : ErpMoney::formatBr($qtd, 3);
    }

    public function getCupomItemPrecoProperty(): string
    {
        $item = $this->cupomItemSelecionado;

        if (! $item) {
            return ErpMoney::formatBr(0);
        }

        return ErpMoney::formatBr($item['preco'] ?? 0);
    }

    public function getCupomItemTotalProperty(): string
    {
        $item = $this->cupomItemSelecionado;

        if (! $item) {
            return ErpMoney::formatBr(0);
        }

        return ErpMoney::formatBr($item['total'] ?? 0);
    }

    public function getPdvLaunchItemTotalProperty(): string
    {
        $quantidade = $this->parsePdvLaunchQtd();
        $preco = ErpMoney::parseBr($this->pdvLaunchPreco, 2);

        return ErpMoney::formatBr(round($quantidade * $preco, 2));
    }

    public function formatCupomQuantidade(float $quantidade): string
    {
        return fmod($quantidade, 1.0) === 0.0
            ? (string) (int) $quantidade
            : number_format($quantidade, 3, ',', '');
    }

    protected function syncPdvPreviewFotoForProduct(?Product $product): void
    {
        $this->pdvPreviewFotoUrl = $product?->fotoUrl();
        $this->pdvPreviewProductName = $product
            ? mb_strtoupper(trim((string) $product->descricao), 'UTF-8')
            : null;
    }

    protected function syncPdvPreviewFotoFromSearchSelection(): void
    {
        if (! $this->pdvEmConsulta) {
            return;
        }

        $row = $this->selectedSearchResult;
        $productId = isset($row['product_id']) ? (int) $row['product_id'] : null;

        if (! $productId) {
            $this->pdvPreviewFotoUrl = null;
            $this->pdvPreviewProductName = null;

            return;
        }

        $this->syncPdvPreviewFotoForProduct(Product::query()->find($productId));
    }

    protected function syncPdvPreviewFotoFromCupomSelection(): void
    {
        if ($this->pdvEmConsulta) {
            return;
        }

        $item = $this->cupomItemSelecionado;
        $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;

        if (! $productId) {
            $this->pdvPreviewFotoUrl = null;
            $this->pdvPreviewProductName = null;

            return;
        }

        $this->syncPdvPreviewFotoForProduct(Product::query()->find($productId));
    }
}
