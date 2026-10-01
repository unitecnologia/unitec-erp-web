<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Models\CaixaConta;
use App\Models\ContaReceber;
use App\Models\Estoque;
use App\Models\ForcaVendasOrder;
use App\Models\FormaPagamento;
use App\Models\PedidoItem;
use App\Models\Person;
use App\Models\PriceTable;
use App\Models\Product;
use App\Models\ProductPriceTableItem;
use App\Models\Vendedor;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueNegativoPolicy;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Support\Erp\Pdv\PdvClienteLimiteService;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use App\Support\ForcaVendas\ForcaVendasFaturamentoService;
use App\Support\ForcaVendas\ForcaVendasItemAgrupamento;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;
use App\Support\ForcaVendas\ForcaVendasTelaVendaService;
use App\Filament\Pages\Concerns\ManagesBoletoPosDocumentoPrompt;
use App\Filament\Pages\Concerns\ManagesForcaVendasTelaVendaImportarOrcamento;
use App\Filament\Pages\Concerns\ManagesForcaVendasTelaVendaMargem;
use App\Filament\Pages\Concerns\ManagesForcaVendasTelaVendaParcelas;
use App\Filament\Pages\Concerns\ManagesForcaVendasTelaVendaTransportadora;
use App\Filament\Pages\Concerns\ManagesPdvFinalizarCartaoCanhoto;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ForcaVendasTelaVendaPage extends Page
{
    use ManagesBoletoPosDocumentoPrompt;
    use ManagesForcaVendasTelaVendaImportarOrcamento;
    use ManagesForcaVendasTelaVendaMargem;
    use ManagesForcaVendasTelaVendaParcelas;
    use ManagesForcaVendasTelaVendaTransportadora;
    use ManagesPdvFinalizarCartaoCanhoto;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $title = '';

    protected static ?string $slug = 'forca-vendas-tela-venda';

    protected static bool $shouldRegisterNavigation = false;

    /** @var 'venda'|'finalizacao' */
    public string $etapa = 'venda';

    /** Pedido FV em edição (null = nova venda). */
    #[Url(as: 'pedido')]
    public ?int $pedidoId = null;

    public ?string $davNumero = null;

    /** @var list<int>|string|null */
    public $tabelaPrazoDias = null;

    public ?int $clienteId = null;

    public string $clienteCodigo = '';

    public string $clienteNome = '';

    public string $clienteCpfCnpj = '';

    public string $clienteEndereco = '';

    public string $clienteNumero = '';

    public string $clienteBairro = '';

    public string $clienteCep = '';

    public string $clienteCidade = '';

    public string $clienteUf = '';

    public string $clienteFone = '';

    public string $clienteWhatsapp = '';

    /** Observação cadastrada em people.observacoes. Preenchida em aplicarCliente(). */
    public string $clienteObservacoes = '';

    public string $clienteBusca = '';

    /** @var list<array{id: int, codigo: string, nome: string, cpf_cnpj: string, doc_tipo?: string, limite?: string, utilizado?: string, vencidas?: string, tem_vencidas?: bool}> */
    public array $clienteSugestoes = [];

    public bool $clienteSugestoesOpen = false;

    public int $selectedClienteSugestaoIndex = 0;

    public ?int $vendedorId = null;

    public string $vendedorLabel = '';

    /** @var list<array{id: int, label: string}> */
    public array $vendedorOpcoes = [];

    public ?int $caixaId = null;

    public string $caixaLabel = '';

    /** @var list<array{id: int, label: string, situacao: string}> */
    public array $caixaOpcoes = [];

    public ?int $estoqueId = null;

    public string $estoqueLabel = '';

    public ?int $tabelaPrecoId = null;

    public string $tabelaPrecoLabel = '';

    /** @var list<array{id: int, label: string}> */
    public array $tabelaPrecoOpcoes = [];

    public bool $tabelaProgressOpen = false;

    public int $tabelaRecalcAlterados = 0;

    public string $codigoBarras = '';

    /** @var list<array{id: int, codigo: string, nome: string, atual: string, reservado: string, disponivel: string, preco: string}> */
    public array $produtoSugestoes = [];

    public bool $produtoSugestoesOpen = false;

    public int $selectedProdutoSugestaoIndex = 0;

    public string $quantidade = '1,000';

    public string $precoUnitario = '0,00';

    /** Preço de tabela do produto em lançamento (base para desconto/acréscimo ao digitar outro valor). */
    public string $precoListaItem = '0,00';

    public string $descontoPct = '0,00';

    public string $descontoValor = '0,00';

    public string $acrescimoPct = '0,00';

    public string $acrescimoValor = '0,00';

    public string $totalItem = '0,00';

    public bool $descontoModalOpen = false;

    public bool $excluirItemModalOpen = false;

    /** @var 'desconto'|'acrescimo' */
    public string $itemAjusteTipo = 'desconto';

    /** @var 'percentual'|'valor' */
    public string $itemAjusteModo = 'percentual';

    public string $itemAjusteValor = '0,00';

    /** Aplica o ajuste no formulário de lançamento ou no item selecionado da grade. */
    public ?string $itemAjusteAlvo = null;

    public ?int $produtoAtualId = null;

    public string $produtoAtualNome = '';

    public ?string $produtoAtualFoto = null;

    /**
     * @var list<array{
     *   key: string,
     *   product_id: int,
     *   product_grade_id: int|null,
     *   codigo: string,
     *   descricao: string,
     *   quantidade: float,
     *   preco_unitario: float,
     *   acrescimo: float,
     *   desconto: float,
     *   total: float,
     *   foto: ?string
     * }>
     */
    public array $itens = [];

    public ?int $itemSelecionado = null;

    public string $descontoPedidoPct = '0,00';

    public string $descontoPedidoValor = '0,00';

    public string $acrescimoPedidoPct = '0,00';

    public string $acrescimoPedidoValor = '0,00';

    public string $observacoes = '';

    public bool $observacaoPedidoModalOpen = false;

    public bool $observacaoClienteModalOpen = false;

    public string $aberturaData = '';

    public string $aberturaHora = '';

    /** @var list<array{id: int, descricao: string, atalho: string, valor: string}> */
    public array $meiosPagamento = [];

    public ?int $formaSelecionadaId = null;

    public int $selectedPagamentoIndex = 0;

    public string $valorPagamento = '0,00';

    public bool $gravando = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('vendas.access')
            || ErpAccess::currentCan('forca_vendas.access');
    }

    public function mount(): void
    {
        ErpScreen::set('Tela de Venda');
        $this->resetarVenda();

        if ($this->pedidoId) {
            $this->carregarPedidoExistente((int) $this->pedidoId);
            $this->dispatch('fv-tela-venda-focus-barcode');

            return;
        }

        // Nova venda: CONSUMIDOR FINAL já selecionado; Enter na razão → código do produto.
        $this->dispatch('fv-tela-venda-focus-cliente');
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-list-page',
            'erp-fv-tela-venda-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.forca-vendas.tela-venda.screen'),
            ]);
    }

    public function resetarVenda(): void
    {
        $agora = ErpTimezone::toLocal();
        $this->etapa = 'venda';
        $this->itens = [];
        $this->itemSelecionado = null;
        $this->codigoBarras = '';
        $this->quantidade = '1,000';
        $this->precoUnitario = '0,00';
        $this->precoListaItem = '0,00';
        $this->descontoPct = '0,00';
        $this->descontoValor = '0,00';
        $this->acrescimoPct = '0,00';
        $this->acrescimoValor = '0,00';
        $this->totalItem = '0,00';
        $this->produtoAtualId = null;
        $this->produtoAtualNome = '';
        $this->produtoAtualFoto = null;
        $this->produtoSugestoes = [];
        $this->produtoSugestoesOpen = false;
        $this->selectedProdutoSugestaoIndex = 0;
        $this->descontoPedidoPct = '0,00';
        $this->descontoPedidoValor = '0,00';
        $this->acrescimoPedidoPct = '0,00';
        $this->acrescimoPedidoValor = '0,00';
        $this->fecharMargemVenda();
        $this->observacoes = '';
        $this->clienteObservacoes = '';
        $this->observacaoPedidoModalOpen = false;
        $this->observacaoClienteModalOpen = false;
        $this->aberturaData = $agora->format('d/m/Y');
        $this->aberturaHora = $agora->format('H:i:s');
        $this->meiosPagamento = [];
        $this->formaSelecionadaId = null;
        $this->selectedPagamentoIndex = 0;
        $this->valorPagamento = '0,00';
        $this->clienteSugestoes = [];
        $this->clienteSugestoesOpen = false;
        $this->selectedClienteSugestaoIndex = 0;
        $this->clienteBusca = '';
        $this->gravando = false;
        $this->davNumero = null;
        $this->tabelaPrazoDias = null;
        $this->resetFvParcelasFaturamento();
        $this->resetFvTransporte();

        // Em edição, pedidoId vem da URL e não deve ser limpo aqui.
        if (! $this->pedidoId) {
            $this->pedidoId = null;
        }

        $this->caixaId = null;
        $this->caixaLabel = '';
        $this->estoqueId = null;
        $this->estoqueLabel = '';
        $this->vendedorId = null;
        $this->vendedorLabel = '';
        $this->tabelaPrecoId = null;
        $this->tabelaPrecoLabel = '';
        $this->tabelaProgressOpen = false;
        $this->tabelaRecalcAlterados = 0;
        $this->excluirItemModalOpen = false;
        $this->resetFinalizarCartaoCanhoto();

        $this->carregarClientePadrao();
        $this->carregarOpcoesOperacao();
        $this->carregarVendedorPadrao();
        $this->carregarTabelaPrecoPadrao();
        $this->carregarMeiosPagamento();
    }

    public function novoPedido(): void
    {
        $this->pedidoId = null;
        $this->resetarVenda();
        Notification::make()->title('Nova venda iniciada.')->success()->send();
        $this->dispatch('fv-tela-venda-focus-cliente');
    }

    public function cancelarVenda(): void
    {
        if ($this->etapa === 'finalizacao') {
            $this->voltarParaVenda();

            return;
        }

        $this->sair();
    }

    public function sair(): void
    {
        $this->redirect(ForcaVendasMonitorResource::getUrl('index'));
    }

    public function abrirObservacaoPedido(): void
    {
        $this->observacoes = $this->observacaoPedidoCaixaAlta($this->observacoes);
        $this->observacaoPedidoModalOpen = true;
        $this->dispatch('erp-fv-focus-obs-pedido');
    }

    public function fecharObservacaoPedido(?string $texto = null): void
    {
        $this->observacoes = $this->observacaoPedidoCaixaAlta($texto ?? $this->observacoes);
        $this->observacaoPedidoModalOpen = false;
        $this->persistirObservacaoPedidoGravada();
    }

    private function observacaoPedidoCaixaAlta(?string $texto): string
    {
        return trim(mb_strtoupper((string) $texto, 'UTF-8'));
    }

    /**
     * Pedido já aberto: grava só pedidos.observacoes (e o mesmo texto no payload).
     * Venda nova continua só em memória até o F3/F4.
     */
    private function persistirObservacaoPedidoGravada(): void
    {
        if (! $this->pedidoId) {
            return;
        }

        $order = ForcaVendasOrder::query()->with('pedido')->find($this->pedidoId);
        $pedido = $order?->pedido;

        if (! $order || ! $pedido) {
            return;
        }

        try {
            app(ForcaVendasTelaVendaService::class)->assertEditavel($order);
        } catch (\Throwable) {
            return;
        }

        $texto = $this->observacoes !== '' ? $this->observacoes : null;
        $pedido->update(['observacoes' => $texto]);

        $payload = is_array($order->payload) ? $order->payload : [];
        $payload['observacoes'] = $texto;
        $order->update(['payload' => $payload]);
    }

    public function abrirObservacaoCliente(): void
    {
        if (trim($this->clienteObservacoes) === '' || Person::isCodigoConsumidorFinal($this->clienteCodigo)) {
            $this->observacaoClienteModalOpen = false;

            return;
        }

        $this->observacaoClienteModalOpen = true;
    }

    public function fecharObservacaoCliente(): void
    {
        $this->observacaoClienteModalOpen = false;
    }

    public function updatedClienteBusca(string $value): void
    {
        $term = trim($value);

        if ($term === '') {
            $this->clienteSugestoes = [];
            $this->clienteSugestoesOpen = false;
            $this->selectedClienteSugestaoIndex = 0;

            return;
        }

        // Texto do cliente já selecionado (ex.: CONSUMIDOR FINAL) — não abre lista.
        if ($this->clienteJaSelecionadoCorresponde($term)) {
            $this->fecharSugestoesCliente();

            return;
        }

        $digits = preg_replace('/\D/', '', $term) ?: '';

        $people = Person::query()
            ->where(function ($q) use ($term, $digits): void {
                $q->where('codigo', 'like', $term.'%')
                    ->orWhere('nome_razao', 'like', '%'.$term.'%')
                    ->orWhere('apelido_fantasia', 'like', '%'.$term.'%');

                if ($digits !== '') {
                    $q->orWhere('cpf_cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('nome_razao')
            ->limit(12)
            ->get(['id', 'codigo', 'nome_razao', 'cpf_cnpj', 'limite_credito']);

        $this->clienteSugestoes = $this->montarSugestoesCliente($people);
        $this->clienteSugestoesOpen = $this->clienteSugestoes !== [];
        $this->selectedClienteSugestaoIndex = 0;
    }

    /**
     * Enter na razão social: confirma sugestão/cliente, ou mantém CONSUMIDOR FINAL e vai ao código.
     */
    public function confirmarClienteEAvancar(): void
    {
        if ($this->clienteSugestoesOpen && $this->clienteSugestoes !== []) {
            $index = $this->selectedClienteSugestaoIndex;
            if (! isset($this->clienteSugestoes[$index])) {
                $index = 0;
            }
            $this->selecionarCliente((int) $this->clienteSugestoes[$index]['id']);
            $this->dispatch('fv-tela-venda-focus-barcode');

            return;
        }

        $term = trim($this->clienteBusca);

        if ($term === '' || $this->ehTextoConsumidorFinal($term) || $this->clienteJaSelecionadoCorresponde($term)) {
            if ($term === '' || $this->ehTextoConsumidorFinal($term) || ! $this->clienteId) {
                $this->carregarClientePadrao();
            }
            $this->fecharSugestoesCliente();
            $this->dispatch('fv-tela-venda-focus-barcode');

            return;
        }

        $this->confirmarClienteFinalizacao();

        if ($this->clienteId) {
            $this->dispatch('fv-tela-venda-focus-barcode');
        }
    }

    public function moverSugestaoCliente(int $delta): void
    {
        if (! $this->clienteSugestoesOpen || $this->clienteSugestoes === []) {
            return;
        }

        $count = count($this->clienteSugestoes);
        $index = $this->selectedClienteSugestaoIndex + $delta;
        $this->selectedClienteSugestaoIndex = max(0, min($count - 1, $index));
        $this->dispatch('erp-fv-scroll-cliente-sugestao', index: $this->selectedClienteSugestaoIndex);
    }

    public function fecharSugestoesCliente(): void
    {
        $this->clienteSugestoes = [];
        $this->clienteSugestoesOpen = false;
        $this->selectedClienteSugestaoIndex = 0;
    }

    public function selecionarCliente(int $id): void
    {
        $person = Person::query()->find($id);

        if (! $person) {
            Notification::make()->title('Cliente não encontrado.')->warning()->send();

            return;
        }

        $this->aplicarCliente($person);
        $this->clienteSugestoes = [];
        $this->clienteSugestoesOpen = false;
        $this->selectedClienteSugestaoIndex = 0;
        $this->clienteBusca = $this->formatarClienteBusca($person);

        if ($this->etapa === 'venda') {
            $this->dispatch('fv-tela-venda-focus-barcode');
        }
    }

    public function confirmarClienteFinalizacao(): void
    {
        if ($this->clienteSugestoes !== []) {
            $index = $this->selectedClienteSugestaoIndex;
            if (! isset($this->clienteSugestoes[$index])) {
                $index = 0;
            }
            $this->selecionarCliente((int) $this->clienteSugestoes[$index]['id']);

            return;
        }

        $term = trim($this->clienteBusca);

        if ($term === '') {
            return;
        }

        // Aceita "6 — NOME" ou só código/nome
        if (preg_match('/^(\d+)\s*[—\-]/s*(.*)$/u', $term, $m)) {
            $this->clienteCodigo = $m[1];
            $this->buscarClientePorCodigo();

            return;
        }

        $digits = preg_replace('/\D/', '', $term) ?: '';
        $person = Person::query()
            ->where(function ($q) use ($term, $digits): void {
                $q->where('codigo', $term)
                    ->orWhere('codigo', ltrim($term, '0'))
                    ->orWhere('nome_razao', $term);

                if ($digits !== '' && strlen($digits) >= 11) {
                    $q->orWhere('cpf_cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('nome_razao')
            ->first();

        if (! $person) {
            Notification::make()->title('Cliente não encontrado.')->warning()->send();

            return;
        }

        $this->selecionarCliente((int) $person->id);
    }

    public function buscarClientePorCodigo(): void
    {
        $term = trim($this->clienteCodigo);

        if ($term === '') {
            return;
        }

        $person = Person::query()
            ->where('codigo', $term)
            ->orWhere('codigo', ltrim($term, '0'))
            ->first();

        if (! $person) {
            Notification::make()->title('Cliente não encontrado.')->warning()->send();

            return;
        }

        $this->aplicarCliente($person);
        $this->clienteBusca = trim(($person->codigo ? $person->codigo.' — ' : '').($person->nome_razao ?? ''));
        $this->clienteSugestoes = [];
        $this->clienteSugestoesOpen = false;
    }

    public function updatedQuantidade(): void
    {
        $this->quantidade = $this->sanitizarNumero($this->quantidade);
        $this->recalcularTotalItem();
    }

    public function updatedPrecoUnitario(): void
    {
        $this->precoUnitario = $this->sanitizarNumero($this->precoUnitario);
        $this->limparAjustePercentualSePrecoDiferenteDaLista();
        $this->recalcularTotalItem();
    }

    public function updatedCodigoBarras(string $value): void
    {
        $term = trim($value);

        if ($term === '' || ($this->produtoAtualNome !== '' && mb_strtoupper($term, 'UTF-8') === mb_strtoupper($this->produtoAtualNome, 'UTF-8'))) {
            $this->fecharSugestoesProduto();

            return;
        }

        $this->produtoSugestoes = $this->montarSugestoesProduto($term);
        $this->produtoSugestoesOpen = $this->produtoSugestoes !== [];
        $this->selectedProdutoSugestaoIndex = 0;

        if ($this->produtoSugestoesOpen) {
            $this->dispatch('erp-fv-produto-sugestoes-ready');
        }
    }

    public function moverSugestaoProduto(int $delta): void
    {
        // Navegação ↑↓ é feita no cliente (Alpine) para ficar imediata.
    }

    public function fecharSugestoesProduto(): void
    {
        $this->produtoSugestoes = [];
        $this->produtoSugestoesOpen = false;
        $this->selectedProdutoSugestaoIndex = 0;
    }

    public function selecionarProduto(int $id): void
    {
        $product = Product::query()->where('ativo', true)->find($id);

        if (! $product) {
            Notification::make()->title('Produto não encontrado.')->warning()->send();

            return;
        }

        $this->aplicarProdutoSelecionado($product);
    }

    public function confirmarCodigoProduto(): void
    {
        if ($this->produtoSugestoesOpen && $this->produtoSugestoes !== []) {
            $index = $this->selectedProdutoSugestaoIndex;
            if (! isset($this->produtoSugestoes[$index])) {
                $index = 0;
            }
            $this->selecionarProduto((int) $this->produtoSugestoes[$index]['id']);

            return;
        }

        $term = trim($this->codigoBarras);

        if ($term === '') {
            Notification::make()->title('Informe o código, barras ou nome do produto.')->warning()->send();

            return;
        }

        // Já confirmado (campo mostra a descrição): só avança para quantidade.
        if ($this->produtoAtualId && $this->produtoAtualNome !== ''
            && mb_strtoupper($term, 'UTF-8') === mb_strtoupper($this->produtoAtualNome, 'UTF-8')) {
            $this->dispatch('fv-tela-venda-focus-qtd');

            return;
        }

        $product = $this->findExactProduct($term);

        if (! $product) {
            $sugestoes = $this->montarSugestoesProduto($term);

            if (count($sugestoes) === 1) {
                $this->selecionarProduto((int) $sugestoes[0]['id']);

                return;
            }

            if ($sugestoes !== []) {
                $this->produtoSugestoes = $sugestoes;
                $this->produtoSugestoesOpen = true;
                $this->selectedProdutoSugestaoIndex = 0;
                $this->dispatch('erp-fv-produto-sugestoes-ready');
                Notification::make()->title('Selecione o produto na lista (↑ ↓ + Enter).')->info()->send();

                return;
            }

            Notification::make()->title('Produto não encontrado.')->warning()->send();

            return;
        }

        $this->aplicarProdutoSelecionado($product);
    }

    public function focoPrecoAposQtd(): void
    {
        if ($this->parseDecimal($this->quantidade) <= 0) {
            Notification::make()->title('Quantidade inválida.')->warning()->send();

            return;
        }

        $this->dispatch('fv-tela-venda-focus-preco');
    }

    public function abrirModalDescontoItem(): void
    {
        if ($this->etapa !== 'venda' || $this->descontoModalOpen) {
            return;
        }

        // Preferência: produto em lançamento; senão, item selecionado na grade.
        if ($this->produtoAtualId && $this->parseDecimal($this->precoUnitario) > 0) {
            $this->itemAjusteAlvo = 'form';
        } elseif ($this->itemSelecionado !== null && isset($this->itens[$this->itemSelecionado])) {
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
        $this->dispatch('erp-fv-focus-desconto-item');
    }

    public function fecharModalDescontoItem(): void
    {
        $this->descontoModalOpen = false;
        $this->itemAjusteAlvo = null;
    }

    public function updatedItemAjusteValor(): void
    {
        $this->itemAjusteValor = $this->sanitizarNumero($this->itemAjusteValor);
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
                'base' => $this->formatMoney(0),
                'novoPreco' => $this->formatMoney(0),
                'total' => $this->formatMoney(0),
                'tipo' => $this->itemAjusteTipo,
                'temAjuste' => false,
            ];
        }

        $calc = $this->calcularItemAjuste($ctx['preco'], $ctx['quantidade']);

        return [
            'descricao' => $ctx['descricao'],
            'base' => $this->formatMoney($calc['base']),
            'novoPreco' => $this->formatMoney($calc['novoPreco']),
            'total' => $this->formatMoney($calc['total']),
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
                $this->descontoValor = $this->formatMoney($ajusteLinha);
                $this->descontoPct = $ctx['preco'] > 0
                    ? $this->formatMoney(($calc['deltaUnit'] / $ctx['preco']) * 100)
                    : '0,00';
                $this->acrescimoValor = '0,00';
                $this->acrescimoPct = '0,00';
            } else {
                $this->acrescimoValor = $this->formatMoney($ajusteLinha);
                $this->acrescimoPct = $ctx['preco'] > 0
                    ? $this->formatMoney(($calc['deltaUnit'] / $ctx['preco']) * 100)
                    : '0,00';
                $this->descontoValor = '0,00';
                $this->descontoPct = '0,00';
            }

            $this->recalcularTotalItem();
            $tipo = $this->itemAjusteTipo;
            $this->fecharModalDescontoItem();
            // Registra o item na grade já com o ajuste (não deixa só na barra de inclusão).
            $this->adicionarItem();
            Notification::make()
                ->title($tipo === 'acrescimo' ? 'Acréscimo aplicado.' : 'Desconto aplicado.')
                ->success()
                ->send();

            return;
        }

        $index = (int) $this->itemSelecionado;
        $item = $this->itens[$index];
        $qtd = (float) $item['quantidade'];
        $preco = (float) $item['preco_unitario'];

        if ($this->itemAjusteTipo === 'desconto') {
            $item['desconto'] = $ajusteLinha;
            $item['acrescimo'] = 0.0;
        } else {
            $item['acrescimo'] = $ajusteLinha;
            $item['desconto'] = 0.0;
        }

        $item['total'] = round(($qtd * $preco) + (float) $item['acrescimo'] - (float) $item['desconto'], 2);
        $this->itens[$index] = $item;

        $tipo = $this->itemAjusteTipo;
        $this->fecharModalDescontoItem();
        $this->dispatch('fv-tela-venda-focus-barcode');
        Notification::make()
            ->title($tipo === 'acrescimo' ? 'Acréscimo aplicado.' : 'Desconto aplicado.')
            ->success()
            ->send();
    }

    /**
     * @return array{descricao: string, preco: float, quantidade: float}|null
     */
    private function contextoItemAjuste(): ?array
    {
        if ($this->itemAjusteAlvo === 'form' && $this->produtoAtualId) {
            return [
                'descricao' => $this->produtoAtualNome,
                'preco' => $this->parseDecimal($this->precoUnitario),
                'quantidade' => max(0.0, $this->parseDecimal($this->quantidade)),
            ];
        }

        if ($this->itemAjusteAlvo === 'grid' && $this->itemSelecionado !== null && isset($this->itens[$this->itemSelecionado])) {
            $item = $this->itens[$this->itemSelecionado];

            return [
                'descricao' => (string) $item['descricao'],
                'preco' => (float) $item['preco_unitario'],
                'quantidade' => (float) $item['quantidade'],
            ];
        }

        return null;
    }

    /**
     * @return array{base: float, deltaUnit: float, novoPreco: float, total: float}
     */
    private function calcularItemAjuste(float $base, float $quantidade): array
    {
        $valor = $this->parseDecimal($this->itemAjusteValor);

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

        return [
            'base' => $base,
            'deltaUnit' => $deltaUnit,
            'novoPreco' => $novoPreco,
            'total' => round($quantidade * $novoPreco, 2),
        ];
    }

    public function updatedDescontoPedidoPct(): void
    {
        $this->descontoPedidoPct = $this->sanitizarNumero($this->descontoPedidoPct);
        $base = $this->baseItensParaAjuste();
        $pct = $this->parseDecimal($this->descontoPedidoPct);
        $this->descontoPedidoValor = $this->formatMoney(($base * $pct) / 100);
        $this->sincronizarPagamentoAposAjustePedido();
    }

    public function updatedDescontoPedidoValor(): void
    {
        $this->descontoPedidoValor = $this->sanitizarNumero($this->descontoPedidoValor);
        $base = $this->baseItensParaAjuste();
        $desc = $this->parseDecimal($this->descontoPedidoValor);
        $this->descontoPedidoPct = $base > 0 ? $this->formatMoney(($desc / $base) * 100) : '0,00';
        $this->sincronizarPagamentoAposAjustePedido();
    }

    public function updatedAcrescimoPedidoPct(): void
    {
        $this->acrescimoPedidoPct = $this->sanitizarNumero($this->acrescimoPedidoPct);
        $base = $this->baseItensParaAjuste();
        $pct = $this->parseDecimal($this->acrescimoPedidoPct);
        $this->acrescimoPedidoValor = $this->formatMoney(($base * $pct) / 100);
        $this->sincronizarPagamentoAposAjustePedido();
    }

    public function updatedAcrescimoPedidoValor(): void
    {
        $this->acrescimoPedidoValor = $this->sanitizarNumero($this->acrescimoPedidoValor);
        $base = $this->baseItensParaAjuste();
        $acr = $this->parseDecimal($this->acrescimoPedidoValor);
        $this->acrescimoPedidoPct = $base > 0 ? $this->formatMoney(($acr / $base) * 100) : '0,00';
        $this->sincronizarPagamentoAposAjustePedido();
    }

    public function updatedMeiosPagamento(mixed $value, ?string $key = null): void
    {
        if (! is_string($key) || ! preg_match('/^(\d+)\.valor$/', $key, $m)) {
            return;
        }

        $index = (int) $m[1];

        if (! isset($this->meiosPagamento[$index])) {
            return;
        }

        $this->normalizarValorPagamentoLinha($index, (string) ($value ?? ''));
    }

    /**
     * Digitação normal (60 → 60,00). Não usa máscara de centavos do PDV.
     */
    private function normalizarValorPagamentoLinha(int $index, string $raw): void
    {
        $raw = trim($raw);
        $somenteDigitos = preg_replace('/\D+/', '', $raw) ?? '';

        if ($raw === '' || $somenteDigitos === '') {
            $this->meiosPagamento[$index]['valor'] = '0,00';
        } else {
            $this->meiosPagamento[$index]['valor'] = ErpMoney::formatBr(ErpMoney::parseBr($raw, 2), 2);
        }

        $this->formaSelecionadaId = (int) ($this->meiosPagamento[$index]['id'] ?? 0);
        $this->selectedPagamentoIndex = $index;
    }

    public function adicionarItem(): void
    {
        $product = null;

        if ($this->produtoAtualId) {
            $product = Product::query()->where('ativo', true)->find($this->produtoAtualId);
        }

        if (! $product) {
            $term = trim($this->codigoBarras);

            if ($term === '') {
                Notification::make()->title('Informe o código ou código de barras.')->warning()->send();

                return;
            }

            $product = $this->findProduct($term);
        }

        if (! $product) {
            Notification::make()->title('Produto não encontrado.')->warning()->send();

            return;
        }

        $this->carregarProdutoNoForm($product);

        $qtd = $this->parseDecimal($this->quantidade);
        $digitado = $this->parseDecimal($this->precoUnitario);
        $desconto = $this->parseDecimal($this->descontoValor);
        $acrescimo = $this->parseDecimal($this->acrescimoValor);
        [$preco, $desconto, $acrescimo] = $this->resolverPrecoDescontoAcrescimoDigitado(
            $qtd,
            $digitado,
            $desconto,
            $acrescimo,
        );

        if ($qtd <= 0) {
            Notification::make()->title('Quantidade inválida.')->warning()->send();

            return;
        }

        if ($this->bloquearSeOperacaoInvalida(exigeEstoque: ! $product->is_servico)) {
            return;
        }

        if (EstoqueNegativoPolicy::ativo() && ! $product->is_servico) {
            $jaNoPedido = 0.0;
            foreach ($this->itens as $existente) {
                if ((int) ($existente['product_id'] ?? 0) === (int) $product->id) {
                    $jaNoPedido += (float) ($existente['quantidade'] ?? 0);
                }
            }

            $disponivel = app(EstoqueReservaService::class)->disponivel($product, null, $this->estoqueId);

            if ($disponivel < ($qtd + $jaNoPedido)) {
                Notification::make()
                    ->title(
                        'Estoque insuficiente para '.$product->descricao
                        .' (disponível: '.$this->formatQty($disponivel).').'
                        .' Bloqueio de estoque negativo está ativo.'
                    )
                    ->danger()
                    ->send();

                return;
            }
        }

        $productId = (int) $product->id;
        $gradeId = null;

        // Agrupa na mesma linha quando for o mesmo produto (código/grade) e mesmo preço unitário.
        $existenteIndex = $this->indiceItemAgrupavel($productId, $gradeId, $preco);

        if ($existenteIndex !== null) {
            $item = $this->itens[$existenteIndex];
            $consolidado = ForcaVendasItemAgrupamento::consolidar(
                (float) $item['quantidade'],
                (float) ($item['desconto'] ?? 0),
                (float) ($item['acrescimo'] ?? 0),
                $qtd,
                $desconto,
                $acrescimo,
            );

            $novaQtd = $consolidado['quantidade'];
            $novoDesconto = $consolidado['desconto'];
            $novoAcrescimo = $consolidado['acrescimo'];

            $item['quantidade'] = $novaQtd;
            $item['acrescimo'] = $novoAcrescimo;
            $item['desconto'] = $novoDesconto;
            $item['total'] = round(($novaQtd * $preco) + $novoAcrescimo - $novoDesconto, 2);

            // Move a linha atualizada para o topo (último movimento fica primeiro).
            array_splice($this->itens, $existenteIndex, 1);
            array_unshift($this->itens, $item);
        } else {
            array_unshift($this->itens, [
                'key' => uniqid('i', true),
                'product_id' => $productId,
                'product_grade_id' => $gradeId,
                'codigo' => (string) ($product->codigo ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'quantidade' => $qtd,
                'preco_unitario' => $preco,
                'acrescimo' => $acrescimo,
                'desconto' => $desconto,
                'total' => round(($qtd * $preco) + $acrescimo - $desconto, 2),
                'foto' => $product->fotoUrl(),
            ]);
        }

        $this->itens = array_values($this->itens);
        $this->itemSelecionado = 0;
        $this->limparEntradaItem();
        $this->dispatch('fv-tela-venda-focus-barcode');
    }

    /**
     * Índice da linha existente para agrupar o mesmo item (produto + grade + preço).
     */
    private function indiceItemAgrupavel(int $productId, ?int $gradeId, float $preco): ?int
    {
        foreach ($this->itens as $index => $item) {
            if ((int) ($item['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $itemGrade = isset($item['product_grade_id']) && $item['product_grade_id'] !== null
                ? (int) $item['product_grade_id']
                : null;

            if ($itemGrade !== $gradeId) {
                continue;
            }

            if (round((float) ($item['preco_unitario'] ?? 0), 2) !== round($preco, 2)) {
                continue;
            }

            return (int) $index;
        }

        return null;
    }

    public function selecionarItem(int $index): void
    {
        if (! isset($this->itens[$index])) {
            return;
        }

        $this->itemSelecionado = $index;
        $item = $this->itens[$index];
        $this->produtoAtualId = (int) $item['product_id'];
        $this->produtoAtualNome = (string) $item['descricao'];
        $this->produtoAtualFoto = $item['foto'] ?? null;
    }

    /**
     * Duplo clique no nome do produto: remove da grade e recoloca na barra de inclusão.
     */
    public function puxarItemParaInclusao(int $index): void
    {
        if ($this->etapa !== 'venda' || $this->descontoModalOpen || $this->excluirItemModalOpen) {
            return;
        }

        if (! isset($this->itens[$index])) {
            return;
        }

        $item = $this->itens[$index];
        $preco = (float) ($item['preco_unitario'] ?? 0);
        $qtd = (float) ($item['quantidade'] ?? 0);
        $desconto = (float) ($item['desconto'] ?? 0);
        $acrescimo = (float) ($item['acrescimo'] ?? 0);

        $this->produtoAtualId = (int) ($item['product_id'] ?? 0) ?: null;
        $this->produtoAtualNome = (string) ($item['descricao'] ?? '');
        $this->produtoAtualFoto = $item['foto'] ?? null;
        $this->codigoBarras = $this->produtoAtualNome !== ''
            ? $this->produtoAtualNome
            : (string) ($item['codigo'] ?? '');
        $this->quantidade = $this->formatQty($qtd > 0 ? $qtd : 1);
        $this->precoUnitario = $this->formatMoney($preco);
        $lista = $preco;
        if ($this->produtoAtualId) {
            $produtoLista = Product::query()->where('ativo', true)->find($this->produtoAtualId);
            if ($produtoLista) {
                $lista = $this->precoNaTabelaSelecionada($produtoLista)
                    ?? (float) ($produtoLista->preco_venda ?? $preco);
            }
        }
        $this->precoListaItem = $this->formatMoney($lista > 0 ? $lista : $preco);
        // Mantém % do ajuste; o valor R$ é recalculado ao mudar preço/qtd (evita interferência do valor antigo).
        $this->descontoPct = $preco > 0 && $qtd > 0
            ? $this->formatMoney(($desconto / ($preco * $qtd)) * 100)
            : '0,00';
        $this->acrescimoPct = $preco > 0 && $qtd > 0
            ? $this->formatMoney(($acrescimo / ($preco * $qtd)) * 100)
            : '0,00';
        $this->descontoValor = $this->formatMoney($desconto);
        $this->acrescimoValor = $this->formatMoney($acrescimo);
        $this->recalcularTotalItem();
        $this->fecharSugestoesProduto();

        array_splice($this->itens, $index, 1);
        $this->itens = array_values($this->itens);
        $this->itemSelecionado = $this->itens === []
            ? null
            : min($index, count($this->itens) - 1);

        $this->dispatch('fv-tela-venda-focus-qtd');
    }

    public function pedirConfirmacaoExcluirItemPorIndice(int $index): void
    {
        if ($this->etapa !== 'venda' || $this->descontoModalOpen || $this->excluirItemModalOpen) {
            return;
        }

        if (! isset($this->itens[$index])) {
            return;
        }

        $this->itemSelecionado = $index;
        $this->excluirItemModalOpen = true;
        $this->dispatch('erp-fv-focus-excluir-item-sim');
    }

    public function cancelarExcluirItem(): void
    {
        $this->excluirItemModalOpen = false;
        $this->dispatch('fv-tela-venda-focus-barcode');
    }

    public function confirmarExcluirItem(): void
    {
        $this->excluirItemModalOpen = false;
        $this->removerItemSelecionado();
        $this->dispatch('fv-tela-venda-focus-barcode');
    }

    public function removerItemSelecionado(): void
    {
        if ($this->itemSelecionado === null || ! isset($this->itens[$this->itemSelecionado])) {
            Notification::make()->title('Selecione um item para remover.')->warning()->send();

            return;
        }

        array_splice($this->itens, $this->itemSelecionado, 1);
        $this->itens = array_values($this->itens);
        $this->itemSelecionado = $this->itens === [] ? null : min($this->itemSelecionado, count($this->itens) - 1);
        $this->produtoAtualId = null;
        $this->produtoAtualNome = '';
        $this->produtoAtualFoto = null;
    }

    public function irParaFinalizacao(): void
    {
        if ($this->clienteId === null) {
            $busca = trim((string) $this->clienteBusca);
            $nome = trim((string) $this->clienteNome);

            if ($this->ehTextoConsumidorFinal($busca) || $this->ehTextoConsumidorFinal($nome) || ($busca === '' && $nome === '')) {
                $person = Person::resolveConsumidorFinal();
                $this->aplicarCliente($person);
                $this->clienteBusca = $this->formatarClienteBusca($person);
            } else {
                Notification::make()->title('Selecione o cliente.')->warning()->send();

                return;
            }
        }

        if ($this->itens === []) {
            Notification::make()->title('Inclua ao menos um item.')->warning()->send();

            return;
        }

        $this->etapa = 'finalizacao';
        ErpScreen::set('Finalização do Pedido');
        $this->resetFinalizarCartaoCanhoto();
        $this->carregarMeiosPagamento();

        if (trim($this->clienteBusca) === '' && filled($this->clienteNome)) {
            $this->clienteBusca = trim(($this->clienteCodigo ? $this->clienteCodigo.' — ' : '').$this->clienteNome);
        }
        $this->clienteSugestoes = [];
        $this->clienteSugestoesOpen = false;

        if ($this->formaSelecionadaId === null && $this->meiosPagamento !== []) {
            $this->formaSelecionadaId = (int) $this->meiosPagamento[0]['id'];
            $this->selectedPagamentoIndex = 0;
        } else {
            $this->selectedPagamentoIndex = $this->indexFormaSelecionada();
        }

        $this->dispatch('erp-fv-focus-pagamento', index: $this->selectedPagamentoIndex);
    }

    public function voltarParaVenda(): void
    {
        if ($this->fvTabelaPrazoConsulta) {
            $this->cancelarFvTabelaPrazoConsulta();

            return;
        }

        if ($this->finalizarCartaoCanhotoAberta) {
            $this->cancelFinalizarCartaoCanhoto();

            return;
        }

        $this->etapa = 'venda';
        ErpScreen::set('Tela de Venda');
        $this->dispatch('fv-tela-venda-focus-barcode');
    }

    public function selectPagamentoRow(int $index): void
    {
        if (! isset($this->meiosPagamento[$index])) {
            return;
        }

        $this->selectedPagamentoIndex = $index;
        $this->formaSelecionadaId = (int) $this->meiosPagamento[$index]['id'];
    }

    public function movePagamentoSelection(int $delta): void
    {
        $count = count($this->meiosPagamento);

        if ($count === 0) {
            return;
        }

        $index = $this->selectedPagamentoIndex + $delta;
        $index = max(0, min($count - 1, $index));
        $this->selectedPagamentoIndex = $index;
        $this->formaSelecionadaId = (int) ($this->meiosPagamento[$index]['id'] ?? 0);
        $this->dispatch('erp-fv-focus-pagamento', index: $index);
    }

    public function selectPagamentoByAtalho(string $atalho): void
    {
        $atalho = strtoupper(trim($atalho));

        foreach ($this->meiosPagamento as $index => $meio) {
            if (strtoupper((string) ($meio['atalho'] ?? '')) === $atalho) {
                $this->aplicarValorRestanteNaForma($index);

                return;
            }
        }
    }

    /**
     * Enter no valor: grava o digitado e segue o fluxo do PDV
     * (canhoto cartão → próxima linha → foco no botão de confirmar).
     */
    public function confirmarValorPagamentoLinha(?string $valorFromInput = null): void
    {
        $index = $this->selectedPagamentoIndex;

        if (! isset($this->meiosPagamento[$index])) {
            return;
        }

        if ($valorFromInput !== null) {
            $this->normalizarValorPagamentoLinha($index, $valorFromInput);
        }

        $meio = $this->meiosPagamento[$index];
        $valor = ErpMoney::parseBr($meio['valor'] ?? '0');

        if ($valor > 0 && PdvFinalizarPagamentosHelper::isFormaCartao($meio)) {
            $this->finalizarCartaoCanhotoConfirmado = false;
            $this->finalizarCartaoParcelasRows = [];
            $this->ensureCartaoCanhoto();

            return;
        }

        if ($valor > 0 && PdvFinalizarPagamentosHelper::precisaParcelasCarne($this->fvPagamentoComoPdv($meio))) {
            // Preserva tabelaPrazoDias negociado (app/pedido); só regenera a grade.
            $this->fvParcelasRows = [];

            if (! $this->ensureFvTabelaPrazoCrediario()) {
                return;
            }
        }

        if ($valor > 0 && PdvFinalizarPagamentosHelper::isFormaTef($meio)) {
            $count = count($this->meiosPagamento);

            if ($this->selectedPagamentoIndex < $count - 1) {
                $this->movePagamentoSelection(1);
            }

            return;
        }

        if ($this->valorRestante() <= 0) {
            $this->dispatch('erp-fv-focus-finalizar-ok');

            return;
        }

        $count = count($this->meiosPagamento);

        if ($this->selectedPagamentoIndex < $count - 1) {
            $this->movePagamentoSelection(1);
        }
    }

    public function selecionarForma(int $id): void
    {
        foreach ($this->meiosPagamento as $index => $meio) {
            if ((int) $meio['id'] === $id) {
                $this->selectPagamentoRow($index);

                return;
            }
        }
    }

    public function faturarPedido(): void
    {
        $this->gravarPedidoInterno(faturar: true);
    }

    /**
     * Grava o pedido como pendente (DAV + ForcaVendasOrder) sem exigir meios de pagamento.
     * Mesmo fluxo do FV; o faturamento fica para o monitor.
     */
    public function gravarPedidoPendente(): void
    {
        if ($this->gravando) {
            return;
        }

        if ($this->descontoModalOpen || $this->excluirItemModalOpen || $this->fvImportarOrcamentoOpen || $this->margemModalOpen) {
            return;
        }

        if ($this->clienteId === null) {
            $busca = trim((string) $this->clienteBusca);
            $nome = trim((string) $this->clienteNome);

            if ($this->ehTextoConsumidorFinal($busca) || $this->ehTextoConsumidorFinal($nome) || ($busca === '' && $nome === '')) {
                $person = Person::resolveConsumidorFinal();
                $this->aplicarCliente($person);
                $this->clienteBusca = $this->formatarClienteBusca($person);
            } else {
                Notification::make()->title('Selecione o cliente.')->warning()->send();

                return;
            }
        }

        if ($this->itens === []) {
            Notification::make()->title('Inclua ao menos um item.')->warning()->send();

            return;
        }

        if ($this->bloquearSeOperacaoInvalida(exigeEstoque: $this->pedidoExigeEstoque())) {
            return;
        }

        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->gravando = true;

        try {
            $existente = null;

            if ($this->pedidoId) {
                $existente = ForcaVendasOrder::query()->find($this->pedidoId);

                if (! $existente) {
                    throw new \RuntimeException('Pedido em edição não encontrado.');
                }
            }

            $order = app(ForcaVendasTelaVendaService::class)->gravarPedido($user, [
                'cliente_id' => (int) $this->clienteId,
                'vendedor_id' => $this->vendedorId,
                'caixa_conta_id' => $this->caixaId,
                'estoque_id' => $this->estoqueId,
                'observacoes' => trim($this->observacoes) !== '' ? trim($this->observacoes) : null,
                'desconto_valor' => 0,
                'percentual_desconto' => 0,
                'forma_pagamento_id' => null,
                'forma_pagamento' => null,
                'tabela_prazo_dias' => $this->tabelaPrazoDias,
                'cartao_canhoto' => null,
                'transporte' => $this->fvTransportePayload(),
                'itens' => $this->itensParaGravar(),
            ], $existente);

            Notification::make()
                ->title($existente ? 'Pedido atualizado.' : 'Pedido gravado como pendente.')
                ->body('DAV Nº '.$order->pedido?->numero.' — aguardando faturamento no monitor.')
                ->success()
                ->send();

            $this->redirect(ForcaVendasMonitorResource::getUrl('index'));
        } catch (\Throwable $e) {
            $this->gravando = false;
            Notification::make()
                ->title('Não foi possível gravar o pedido pendente.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Grava o pedido (DAV + ForcaVendasOrder) e fatura (venda/estoque/financeiro),
     * salvo se o cliente cair na regra de liberação financeira (limite de crédito
     * na parte a prazo), caso em que o pedido fica retido como "Financeiro" para
     * aprovação no monitor.
     */
    private function gravarPedidoInterno(bool $faturar = true): void
    {
        if ($this->gravando) {
            return;
        }

        if ($this->totalInformado() <= 0 && $this->meiosPagamento !== []) {
            $this->aplicarValorRestanteNaForma($this->selectedPagamentoIndex, focus: false);
        }

        $totalInformado = $this->totalInformado();
        $totalLiquido = $this->totalLiquido();

        if ($totalInformado + 0.009 < $totalLiquido) {
            Notification::make()
                ->title('Informe o valor nos meios de pagamento.')
                ->body('Valor restante: R$ '.$this->formatMoney($this->valorRestante()))
                ->warning()
                ->send();

            return;
        }

        $formaPrincipal = collect($this->meiosPagamento)
            ->first(fn (array $m): bool => ErpMoney::parseBr($m['valor'] ?? '0') > 0);

        if (! $formaPrincipal) {
            Notification::make()->title('Selecione um meio de pagamento.')->warning()->send();

            return;
        }

        if ($msg = $this->validaCartaoCanhotoFinalizar()) {
            Notification::make()->title($msg)->warning()->send();
            $this->ensureCartaoCanhoto();

            return;
        }

        if (! $this->ensureFvTabelaPrazoCrediario(abrirSeNecessario: true)) {
            return;
        }

        if ($this->bloquearSeOperacaoInvalida(exigeEstoque: $this->pedidoExigeEstoque())) {
            return;
        }

        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->gravando = true;

        try {
            $existente = null;

            if ($this->pedidoId) {
                $existente = ForcaVendasOrder::query()->find($this->pedidoId);

                if (! $existente) {
                    throw new \RuntimeException('Pedido em edição não encontrado.');
                }
            }

            $canhoto = $this->finalizarCartaoCanhotoPayload();
            $tabelaPrazo = $this->tabelaPrazoDias;

            if ($canhoto !== null && ! empty($canhoto['dias']) && is_array($canhoto['dias'])) {
                $tabelaPrazo = implode(',', array_map('intval', $canhoto['dias']));
            }

            $order = app(ForcaVendasTelaVendaService::class)->gravarPedido($user, [
                'cliente_id' => (int) $this->clienteId,
                'vendedor_id' => $this->vendedorId,
                'caixa_conta_id' => $this->caixaId,
                'estoque_id' => $this->estoqueId,
                'observacoes' => trim($this->observacoes) !== '' ? trim($this->observacoes) : null,
                // Desconto/acréscimo do pedido já vão rateados nos itens.
                'desconto_valor' => 0,
                'percentual_desconto' => 0,
                'forma_pagamento_id' => (int) $formaPrincipal['id'],
                'forma_pagamento' => (string) $formaPrincipal['descricao'],
                'tabela_prazo_dias' => $tabelaPrazo,
                'cartao_canhoto' => $canhoto,
                'transporte' => $this->fvTransportePayload(),
                'itens' => $this->itensParaGravar(),
            ], $existente);

            if (! $faturar) {
                Notification::make()
                    ->title($existente ? 'Pedido atualizado.' : 'Pedido gravado.')
                    ->body('DAV Nº '.$order->pedido?->numero.' — aguardando faturamento no monitor.')
                    ->success()
                    ->send();

                $this->redirect(ForcaVendasMonitorResource::getUrl('index'));

                return;
            }

            $this->finalizarFaturamento($order);
        } catch (\Throwable $e) {
            $this->gravando = false;
            Notification::make()
                ->title($faturar ? 'Não foi possível faturar o pedido.' : 'Não foi possível gravar o pedido.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Fatura o pedido recém-gravado, respeitando a liberação financeira: se a parte
     * a prazo estourar o limite de crédito do cliente, retém como "Financeiro".
     */
    private function finalizarFaturamento(ForcaVendasOrder $order): void
    {
        $order->loadMissing('pedido');

        if (! $order->pedido) {
            throw new \RuntimeException('DAV do pedido não encontrado para faturar.');
        }

        $restricao = $this->restricaoFinanceira($order);

        if ($restricao !== null) {
            $this->marcarPedidoFinanceiro($order, $restricao);

            Notification::make()
                ->title('Pedido em análise financeira.')
                ->body('DAV Nº '.$order->pedido->numero.' retido: '.$restricao)
                ->warning()
                ->send();

            $this->redirect(ForcaVendasMonitorResource::getUrl('index'));

            return;
        }

        $resultado = DB::transaction(
            fn (): array => app(ForcaVendasFaturamentoService::class)->faturar($order, $order->pedido)
        );

        Notification::make()
            ->title('Pedido faturado.')
            ->body('DAV Nº '.$order->pedido->numero.' — venda gerada e estoque baixado.')
            ->success()
            ->send();

        $monitorUrl = ForcaVendasMonitorResource::getUrl('index');
        $contas = $resultado['contas_receber'] ?? [];

        if ($this->offerEmitirBoletosPosDocumento($contas, $monitorUrl)) {
            $this->gravando = false;
            // Venda já faturada: limpa a grade para o modal de boleto ficar em evidência.
            $this->pedidoId = null;
            $this->davNumero = null;
            $this->itens = [];
            $this->etapa = 'venda';
            $this->meiosPagamento = [];
            $this->resetFvParcelasFaturamento();
            $this->resetFinalizarCartaoCanhoto();

            return;
        }

        $this->redirect($monitorUrl);
    }

    /**
     * Motivo da retenção financeira (limite de crédito) considerando apenas a
     * parte a prazo do pagamento. Retorna null quando não há restrição.
     */
    private function restricaoFinanceira(ForcaVendasOrder $order): ?string
    {
        $aPrazo = $this->valorAPrazo();

        if ($aPrazo <= 0 || ! $this->clienteId) {
            return null;
        }

        $person = Person::query()->find((int) $this->clienteId);

        if (! $person) {
            return null;
        }

        return app(PdvClienteLimiteService::class)->valida($person, $aPrazo);
    }

    private function valorAPrazo(): float
    {
        $total = 0.0;

        foreach ($this->meiosPagamento as $meio) {
            if (PdvFinalizarPagamentosHelper::isFormaAPrazoPagamento([
                'forma' => (string) ($meio['descricao'] ?? $meio['forma'] ?? ''),
                'tipo' => (string) ($meio['tipo'] ?? ''),
                'tipo_movimento' => (string) ($meio['tipo_movimento'] ?? ''),
            ])) {
                $total += ErpMoney::parseBr($meio['valor'] ?? '0');
            }
        }

        return round($total, 2);
    }

    private function marcarPedidoFinanceiro(ForcaVendasOrder $order, string $motivo): void
    {
        $payload = is_array($order->payload) ? $order->payload : [];
        $payload['restricao_financeira'] = true;
        $payload['restricao_financeira_motivo'] = $motivo;

        $order->forceFill([
            'situacao' => ForcaVendasOrder::SITUACAO_FINANCEIRO,
            'payload' => $payload,
        ])->save();
    }

    #[Computed]
    public function totalBruto(): float
    {
        return round(array_sum(array_map(
            fn (array $i): float => ((float) $i['quantidade'] * (float) $i['preco_unitario']) + (float) $i['acrescimo'],
            $this->itens,
        )), 2);
    }

    #[Computed]
    public function totalDescontosItens(): float
    {
        return round(array_sum(array_map(fn (array $i): float => (float) $i['desconto'], $this->itens)), 2);
    }

    #[Computed]
    public function totalAcrescimosItens(): float
    {
        return round(array_sum(array_map(fn (array $i): float => (float) $i['acrescimo'], $this->itens)), 2);
    }

    #[Computed]
    public function exibirCustoProdutoGrade(): bool
    {
        return (bool) (ErpContext::currentEmpresa()?->param_monitor_vendas_exibir_custo_produto ?? false);
    }

    /**
     * Custos unitários em lote para a grade (só quando o parâmetro está ligado).
     *
     * @return array<int, float>
     */
    #[Computed]
    public function custosUnitariosGrade(): array
    {
        if (! $this->exibirCustoProdutoGrade || $this->itens === []) {
            return [];
        }

        $ids = array_map(
            static fn (array $i): int => (int) ($i['product_id'] ?? 0),
            $this->itens,
        );

        return ForcaVendasMargemVendaCalculator::custosUnitariosPorProduto(
            $ids,
            ErpContext::currentEmpresaId(),
        );
    }

    public function totalLiquido(): float
    {
        $brutoItens = round(array_sum(array_map(fn (array $i): float => (float) $i['total'], $this->itens)), 2);
        $acr = $this->acrescimoPedidoEfetivo();
        $desc = $this->descontoPedidoEfetivo();

        return round($brutoItens + $acr - $desc, 2);
    }

    public function totalInformado(): float
    {
        return round(array_sum(array_map(
            fn (array $m): float => ErpMoney::parseBr($m['valor'] ?? '0'),
            $this->meiosPagamento
        )), 2);
    }

    public function valorRestante(): float
    {
        return round(max(0, $this->totalLiquido() - $this->totalInformado()), 2);
    }

    public function troco(): float
    {
        return round(max(0, $this->totalInformado() - $this->totalLiquido()), 2);
    }

    public function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public function formatQty(float $value): string
    {
        return number_format($value, 3, ',', '.');
    }

    private function carregarPedidoExistente(int $pedidoId): void
    {
        $order = ForcaVendasOrder::query()
            ->with(['pedido.itens.product', 'cliente', 'vendedor', 'user'])
            ->find($pedidoId);

        if (! $order) {
            Notification::make()
                ->title('Pedido não encontrado.')
                ->warning()
                ->send();
            $this->pedidoId = null;
            $this->redirect(ForcaVendasMonitorResource::getUrl('index'));

            return;
        }

        try {
            app(ForcaVendasTelaVendaService::class)->assertEditavel($order);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não é possível editar este pedido.')
                ->body($e->getMessage())
                ->warning()
                ->send();
            $this->pedidoId = null;
            $this->redirect(ForcaVendasMonitorResource::getUrl('index'));

            return;
        }

        $pedido = $order->pedido;
        $payload = is_array($order->payload) ? $order->payload : [];
        $faltando = [];

        if (! $pedido) {
            Notification::make()
                ->title('DAV do pedido não encontrado.')
                ->danger()
                ->send();
            $this->pedidoId = null;
            $this->redirect(ForcaVendasMonitorResource::getUrl('index'));

            return;
        }

        $this->davNumero = (string) ($pedido->numero ?? '');
        $this->observacoes = (string) ($pedido->observacoes ?? ($payload['observacoes'] ?? ''));
        $this->aplicarFvTransporteFromPayload(is_array($payload['transporte'] ?? null) ? $payload['transporte'] : null);
        // App: Prazo Avulso (condicao_pagamento) tem prioridade sobre tabela_prazo_dias.
        $condicaoRaw = trim((string) ($payload['condicao_pagamento'] ?? ''));
        $prazoRaw = $payload['tabela_prazo_dias'] ?? null;

        if ($condicaoRaw !== '') {
            $this->tabelaPrazoDias = $condicaoRaw;
        } elseif (is_array($prazoRaw)) {
            $this->tabelaPrazoDias = implode(',', array_map('intval', $prazoRaw));
        } else {
            $this->tabelaPrazoDias = $prazoRaw !== null && $prazoRaw !== '' ? (string) $prazoRaw : null;
        }

        if ($pedido->data) {
            try {
                $this->aberturaData = \Carbon\Carbon::parse($pedido->data)->format('d/m/Y');
            } catch (\Throwable) {
                $this->aberturaData = (string) $pedido->data;
            }
        }

        if ($pedido->hora) {
            $this->aberturaHora = (string) $pedido->hora;
        }

        // Cliente
        $cliente = $order->cliente ?? ($order->cliente_id ? Person::query()->find($order->cliente_id) : null);

        if ($cliente) {
            $this->aplicarCliente($cliente);
            $this->clienteBusca = (string) ($cliente->nome_razao ?? '');
        } else {
            $faltando[] = 'Cliente';
        }

        // Vendedor (+ estoque + caixa amarrado)
        $vendedor = $order->vendedor;

        if ($vendedor) {
            $this->aplicarVendedor($vendedor, permitirHistorico: true);
        } elseif ($order->vendedor_id) {
            $faltando[] = 'Vendedor';
        } else {
            $this->carregarVendedorPadrao();
        }

        // Caixa/estoque salvos no pedido têm prioridade sobre o padrão do vendedor.
        if (filled($payload['caixa_conta_id'] ?? null)) {
            $caixa = CaixaConta::query()->find((int) $payload['caixa_conta_id']);

            if ($caixa) {
                $this->caixaId = (int) $caixa->id;
                $this->caixaLabel = trim(($caixa->codigo ? $caixa->codigo.' - ' : '').($caixa->nome ?? ''));
            }
        }

        if (filled($payload['estoque_id'] ?? null)) {
            $estoque = Estoque::query()->find((int) $payload['estoque_id']);

            if ($estoque) {
                $this->estoqueId = (int) $estoque->id;
                $this->estoqueLabel = $estoque->label();
            }
        }

        // Itens (acréscimo fica no payload — linha do pedido não tem a coluna)
        $payloadItens = is_array($payload['itens'] ?? null) ? $payload['itens'] : [];
        $payloadFila = [];
        foreach ($payloadItens as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $chave = ((int) ($raw['product_id'] ?? 0)).':'
                .(filled($raw['product_grade_id'] ?? null) ? (int) $raw['product_grade_id'] : 0);
            $payloadFila[$chave][] = $raw;
        }

        $this->itens = $pedido->itens
            ->sortBy(fn ($item) => (int) ($item->item ?? 0))
            ->values()
            ->map(function ($item) use (&$payloadFila): array {
                /** @var PedidoItem $item */
                $product = $item->product;
                $chave = ((int) $item->product_id).':'
                    .(filled($item->product_grade_id) ? (int) $item->product_grade_id : 0);
                $payloadItem = [];
                if (! empty($payloadFila[$chave])) {
                    $payloadItem = array_shift($payloadFila[$chave]) ?? [];
                }

                $qtd = (float) $item->quantidade;
                $desc = (float) $item->desconto;
                $acr = (float) ($payloadItem['acrescimo'] ?? 0);
                $precoPedido = (float) $item->preco_unitario;

                // Na linha do pedido o acréscimo pode estar embutido no unitário; separa de novo para a tela.
                if ($acr > 0.0001 && $qtd > 0) {
                    $precoPayload = isset($payloadItem['preco_unitario'])
                        ? (float) $payloadItem['preco_unitario']
                        : null;
                    // Payload antigo gravava o unitário já com acréscimo; payload novo guarda o da tabela.
                    if ($precoPayload !== null && abs($precoPayload - $precoPedido) > 0.005) {
                        $preco = $precoPayload;
                    } else {
                        $preco = round($precoPedido - ($acr / $qtd), 2);
                    }
                } else {
                    $preco = $precoPedido;
                }

                return [
                    'key' => uniqid('i', true),
                    'product_id' => (int) $item->product_id,
                    'product_grade_id' => $item->product_grade_id ? (int) $item->product_grade_id : null,
                    'codigo' => (string) ($product?->codigo ?? ''),
                    'descricao' => (string) ($item->descricao ?: ($product?->descricao ?? 'Item')),
                    'quantidade' => $qtd,
                    'preco_unitario' => $preco,
                    'acrescimo' => $acr,
                    'desconto' => $desc,
                    'total' => round(($qtd * $preco) + $acr - $desc, 2),
                    'foto' => $product?->fotoUrl(),
                ];
            })->values()->all();

        // Fallback: itens só no payload (pedido antigo / sync incompleto)
        if ($this->itens === [] && $payloadItens !== []) {
            foreach ($payloadItens as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                $productId = (int) ($raw['product_id'] ?? 0);
                $product = $productId > 0 ? Product::query()->find($productId) : null;
                $qtd = (float) ($raw['quantidade'] ?? 0);
                $preco = (float) ($raw['preco_unitario'] ?? 0);
                $desc = (float) ($raw['desconto'] ?? 0);
                $acr = (float) ($raw['acrescimo'] ?? 0);

                $this->itens[] = [
                    'key' => uniqid('i', true),
                    'product_id' => $productId,
                    'product_grade_id' => isset($raw['product_grade_id']) ? (int) $raw['product_grade_id'] : null,
                    'codigo' => (string) ($product?->codigo ?? ($raw['codigo'] ?? '')),
                    'descricao' => (string) ($raw['descricao'] ?? $product?->descricao ?? 'Item'),
                    'quantidade' => $qtd,
                    'preco_unitario' => $preco,
                    'acrescimo' => $acr,
                    'desconto' => $desc,
                    'total' => round(($qtd * $preco) + $acr - $desc, 2),
                    'foto' => $product?->fotoUrl(),
                ];
            }
        }

        if ($this->itens === []) {
            $faltando[] = 'Itens';
        }

        // Descontos do pedido (acréscimo fica zerado — se já veio rateado nos itens, não duplica)
        $descValor = (float) ($pedido->desconto_valor ?? ($payload['desconto_valor'] ?? 0));
        $descPct = (float) ($pedido->percentual_desconto ?? ($payload['percentual_desconto'] ?? 0));
        $this->descontoPedidoValor = $this->formatMoney($descValor);
        $this->descontoPedidoPct = $this->formatMoney($descPct);
        $this->acrescimoPedidoValor = '0,00';
        $this->acrescimoPedidoPct = '0,00';

        // Meios de pagamento
        $this->carregarMeiosPagamento();
        $formaId = filled($payload['forma_pagamento_id'] ?? null) ? (int) $payload['forma_pagamento_id'] : null;
        $formaNome = trim((string) ($payload['forma_pagamento'] ?? $pedido->forma_pagamento ?? ''));

        if (! $formaId && $formaNome !== '') {
            $formaId = (int) (FormaPagamento::query()
                ->where(function ($q) use ($formaNome): void {
                    $q->where('descricao', $formaNome)
                        ->orWhereRaw('LOWER(descricao) = ?', [mb_strtolower($formaNome, 'UTF-8')]);
                })
                ->value('id') ?? 0) ?: null;
        }

        $totalLiquido = $this->totalLiquido();

        if ($formaId) {
            $this->formaSelecionadaId = $formaId;
            $encontrou = false;

            foreach ($this->meiosPagamento as $i => $meio) {
                if ((int) $meio['id'] === $formaId) {
                    $this->meiosPagamento[$i]['valor'] = ErpMoney::formatBr($totalLiquido);
                    $this->selectedPagamentoIndex = $i;
                    $encontrou = true;
                    break;
                }
            }

            if (! $encontrou) {
                $faltando[] = 'Meio de pagamento (cadastro não encontrado na lista)';
            }
        } elseif ($formaNome !== '') {
            $faltando[] = 'Meio de pagamento (não vinculado ao cadastro)';
        } else {
            $faltando[] = 'Meio de pagamento';
        }

        $this->valorPagamento = $this->formatMoney($this->valorRestante());

        // Limitações de UI (não são dados faltando — só aviso curto)
        $limitacoesUi = [];

        if (filled($payload['convenio_id'] ?? null) || filled($payload['convenio'] ?? null)) {
            $limitacoesUi[] = 'convênio';
        }

        if (filled($payload['propriedade_id'] ?? null) || filled($payload['propriedade'] ?? null)) {
            $limitacoesUi[] = 'propriedade';
        }

        if (filled($payload['local_estoque_id'] ?? null) || filled($payload['tabela_preco_id'] ?? null)) {
            $limitacoesUi[] = 'estoque/tabela';
        }

        $temGrade = collect($this->itens)->contains(fn (array $i): bool => ! empty($i['product_grade_id']));

        if ($temGrade) {
            $limitacoesUi[] = 'grade';
        }

        if (filled($this->tabelaPrazoDias)) {
            $limitacoesUi[] = 'prazos de parcela';
        }

        ErpScreen::set('Tela de Venda — DAV '.$this->davNumero);

        if ($faltando !== []) {
            Notification::make()
                ->title('Pedido carregado com avisos')
                ->body('DAV '.$this->davNumero.' — faltando: '.implode(', ', $faltando).'.')
                ->warning()
                ->send();
        } else {
            $body = 'DAV '.$this->davNumero.' pronto para edição.';

            if ($limitacoesUi !== []) {
                $body .= ' Mantidos sem edição nesta tela: '.implode(', ', $limitacoesUi).'.';
            }

            Notification::make()
                ->title('Pedido carregado')
                ->body($body)
                ->success()
                ->duration(4500)
                ->send();
        }
    }

    private function carregarClientePadrao(): void
    {
        $person = Person::resolveConsumidorFinal();
        $this->aplicarCliente($person);
        $this->clienteBusca = $this->formatarClienteBusca($person);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Person>  $people
     * @return list<array{id: int, codigo: string, nome: string, cpf_cnpj: string, doc_tipo: string, limite: string, utilizado: string, vencidas: string, tem_vencidas: bool}>
     */
    private function montarSugestoesCliente($people): array
    {
        if ($people->isEmpty()) {
            return [];
        }

        $ids = $people->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $hoje = ErpTimezone::toLocal()->toDateString();

        $abertos = ContaReceber::query()
            ->whereIn('cliente_id', $ids)
            ->where('saldo', '>', 0)
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, SUM(saldo) as total')
            ->pluck('total', 'cliente_id');

        $vencidos = ContaReceber::query()
            ->whereIn('cliente_id', $ids)
            ->where('saldo', '>', 0)
            ->whereDate('vencimento', '<', $hoje)
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, SUM(saldo) as total')
            ->pluck('total', 'cliente_id');

        return $people->map(function (Person $p) use ($abertos, $vencidos): array {
            $id = (int) $p->id;
            $limite = round((float) ($p->limite_credito ?? 0), 2);
            $utilizado = round((float) ($abertos[$id] ?? 0), 2);
            $vencidas = round((float) ($vencidos[$id] ?? 0), 2);
            $doc = (string) ($p->cpf_cnpj ?? '');
            $digits = preg_replace('/\D+/', '', $doc) ?? '';
            $docTipo = strlen($digits) === 14 ? 'cnpj' : (strlen($digits) === 11 ? 'cpf' : '');

            return [
                'id' => $id,
                'codigo' => (string) ($p->codigo ?? ''),
                'nome' => (string) ($p->nome_razao ?? ''),
                'cpf_cnpj' => $doc,
                'doc_tipo' => $docTipo,
                'limite' => ErpMoney::formatBr($limite),
                'utilizado' => ErpMoney::formatBr($utilizado),
                'vencidas' => ErpMoney::formatBr($vencidas),
                'tem_vencidas' => $vencidas > 0,
            ];
        })->values()->all();
    }

    private function formatarClienteBusca(Person $person): string
    {
        $nome = trim((string) ($person->nome_razao ?? ''));

        if (Person::isCodigoConsumidorFinal($person->codigo)) {
            return $nome !== '' ? $nome : 'CONSUMIDOR FINAL';
        }

        return trim(($person->codigo ? $person->codigo.' — ' : '').$nome);
    }

    private function ehTextoConsumidorFinal(string $term): bool
    {
        $norm = mb_strtoupper(trim($term));
        $norm = preg_replace('/\s+/', ' ', $norm) ?: $norm;

        if (in_array($norm, ['CONSUMIDOR FINAL', 'CONSUMIDOR', 'CF', '000001'], true)) {
            return true;
        }

        return Person::isCodigoConsumidorFinal($norm);
    }

    private function clienteJaSelecionadoCorresponde(string $term): bool
    {
        if (! $this->clienteId) {
            return false;
        }

        $termNorm = mb_strtoupper(trim($term));
        $nomeNorm = mb_strtoupper(trim((string) $this->clienteNome));
        $codigoNorm = mb_strtoupper(trim((string) $this->clienteCodigo));
        $formatado = mb_strtoupper(trim(
            ($this->clienteCodigo ? $this->clienteCodigo.' — ' : '').$this->clienteNome
        ));

        return $termNorm === $nomeNorm
            || $termNorm === $codigoNorm
            || $termNorm === $formatado
            || ($this->ehTextoConsumidorFinal($term) && Person::isCodigoConsumidorFinal($this->clienteCodigo));
    }

    private function carregarOpcoesOperacao(): void
    {
        $empresaId = ErpContext::currentEmpresaId();

        // Só operadores com RH (evita órfãos legados em vendedores). Value = vendedores.id.
        $vendedoresQuery = Vendedor::query()
            ->where('ativo', true)
            ->where('efetua_venda', true)
            ->whereHas('rhFuncionario')
            ->with('rhFuncionario');

        // Escopo da empresa atual via pivot empresa_vendedor.
        if ($empresaId) {
            $vendedoresQuery->whereHas(
                'empresas',
                fn ($q) => $q->where('empresas.id', (int) $empresaId)
            );
        }

        $this->vendedorOpcoes = $vendedoresQuery
            ->get(['id', 'codigo', 'nome'])
            ->sortBy(fn (Vendedor $v): int => (int) preg_replace('/\D/', '', (string) ($v->rhFuncionario?->codigo ?? '0')))
            ->values()
            ->map(function (Vendedor $v): array {
                $rh = $v->rhFuncionario;
                $codigo = trim((string) ($rh?->codigo ?? ''));
                $nome = trim((string) ($rh?->nome ?? $v->nome ?? ''));

                return [
                    'id' => (int) $v->id,
                    'label' => trim(($codigo !== '' ? $codigo.' - ' : '').$nome),
                ];
            })
            ->all();

        $this->caixaOpcoes = CaixaConta::query()
            ->where('ativo', true)
            ->whereIn('tipo', [CaixaConta::TIPO_PDV, CaixaConta::TIPO_SUBCAIXA])
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome', 'situacao'])
            ->map(fn (CaixaConta $c): array => [
                'id' => (int) $c->id,
                'label' => trim(($c->codigo ? $c->codigo.' - ' : '').($c->nome ?? '')),
                'situacao' => (string) $c->situacao,
            ])
            ->all();

        $this->tabelaPrecoOpcoes = PriceTable::query()
            ->where('ativo', true)
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->orderBy('descricao')
            ->get(['id', 'codigo', 'descricao'])
            ->map(fn (PriceTable $t): array => [
                'id' => (int) $t->id,
                'label' => trim(($t->codigo ? $t->codigo.' - ' : '').($t->descricao ?? '')),
            ])
            ->all();
    }

    /**
     * Tabela de preço sempre vem do cliente (prioridade) ou do vendedor.
     */
    private function carregarTabelaPrecoPadrao(): void
    {
        // 1º Cliente selecionado.
        if ($this->clienteId) {
            $tabelaCliente = (int) (Person::query()->whereKey($this->clienteId)->value('price_table_id') ?? 0);

            if ($tabelaCliente > 0 && $this->aplicarTabelaPreco($tabelaCliente)) {
                return;
            }
        }

        // 2º Vendedor logado / selecionado.
        if ($this->vendedorId) {
            $tabelaVendedor = (int) (Vendedor::query()->whereKey($this->vendedorId)->value('tabela_venda_id') ?? 0);

            if ($tabelaVendedor > 0 && $this->aplicarTabelaPreco($tabelaVendedor)) {
                return;
            }
        }

        $this->tabelaPrecoId = null;
        $this->tabelaPrecoLabel = '';
    }

    private function aplicarTabelaPreco(int $id): bool
    {
        $tabela = PriceTable::query()->where('ativo', true)->find($id);

        if (! $tabela) {
            return false;
        }

        $this->tabelaPrecoId = (int) $tabela->id;
        $this->tabelaPrecoLabel = trim(($tabela->codigo ? $tabela->codigo.' - ' : '').($tabela->descricao ?? ''));

        $existe = collect($this->tabelaPrecoOpcoes)->contains(
            fn (array $op): bool => (int) ($op['id'] ?? 0) === $this->tabelaPrecoId
        );

        if (! $existe) {
            array_unshift($this->tabelaPrecoOpcoes, [
                'id' => $this->tabelaPrecoId,
                'label' => $this->tabelaPrecoLabel,
            ]);
        }

        return true;
    }

    public function updatedTabelaPrecoId(mixed $value): void
    {
        // Combo Alpine usa fluxo item a item com barra de progresso.
        if ($this->tabelaProgressOpen) {
            return;
        }

        $this->iniciarAtualizacaoTabelaPreco((int) $value);
        $total = count($this->itens);

        for ($i = 0; $i < $total; $i++) {
            $this->aplicarPrecoItemNaGrid($i);
        }

        $this->finalizarAtualizacaoTabelaPreco($this->tabelaRecalcAlterados);
    }

    /**
     * Inicia troca de tabela e prepara o recálculo item a item.
     *
     * @return array{ok: bool, total: int, label: string}
     */
    public function iniciarAtualizacaoTabelaPreco(int $id): array
    {
        $this->tabelaProgressOpen = true;
        $this->tabelaRecalcAlterados = 0;

        if ($id <= 0) {
            $this->tabelaPrecoId = null;
            $this->tabelaPrecoLabel = '';
        } elseif (! $this->aplicarTabelaPreco($id)) {
            $this->tabelaProgressOpen = false;

            Notification::make()->title('Tabela de preço não encontrada.')->warning()->send();

            return [
                'ok' => false,
                'total' => 0,
                'label' => '',
            ];
        }

        return [
            'ok' => true,
            'total' => count($this->itens),
            'label' => $this->tabelaPrecoLabel !== '' ? $this->tabelaPrecoLabel : 'preço padrão',
        ];
    }

    /**
     * Aplica o preço da tabela em um único item da grid (para progresso 1 a 1).
     *
     * @return array{done: bool, atual: int, total: int, pct: int, alterado: bool, descricao: string, preco: string}
     */
    public function aplicarPrecoItemNaGrid(int $index): array
    {
        $total = count($this->itens);

        if ($total === 0 || $index < 0 || $index >= $total) {
            return [
                'done' => true,
                'atual' => $total,
                'total' => $total,
                'pct' => 100,
                'alterado' => false,
                'descricao' => '',
                'preco' => '',
            ];
        }

        $itens = $this->itens;
        $item = $itens[$index];
        $productId = (int) ($item['product_id'] ?? 0);
        $product = Product::query()->find($productId, [
            'id', 'preco_venda', 'preco_atacado', 'preco_especial',
            'qtd_atacado', 'usa_tab_preco', 'promo_preco_venda',
        ]);

        $qtd = (float) ($item['quantidade'] ?? 0);
        $acrescimo = (float) ($item['acrescimo'] ?? 0);
        $desconto = (float) ($item['desconto'] ?? 0);
        $precoAtual = round((float) ($item['preco_unitario'] ?? 0), 2);
        $novoPreco = $product
            ? $this->precoProdutoNaTabela($product, $qtd > 0 ? $qtd : 1)
            : $precoAtual;

        if ($precoAtual !== $novoPreco) {
            $this->tabelaRecalcAlterados++;
        }

        $itens[$index]['preco_unitario'] = $novoPreco;
        $itens[$index]['total'] = round(($qtd * $novoPreco) + $acrescimo - $desconto, 2);
        $this->itens = array_values($itens);

        $atual = $index + 1;

        return [
            'done' => $atual >= $total,
            'atual' => $atual,
            'total' => $total,
            'pct' => (int) round(($atual / max(1, $total)) * 100),
            'alterado' => $precoAtual !== $novoPreco,
            'descricao' => (string) ($item['descricao'] ?? ''),
            'preco' => $this->formatMoney($novoPreco),
        ];
    }

    public function finalizarAtualizacaoTabelaPreco(int $alterados = 0): void
    {
        $this->atualizarPrecoFormularioAtual();

        $label = $this->tabelaPrecoLabel !== '' ? $this->tabelaPrecoLabel : 'preço padrão';
        $alterados = $alterados > 0 ? $alterados : $this->tabelaRecalcAlterados;

        Notification::make()
            ->title($alterados > 0
                ? "Tabela {$label} — {$alterados} item(ns) atualizado(s)."
                : "Tabela {$label} aplicada.")
            ->success()
            ->duration(2800)
            ->send();

        $this->tabelaProgressOpen = false;
        $this->tabelaRecalcAlterados = 0;
    }

    /**
     * Compat: recalcula a grid inteira de uma vez.
     *
     * @return array{ok: bool, alterados: int, total: int, label: string}
     */
    public function aplicarTabelaPrecoNaGrid(int $id): array
    {
        $start = $this->iniciarAtualizacaoTabelaPreco($id);

        if (! ($start['ok'] ?? false)) {
            return [
                'ok' => false,
                'alterados' => 0,
                'total' => 0,
                'label' => '',
            ];
        }

        $total = (int) $start['total'];

        for ($i = 0; $i < $total; $i++) {
            $this->aplicarPrecoItemNaGrid($i);
        }

        $alterados = $this->tabelaRecalcAlterados;
        $this->finalizarAtualizacaoTabelaPreco($alterados);

        return [
            'ok' => true,
            'alterados' => $alterados,
            'total' => $total,
            'label' => (string) ($start['label'] ?? ''),
        ];
    }

    /**
     * Preço do produto na tabela selecionada — mesmo critério do app Força de Vendas:
     * VAREJO → preco_venda, ATACADO → preco_atacado, ESPECIAL → preco_especial,
     * com overlay de product_price_table_items quando usa_tab_preco.
     */
    private function precoNaTabelaSelecionada(Product $product): ?float
    {
        return $this->precoProdutoNaTabela($product);
    }

    private function precoProdutoNaTabela(Product $product, float $quantidade = 1): float
    {
        $nivel = $this->nivelTabelaPrecoSelecionada();
        $base = $this->precoBasePorNivel($product, $nivel, $quantidade);

        if (! $this->tabelaPrecoId || ! ($product->usa_tab_preco ?? false)) {
            return $base;
        }

        $item = ProductPriceTableItem::query()
            ->where('product_id', $product->id)
            ->where('price_table_id', $this->tabelaPrecoId)
            ->first();

        if (! $item) {
            return $base;
        }

        $valor = (float) ($item->valor ?? 0);

        if ($valor > 0) {
            return round($valor, 2);
        }

        $fator = (float) ($item->fator ?? 0);

        if ($fator > 0) {
            return round($base * $fator, 2);
        }

        return $base;
    }

    /**
     * @return 'varejo'|'atacado'|'especial'
     */
    private function nivelTabelaPrecoSelecionada(): string
    {
        if (! $this->tabelaPrecoId) {
            return 'varejo';
        }

        $tabela = PriceTable::query()->find($this->tabelaPrecoId, ['id', 'codigo', 'descricao']);

        if (! $tabela) {
            return 'varejo';
        }

        $blob = mb_strtoupper(trim((string) ($tabela->codigo ?? '').' '.(string) ($tabela->descricao ?? '')));

        if (str_contains($blob, 'ESPECIAL')) {
            return 'especial';
        }

        if (str_contains($blob, 'ATACADO')) {
            return 'atacado';
        }

        if (
            str_contains($blob, 'VAREJO')
            || str_contains($blob, 'PADRAO')
            || str_contains($blob, 'PADRÃO')
            || (string) ($tabela->codigo ?? '') === '1'
        ) {
            return 'varejo';
        }

        return 'varejo';
    }

    private function precoBasePorNivel(Product $product, string $nivel, float $quantidade = 1): float
    {
        $varejo = $this->precoVarejoProduto($product);

        return match ($nivel) {
            'atacado' => $this->precoAtacadoProduto($product, $varejo),
            'especial' => $this->precoEspecialProduto($product, $varejo),
            default => $this->precoVarejoComAtacadoPorQtd($product, $varejo, $quantidade),
        };
    }

    private function precoVarejoProduto(Product $product): float
    {
        $campanha = app(\App\Support\Erp\Promocao\PromocaoPrecoService::class)
            ->precoVarejoAtivo($product);

        if ($campanha !== null) {
            return $campanha;
        }

        $hoje = \Illuminate\Support\Carbon::today();
        $inicio = $product->promo_data_inicio;
        $fim = $product->promo_data_fim;
        $promo = (float) ($product->promo_preco_venda ?? 0);

        if ($promo > 0 && filled($inicio) && filled($fim) && $hoje->between(
            \Illuminate\Support\Carbon::parse($inicio)->startOfDay(),
            \Illuminate\Support\Carbon::parse($fim)->endOfDay(),
        )) {
            return round($promo, 2);
        }

        return round((float) ($product->preco_venda ?? 0), 2);
    }

    private function precoAtacadoProduto(Product $product, ?float $varejo = null): float
    {
        $atacado = (float) ($product->preco_atacado ?? 0);

        if ($atacado > 0) {
            return round($atacado, 2);
        }

        return $varejo ?? $this->precoVarejoProduto($product);
    }

    private function precoEspecialProduto(Product $product, ?float $varejo = null): float
    {
        $especial = (float) ($product->preco_especial ?? 0);

        if ($especial > 0) {
            return round($especial, 2);
        }

        return $varejo ?? $this->precoVarejoProduto($product);
    }

    private function precoVarejoComAtacadoPorQtd(Product $product, float $varejo, float $quantidade): float
    {
        $qtdAtacado = (float) ($product->qtd_atacado ?? 0);
        $atacado = (float) ($product->preco_atacado ?? 0);

        if ($qtdAtacado > 0 && $atacado > 0 && $quantidade >= $qtdAtacado) {
            return round($atacado, 2);
        }

        return $varejo;
    }

    private function atualizarPrecoFormularioAtual(): void
    {
        if (! $this->produtoAtualId) {
            return;
        }

        $product = Product::query()->find($this->produtoAtualId);

        if (! $product) {
            return;
        }

        $qtd = $this->parseDecimal($this->quantidade);
        $this->precoUnitario = $this->formatMoney(
            $this->precoProdutoNaTabela($product, $qtd > 0 ? $qtd : 1)
        );
        $this->recalcularTotalItem();
    }

    private function carregarVendedorPadrao(): void
    {
        $user = Auth::user();

        if (! $user) {
            $this->vendedorId = null;
            $this->vendedorLabel = '';
            $this->estoqueId = null;
            $this->estoqueLabel = '';
            $this->caixaId = null;
            $this->caixaLabel = '';

            return;
        }

        // 1º: operador/vendedor vinculado ao usuário logado (mesmo fluxo do PDV).
        $vendedor = $user->relationLoaded('vendedor')
            ? $user->vendedor
            : $user->vendedor()->first();

        if ($vendedor && $this->vendedorOperacionalValido($vendedor)) {
            $this->aplicarVendedor($vendedor);

            return;
        }

        // 2º: sessão do PDV (se o operador já estava ativo neste terminal).
        $sessionVendedorId = (int) (session('erp.pdv.vendedor_id') ?? 0);

        if ($sessionVendedorId > 0) {
            $vendedor = Vendedor::query()
                ->with('rhFuncionario')
                ->where('ativo', true)
                ->find($sessionVendedorId);

            if ($vendedor && $this->vendedorOperacionalValido($vendedor)) {
                $this->aplicarVendedor($vendedor);

                return;
            }
        }

        // Sem vínculo válido (ativo + efetua_venda + RH + empresa): não chuta legado/órfão.
        $this->vendedorId = null;
        $this->vendedorLabel = trim((string) ($user->name ?? ''));
        $this->estoqueId = null;
        $this->estoqueLabel = '';
        $this->caixaId = null;
        $this->caixaLabel = '';
    }

    /**
     * Operador válido para NOVA venda (combo / padrão).
     */
    private function vendedorOperacionalValido(Vendedor $vendedor): bool
    {
        if (! $vendedor->ativo || ! $vendedor->efetua_venda) {
            return false;
        }

        $vendedor->loadMissing('rhFuncionario');

        if (! $vendedor->rhFuncionario) {
            return false;
        }

        $empresaId = ErpContext::currentEmpresaId();

        if (! $empresaId) {
            return true;
        }

        if ((int) ($vendedor->empresa_id ?? 0) === (int) $empresaId) {
            return true;
        }

        return $vendedor->empresas()->where('empresas.id', (int) $empresaId)->exists();
    }

    private function aplicarVendedor(Vendedor $vendedor, bool $permitirHistorico = false): void
    {
        $vendedor->loadMissing('rhFuncionario');
        $rh = $vendedor->rhFuncionario;
        $codigo = trim((string) ($rh?->codigo ?? $vendedor->codigo ?? ''));
        $nome = trim((string) ($rh?->nome ?? $vendedor->nome ?? ''));

        $this->vendedorId = (int) $vendedor->id;
        $this->vendedorLabel = trim(($codigo !== '' ? $codigo.' - ' : '').$nome);

        $valido = $this->vendedorOperacionalValido($vendedor);
        $podeInserirNaLista = $valido || $permitirHistorico;

        // Não reinserir órfão em operação nova; histórico (pedido em edição) pode aparecer.
        if ($podeInserirNaLista) {
            $existe = collect($this->vendedorOpcoes)->contains(
                fn (array $op): bool => (int) ($op['id'] ?? 0) === $this->vendedorId
            );

            if (! $existe) {
                array_unshift($this->vendedorOpcoes, [
                    'id' => $this->vendedorId,
                    'label' => $this->vendedorLabel !== '' ? $this->vendedorLabel : (string) $vendedor->nome,
                ]);
            }
        }

        $this->carregarEstoqueDoVendedor($vendedor);
        $this->carregarCaixaDoVendedor($vendedor);
        $this->carregarTabelaPrecoPadrao();
    }

    private function carregarEstoqueDoVendedor(Vendedor $vendedor): void
    {
        $estoque = $vendedor->estoque_id ? Estoque::query()->find($vendedor->estoque_id) : null;

        if ($estoque) {
            $this->estoqueId = (int) $estoque->id;
            $this->estoqueLabel = $estoque->label();
        } else {
            $this->estoqueId = null;
            $this->estoqueLabel = '';
        }
    }

    private function estoqueOperacionalDefinido(): bool
    {
        return $this->estoqueId !== null && $this->estoqueId > 0;
    }

    private function caixaOperacionalDefinido(): bool
    {
        return $this->caixaId !== null && $this->caixaId > 0;
    }

    private function bloquearSeOperacaoInvalida(bool $exigeEstoque = true): bool
    {
        if (! $this->caixaOperacionalDefinido()) {
            $this->notificarCaixaNaoDefinido();

            return true;
        }

        if ($exigeEstoque && ! $this->estoqueOperacionalDefinido()) {
            $this->notificarEstoqueNaoDefinido();

            return true;
        }

        return false;
    }

    private function pedidoExigeEstoque(): bool
    {
        foreach ($this->itens as $item) {
            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $isServico = Product::query()->whereKey($productId)->value('is_servico');

            if (! $isServico) {
                return true;
            }
        }

        return false;
    }

    private function notificarEstoqueNaoDefinido(): void
    {
        $nome = trim($this->vendedorLabel) !== '' ? trim($this->vendedorLabel) : 'vendedor selecionado';

        Notification::make()
            ->title('Depósito de estoque não definido')
            ->body(
                'O vendedor '.$nome.' não possui estoque vinculado. '
                .'Configure em Cadastros → Vendedores antes de lançar produtos.'
            )
            ->warning()
            ->send();
    }

    private function notificarCaixaNaoDefinido(): void
    {
        $nome = trim($this->vendedorLabel) !== '' ? trim($this->vendedorLabel) : 'vendedor selecionado';

        Notification::make()
            ->title('Caixa não definido')
            ->body(
                'O vendedor '.$nome.' não possui caixa vinculado. '
                .'Configure em Cadastros → Vendedores (ou Permissões do usuário) antes de vender.'
            )
            ->warning()
            ->send();
    }

    private function carregarCaixaDoVendedor(Vendedor $vendedor): void
    {
        // Empresa ativa no ERP (sessão) tem prioridade sobre o cadastro fixo do usuário.
        $empresaId = ErpContext::currentEmpresaId()
            ?: ($vendedor->empresa_id ? (int) $vendedor->empresa_id : null)
            ?: ((int) (Auth::user()?->empresa_id ?? 0) ?: null);

        $caixa = $empresaId ? $vendedor->caixaContaDaEmpresa((int) $empresaId) : null;

        // Fallback: primeiro caixa amarrado em qualquer empresa do colaborador.
        if (! $caixa) {
            $vendedor->loadMissing('empresas');

            foreach ($vendedor->empresas as $empresa) {
                $caixaId = (int) ($empresa->pivot?->caixa_conta_id ?? 0);

                if ($caixaId <= 0) {
                    continue;
                }

                $caixa = CaixaConta::query()->find($caixaId);

                if ($caixa) {
                    break;
                }
            }
        }

        if ($caixa) {
            $this->caixaId = (int) $caixa->id;
            $this->caixaLabel = trim(($caixa->codigo ? $caixa->codigo.' - ' : '').($caixa->nome ?? ''));

            $existe = collect($this->caixaOpcoes)->contains(
                fn (array $op): bool => (int) ($op['id'] ?? 0) === $this->caixaId
            );

            if (! $existe) {
                array_unshift($this->caixaOpcoes, [
                    'id' => $this->caixaId,
                    'label' => $this->caixaLabel !== '' ? $this->caixaLabel : (string) $caixa->nome,
                    'situacao' => (string) ($caixa->situacao ?? ''),
                ]);
            }
        } else {
            $this->caixaId = null;
            $this->caixaLabel = '';
        }
    }

    public function updatedVendedorId(mixed $value): void
    {
        $id = (int) $value;

        if ($id <= 0) {
            return;
        }

        $vendedor = Vendedor::query()->find($id);

        if (! $vendedor) {
            Notification::make()->title('Vendedor não encontrado.')->warning()->send();

            return;
        }

        // Ao trocar de vendedor, o caixa amarrado é sugerido de novo.
        $this->caixaId = null;
        $this->caixaLabel = '';
        $this->aplicarVendedor($vendedor);

        if (! $this->caixaOperacionalDefinido()) {
            $this->notificarCaixaNaoDefinido();
        }

        if (! $this->estoqueOperacionalDefinido()) {
            $this->notificarEstoqueNaoDefinido();
        }
    }

    public function updatedCaixaId(mixed $value): void
    {
        $id = (int) $value;

        if ($id <= 0) {
            $this->caixaId = null;
            $this->caixaLabel = '';

            return;
        }

        $caixa = CaixaConta::query()->find($id);

        if (! $caixa) {
            Notification::make()->title('Caixa não encontrado.')->warning()->send();

            return;
        }

        $this->caixaId = (int) $caixa->id;
        $this->caixaLabel = trim(($caixa->codigo ? $caixa->codigo.' - ' : '').($caixa->nome ?? ''));
    }

    private function carregarMeiosPagamento(): void
    {
        $formas = FormaPagamento::query()
            ->where('ativo', true)
            ->where(function ($q): void {
                $q->where('disponivel_mobile', true)
                    ->orWhere('aparece_venda', true);
            })
            ->orderBy('codigo')
            ->orderBy('id')
            ->get();

        if ($formas->isEmpty()) {
            $formas = FormaPagamento::query()->where('ativo', true)->orderBy('codigo')->orderBy('id')->get();
        }

        // Ordem visual do PDV: Dinheiro → PIX → Débito → Crédito → Crediário → demais
        $formas = $formas->sortBy(function (FormaPagamento $f): array {
            return [$this->ordemFormaPagamento($f), (int) ($f->codigo ?? 0), (int) $f->id];
        })->values();

        $existentes = collect($this->meiosPagamento)->keyBy('id');
        $usados = [];

        $this->meiosPagamento = $formas->map(function (FormaPagamento $f) use ($existentes, &$usados): array {
            $id = (int) $f->id;
            $valorExistente = $existentes[$id]['valor'] ?? '0,00';
            $valor = is_numeric($valorExistente)
                ? ErpMoney::formatBr((float) $valorExistente)
                : ErpMoney::formatBr(ErpMoney::parseBr($valorExistente));

            $descricao = (string) ($f->descricao ?? '');
            $atalho = $this->resolveAtalhoForma((string) ($f->atalho ?? ''), $descricao, $usados);

            return [
                'id' => $id,
                'descricao' => $descricao,
                'forma' => $descricao,
                'atalho' => $atalho,
                'valor' => $valor,
                'tipo' => (string) ($f->tipo ?? ''),
                'tipo_movimento' => (string) ($f->tipo_movimento ?? ''),
                'aparece_contas_receber' => (bool) ($f->aparece_contas_receber ?? false),
                'max_parcelas' => max(1, (int) ($f->max_parcelas ?: 12)),
                'prazo_cartao' => max(0, (int) ($f->prazo_cartao ?: 0)),
                'intervalo_parcelas' => max(0, (int) ($f->intervalo_parcelas ?: 30)),
            ];
        })->all();
    }

    private function ordemFormaPagamento(FormaPagamento $forma): int
    {
        $tipo = strtolower(trim((string) ($forma->tipo ?? '')));
        $desc = mb_strtoupper(trim((string) ($forma->descricao ?? '')), 'UTF-8');

        return match (true) {
            $tipo === 'dinheiro', str_contains($desc, 'DINHEIRO') => 10,
            $tipo === 'pix', str_contains($desc, 'PIX') => 20,
            $tipo === 'cartao_debito',
            str_contains($desc, 'DÉBITO'),
            str_contains($desc, 'DEBITO') => 30,
            $tipo === 'cartao_credito',
            str_contains($desc, 'CRÉDITO'),
            str_contains($desc, 'CREDITO') => 40,
            $tipo === 'crediario',
            str_contains($desc, 'CREDIÁRIO'),
            str_contains($desc, 'CREDIARIO') => 50,
            default => 100,
        };
    }

    /**
     * @param  array<string, true>  $usados
     */
    private function resolveAtalhoForma(string $atalho, string $descricao, array &$usados): string
    {
        $atalho = mb_strtoupper(trim($atalho), 'UTF-8');

        if ($atalho !== '' && mb_strlen($atalho, 'UTF-8') === 1 && ! isset($usados[$atalho])) {
            $usados[$atalho] = true;

            return $atalho;
        }

        $desc = mb_strtoupper(trim($descricao), 'UTF-8');
        $preferido = match (true) {
            str_contains($desc, 'DINHEIRO') => 'A',
            str_contains($desc, 'PIX') => 'P',
            str_contains($desc, 'DÉBITO'), str_contains($desc, 'DEBITO') => 'D',
            str_contains($desc, 'CRÉDITO'), str_contains($desc, 'CREDITO') => 'C',
            str_contains($desc, 'CREDIÁRIO'), str_contains($desc, 'CREDIARIO') => 'H',
            default => '',
        };

        if ($preferido !== '' && ! isset($usados[$preferido])) {
            $usados[$preferido] = true;

            return $preferido;
        }

        foreach (range('A', 'Z') as $letra) {
            if (! isset($usados[$letra])) {
                $usados[$letra] = true;

                return $letra;
            }
        }

        return 'Z';
    }

    private function indexFormaSelecionada(): int
    {
        if ($this->formaSelecionadaId === null) {
            return 0;
        }

        foreach ($this->meiosPagamento as $index => $meio) {
            if ((int) $meio['id'] === $this->formaSelecionadaId) {
                return $index;
            }
        }

        return 0;
    }

    private function aplicarValorRestanteNaForma(int $index, bool $focus = true): void
    {
        if (! isset($this->meiosPagamento[$index])) {
            return;
        }

        $outros = 0.0;

        foreach ($this->meiosPagamento as $i => $meio) {
            if ($i === $index) {
                continue;
            }

            $outros += ErpMoney::parseBr($meio['valor'] ?? '0');
        }

        $restante = max(0, round($this->totalLiquido() - $outros, 2));
        $this->meiosPagamento[$index]['valor'] = ErpMoney::formatBr($restante);

        if (PdvFinalizarPagamentosHelper::isFormaAPrazoPagamento($this->meiosPagamento[$index] ?? [])) {
            $pagamentos = array_map(static fn (array $m): array => [
                'forma' => (string) $m['descricao'],
                'atalho' => (string) $m['atalho'],
                'valor' => (string) $m['valor'],
                'tipo' => (string) ($m['tipo'] ?? ''),
                'tipo_movimento' => (string) ($m['tipo_movimento'] ?? ''),
            ], $this->meiosPagamento);

            $pagamentos = PdvFinalizarPagamentosHelper::aplicarFormaPrazoExclusiva(
                $pagamentos,
                $index,
                $this->totalLiquido(),
            );

            foreach ($pagamentos as $i => $pagamento) {
                if (isset($this->meiosPagamento[$i])) {
                    $this->meiosPagamento[$i]['valor'] = $pagamento['valor'];
                }
            }
        }

        $this->selectedPagamentoIndex = $index;
        $this->formaSelecionadaId = (int) $this->meiosPagamento[$index]['id'];

        $meio = $this->meiosPagamento[$index];
        $valorFormatado = (string) ($meio['valor'] ?? ErpMoney::formatBr(0));

        if ($focus) {
            $this->dispatch('erp-fv-focus-pagamento', index: $index, valor: $valorFormatado);
        }

        $valor = ErpMoney::parseBr($meio['valor'] ?? '0');

        if ($valor > 0 && PdvFinalizarPagamentosHelper::isFormaCartao($meio)) {
            $this->finalizarCartaoCanhotoConfirmado = false;
            $this->finalizarCartaoParcelasRows = [];
            $this->ensureCartaoCanhoto();

            return;
        }

        if ($valor > 0 && PdvFinalizarPagamentosHelper::precisaParcelasCarne($this->fvPagamentoComoPdv($meio))) {
            // Preserva tabelaPrazoDias negociado (app/pedido); só regenera a grade.
            $this->fvParcelasRows = [];
            $this->ensureFvTabelaPrazoCrediario();
        }
    }

    /**
     * Adaptado do PDV: total do cartão POS na finalização da Força de Vendas.
     */
    public function getFinalizarCartaoTotalValorProperty(): float
    {
        foreach ($this->meiosPagamento as $meio) {
            if (! PdvFinalizarPagamentosHelper::isFormaCartao($meio)) {
                continue;
            }

            $valor = ErpMoney::parseBr($meio['valor'] ?? '0');

            if ($valor > 0) {
                return $valor;
            }
        }

        return 0.0;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolvePagamentoCartaoContasReceber(): ?array
    {
        foreach ($this->meiosPagamento as $meio) {
            if (
                PdvFinalizarPagamentosHelper::isFormaCartao($meio)
                && ErpMoney::parseBr($meio['valor'] ?? '0') > 0
            ) {
                return $meio;
            }
        }

        return null;
    }

    protected function finalizarTemCartaoContasReceberComValor(): bool
    {
        return $this->finalizarCartaoTotalValor > 0;
    }

    protected function notifyPdvError(string $title, ?string $body = null): void
    {
        $n = Notification::make()->title($title)->warning();

        if (filled($body)) {
            $n->body($body);
        }

        $n->send();
    }

    public function cancelFinalizarCartaoCanhoto(): void
    {
        if (! $this->finalizarCartaoCanhotoAberta) {
            return;
        }

        $this->finalizarCartaoCanhotoAberta = false;
        $this->dispatch('erp-fv-focus-pagamento', index: $this->selectedPagamentoIndex ?? 0);
    }

    public function concluirCartaoCanhoto(): void
    {
        if ($this->finalizarCartaoParcelasRows === []) {
            $this->notifyPdvError(
                'Gere as parcelas do cartão.',
                'Informe Qtd. Parcelas + Intervalo e use F2 | Gerar.',
            );

            return;
        }

        $this->finalizarCartaoNsu = mb_strtoupper(trim($this->finalizarCartaoNsu), 'UTF-8');
        $this->finalizarCartaoAutorizacao = mb_strtoupper(trim($this->finalizarCartaoAutorizacao), 'UTF-8');
        $this->finalizarCartaoMaquininha = mb_strtoupper(trim($this->finalizarCartaoMaquininha), 'UTF-8');
        $this->finalizarCartaoBandeira = mb_strtoupper(trim($this->finalizarCartaoBandeira), 'UTF-8');

        $this->finalizarCartaoCanhotoConfirmado = true;
        $this->finalizarCartaoCanhotoAberta = false;

        // Propaga os dias das parcelas para o pedido (prévia do monitor + faturamento).
        $dias = collect($this->finalizarCartaoParcelasRows)
            ->map(fn (array $row): int => (int) ($row['dias'] ?? 0))
            ->values()
            ->all();

        if ($dias !== []) {
            $this->tabelaPrazoDias = implode(',', $dias);
        }

        $this->dispatch('erp-fv-focus-pagamento', index: $this->selectedPagamentoIndex ?? 0);
    }

    /**
     * Após desconto/acréscimo do pedido, recalibra o meio selecionado para o novo total
     * (evita "troco" fantasma quando o valor informado ficou maior que o líquido).
     */
    private function sincronizarPagamentoAposAjustePedido(): void
    {
        if ($this->meiosPagamento === []) {
            return;
        }

        $index = $this->indexFormaSelecionada();

        // Sem valor informado ainda: só atualiza o campo auxiliar.
        if ($this->totalInformado() <= 0) {
            $this->valorPagamento = $this->formatMoney($this->valorRestante());

            return;
        }

        $this->aplicarValorRestanteNaForma($index, focus: false);
        $this->valorPagamento = $this->formatMoney($this->valorRestante());
    }

    private function aplicarCliente(Person $person): void
    {
        $this->clienteId = (int) $person->id;
        $this->clienteCodigo = (string) ($person->codigo ?? '');
        $this->clienteNome = (string) ($person->nome_razao ?? '');
        $this->clienteCpfCnpj = (string) ($person->cpf_cnpj ?? '');
        $this->clienteEndereco = (string) ($person->endereco ?? '');
        $this->clienteNumero = (string) ($person->numero ?? '');
        $this->clienteBairro = (string) ($person->bairro ?? '');
        $this->clienteCep = (string) ($person->cep ?? '');
        $this->clienteCidade = (string) ($person->cidade_nome ?? '');
        $this->clienteUf = (string) ($person->uf ?? '');
        $this->clienteFone = (string) ($person->fone1 ?? $person->fone2 ?? '');
        $this->clienteWhatsapp = (string) ($person->whatsapp ?? $person->celular1 ?? '');
        $this->clienteObservacoes = trim((string) ($person->observacoes ?? ''));
        $this->observacaoClienteModalOpen = false;

        // Sempre realinha a tabela: cliente (se tiver) senão vendedor.
        $this->carregarTabelaPrecoPadrao();
    }

    private function aplicarProdutoSelecionado(Product $product): void
    {
        $this->descontoPct = '0,00';
        $this->descontoValor = '0,00';
        $this->acrescimoPct = '0,00';
        $this->acrescimoValor = '0,00';
        $this->precoUnitario = '0,00';
        $this->fecharSugestoesProduto();
        $this->carregarProdutoNoForm($product);
        $this->dispatch('fv-tela-venda-focus-qtd');
    }

    /**
     * @return list<array{id: int, codigo: string, nome: string, atual: string, reservado: string, disponivel: string, preco: string}>
     */
    private function montarSugestoesProduto(string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $like = '%'.$term.'%';

        $produtos = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term, $like): void {
                $query->where('codigo', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('codigo_barras_caixa', 'like', $like)
                    ->orWhere('descricao', 'like', $like);
            })
            ->orderByRaw('CASE WHEN codigo = ? THEN 0 WHEN descricao LIKE ? THEN 1 ELSE 2 END', [$term, $term.'%'])
            ->orderBy('descricao')
            ->limit(12)
            ->get(['id', 'codigo', 'descricao', 'preco_venda']);

        // Saldo físico e reservas no depósito do vendedor (mesmo critério da validação ao incluir item).
        $reservaService = app(EstoqueReservaService::class);
        $saldos = app(ProductEstoqueSaldoService::class);
        $reservas = $reservaService->totaisReservadosAtivos($this->estoqueId);

        return $produtos
            ->map(function (Product $product) use ($reservas, $reservaService, $saldos): array {
                $preco = $this->precoNaTabelaSelecionada($product) ?? (float) ($product->preco_venda ?? 0);
                $atual = $saldos->fisico((int) $product->id, $this->estoqueId);
                $reservado = (float) ($reservas[$product->id] ?? 0);
                $disponivel = $reservaService->disponivel($product, null, $this->estoqueId);

                return [
                    'id' => (int) $product->id,
                    'codigo' => (string) ($product->codigo ?? ''),
                    'nome' => (string) ($product->descricao ?? ''),
                    'atual' => $this->formatQty($atual),
                    'reservado' => $this->formatQty($reservado),
                    'disponivel' => $this->formatQty($disponivel),
                    'preco' => $this->formatMoney($preco),
                ];
            })
            ->values()
            ->all();
    }

    private function findExactProduct(string $term): ?Product
    {
        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term): void {
                $query->where('codigo', $term)
                    ->orWhere('referencia', $term)
                    ->orWhere('codigo_barras', $term)
                    ->orWhere('codigo_barras_caixa', $term);
            })
            ->first();
    }

    private function findProduct(string $term): ?Product
    {
        return $this->findExactProduct($term)
            ?? Product::query()
                ->where('ativo', true)
                ->where('descricao', 'like', '%'.$term.'%')
                ->orderBy('descricao')
                ->first();
    }

    private function carregarProdutoNoForm(Product $product): void
    {
        $this->produtoAtualId = (int) $product->id;
        $this->produtoAtualNome = (string) ($product->descricao ?? '');
        $this->produtoAtualFoto = $product->fotoUrl();
        // Troca o código digitado pela descrição ao confirmar o produto.
        $this->codigoBarras = $this->produtoAtualNome;

        $lista = $this->precoNaTabelaSelecionada($product) ?? (float) ($product->preco_venda ?? 0);
        $this->precoListaItem = $this->formatMoney($lista);

        if ($this->parseDecimal($this->precoUnitario) <= 0) {
            $this->precoUnitario = $this->formatMoney($lista);
        }

        $this->recalcularTotalItem();
    }

    private function limparEntradaItem(): void
    {
        $this->codigoBarras = '';
        $this->quantidade = '1,000';
        $this->precoUnitario = '0,00';
        $this->precoListaItem = '0,00';
        $this->descontoPct = '0,00';
        $this->descontoValor = '0,00';
        $this->acrescimoPct = '0,00';
        $this->acrescimoValor = '0,00';
        $this->totalItem = '0,00';
        $this->produtoAtualNome = '';
        // Mantém foto do último item selecionado via grid; limpa se quiser foco no barcode
        $this->produtoAtualFoto = null;
        $this->produtoAtualId = null;
        $this->fecharSugestoesProduto();
    }

    /**
     * Digitou valor diferente da tabela (ex.: tabela 192, digitou 190 ou 200):
     * mantém o unitário da tabela e lança a diferença como desconto ou acréscimo.
     * Se o digitado for igual à tabela, preserva desconto/acréscimo do Ctrl+D.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private function resolverPrecoDescontoAcrescimoDigitado(
        float $qtd,
        float $digitado,
        float $desconto,
        float $acrescimo,
    ): array {
        $lista = $this->parseDecimal($this->precoListaItem);

        if ($lista <= 0 || $qtd <= 0) {
            return [$digitado, $desconto, $acrescimo];
        }

        $deltaUnit = round($digitado - $lista, 2);

        // Igual à tabela: mantém ajuste explícito (Ctrl+D), se houver.
        if (abs($deltaUnit) < 0.005) {
            return [$lista, $desconto, $acrescimo];
        }

        // Outro valor digitado: diferença vira desconto/acréscimo (substitui o anterior).
        if ($deltaUnit < 0) {
            return [$lista, round(abs($deltaUnit) * $qtd, 2), 0.0];
        }

        return [$lista, 0.0, round($deltaUnit * $qtd, 2)];
    }

    /**
     * Ao mudar o Vlr. unit. para algo ≠ tabela, remove % antigo da edição
     * para não recalcular desconto em cima do novo valor digitado.
     */
    private function limparAjustePercentualSePrecoDiferenteDaLista(): void
    {
        $lista = $this->parseDecimal($this->precoListaItem);
        $digitado = $this->parseDecimal($this->precoUnitario);

        if ($lista <= 0) {
            return;
        }

        if (abs(round($digitado - $lista, 2)) < 0.005) {
            return;
        }

        $this->descontoPct = '0,00';
        $this->acrescimoPct = '0,00';
        $this->descontoValor = '0,00';
        $this->acrescimoValor = '0,00';
    }

    private function recalcularTotalItem(): void
    {
        $qtd = $this->parseDecimal($this->quantidade);
        $preco = $this->parseDecimal($this->precoUnitario);
        $bruto = $qtd * $preco;

        // Se há % de desconto/acréscimo, recalcula o R$ a partir do preço/qtd atuais.
        $descPct = $this->parseDecimal($this->descontoPct);
        $acrPct = $this->parseDecimal($this->acrescimoPct);

        if ($descPct > 0) {
            $this->descontoValor = $this->formatMoney(round($bruto * ($descPct / 100), 2));
        } elseif ($descPct <= 0) {
            $this->descontoValor = '0,00';
        }

        if ($acrPct > 0) {
            $this->acrescimoValor = $this->formatMoney(round($bruto * ($acrPct / 100), 2));
        } elseif ($acrPct <= 0) {
            $this->acrescimoValor = '0,00';
        }

        $acr = $this->parseDecimal($this->acrescimoValor);
        $desc = $this->parseDecimal($this->descontoValor);
        $this->totalItem = $this->formatMoney($bruto + $acr - $desc);
    }

    private function baseItensParaAjuste(): float
    {
        return round(array_sum(array_map(fn (array $i): float => (float) $i['total'], $this->itens)), 2);
    }

    /**
     * Itens da gravação com o desconto/acréscimo do pedido rateados proporcionalmente
     * ao total de cada linha (a sobra de arredondamento fica no último item).
     *
     * @return list<array<string, mixed>>
     */
    private function itensParaGravar(): array
    {
        $base = round(array_sum(array_map(fn (array $i): float => (float) $i['total'], $this->itens)), 2);

        $descontoRateio = $this->ratearValorNosItens(min($this->descontoPedidoEfetivo(), max(0, $base)), $base);
        $acrescimoRateio = $this->ratearValorNosItens($this->acrescimoPedidoEfetivo(), $base);

        $itens = [];

        foreach ($this->itens as $index => $item) {
            $itens[] = [
                'product_id' => (int) $item['product_id'],
                'product_grade_id' => $item['product_grade_id'] ?? null,
                'quantidade' => (float) $item['quantidade'],
                'preco_unitario' => (float) $item['preco_unitario'],
                'desconto' => round((float) $item['desconto'] + ($descontoRateio[$index] ?? 0), 2),
                'acrescimo' => round((float) ($item['acrescimo'] ?? 0) + ($acrescimoRateio[$index] ?? 0), 2),
                'descricao' => (string) $item['descricao'],
            ];
        }

        return $itens;
    }

    /**
     * @return array<int, float>
     */
    private function ratearValorNosItens(float $valor, float $base): array
    {
        $rateio = array_fill(0, count($this->itens), 0.0);

        if ($valor <= 0 || $base <= 0 || $this->itens === []) {
            return $rateio;
        }

        $ultimo = count($this->itens) - 1;
        $acumulado = 0.0;

        foreach ($this->itens as $index => $item) {
            if ($index === $ultimo) {
                $rateio[$index] = round($valor - $acumulado, 2);
                break;
            }

            $parte = round($valor * ((float) $item['total'] / $base), 2);
            $rateio[$index] = $parte;
            $acumulado = round($acumulado + $parte, 2);
        }

        return $rateio;
    }

    private function descontoPedidoEfetivo(): float
    {
        $pct = $this->parseDecimal($this->descontoPedidoPct);
        $valor = $this->parseDecimal($this->descontoPedidoValor);
        $base = $this->baseItensParaAjuste();

        if ($valor > 0) {
            return round(min($valor, max(0, $base)), 2);
        }

        if ($pct > 0) {
            return round(($base * $pct) / 100, 2);
        }

        return 0.0;
    }

    private function acrescimoPedidoEfetivo(): float
    {
        $pct = $this->parseDecimal($this->acrescimoPedidoPct);
        $valor = $this->parseDecimal($this->acrescimoPedidoValor);
        $base = $this->baseItensParaAjuste();

        if ($valor > 0) {
            return round($valor, 2);
        }

        if ($pct > 0) {
            return round(($base * $pct) / 100, 2);
        }

        return 0.0;
    }

    /** Remove qualquer caractere que não seja dígito, vírgula ou ponto. */
    private function sanitizarNumero(string $value): string
    {
        return preg_replace('/[^\d.,]/', '', $value) ?? '';
    }

    private function parseDecimal(string $value): float
    {
        $normalized = str_replace(['.', ' '], '', trim($value));
        $normalized = str_replace(',', '.', $normalized);

        return (float) $normalized;
    }
}
