<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Models\CaixaConta;
use App\Models\Estoque;
use App\Models\ForcaVendasOrder;
use App\Models\FormaPagamento;
use App\Models\PedidoItem;
use App\Models\Person;
use App\Models\PriceTable;
use App\Models\Product;
use App\Models\Vendedor;
use App\Support\Erp\ConsumidorFinalGuard;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ProductEmpresaPrecoService;
use App\Support\Erp\ProductTabelaPrecoService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueNegativoPolicy;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\Financeiro\VendaCaixaDestinoResolver;
use App\Support\Erp\Pdv\PdvClienteLimiteService;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use App\Support\Erp\Pdv\PdvNotaClienteService;
use App\Support\ForcaVendas\ForcaVendasFaturamentoService;
use App\Support\ForcaVendas\ForcaVendasItemAgrupamento;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;
use App\Support\ForcaVendas\ForcaVendasTelaVendaService;
use App\Filament\Pages\Concerns\ManagesBoletoPosDocumentoPrompt;
use App\Filament\Pages\Concerns\ManagesForcaVendasTelaVendaImportarOrcamento;
use App\Filament\Pages\Concerns\ManagesForcaVendasTelaVendaListarVendas;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ForcaVendasTelaVendaPage extends Page
{
    use ManagesBoletoPosDocumentoPrompt;
    use ManagesForcaVendasTelaVendaImportarOrcamento;
    use ManagesForcaVendasTelaVendaListarVendas;
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

    /**
     * Empresa congelada no início da venda (sessão no mount ou order.empresa_id).
     * Impede gravar/faturar após troca de empresa em outra aba.
     */
    public ?int $vendaEmpresaId = null;

    /**
     * UUID da operação de tela (venda nova). Congelado no mount / nova venda.
     * Idempotência F3/Sair: o mesmo UUID não cria dois DAVs.
     */
    public string $operacaoUuid = '';

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

    public bool $tabPrecoItemModalOpen = false;

    /** @var list<array{nivel: string, price_table_id: int, label: string, preco: float}> */
    public array $tabPrecoItemOpcoes = [];

    public ?int $tabPrecoItemSelectedIndex = null;

    public ?int $tabPrecoItemProductId = null;

    /** Tabela já confirmada para o produto atual no formulário. */
    public bool $tabPrecoItemConfirmada = false;

    /** Inclusão de item em andamento (bloqueia F3/F4 até concluir). */
    public bool $isConfirmingItem = false;

    /** Key do item retirado da grade para edição na barra (duplo clique). */
    public ?string $editingItemKey = null;

    /**
     * Snapshot do item em edição (para Esc restaurar sem alterar).
     *
     * @var array<string, mixed>|null
     */
    public ?array $editingItemSnapshot = null;

    /**
     * Memo request-local (não serializa no Livewire) — evita reconsultar price_table no mesmo mount.
     *
     * @var array<int, int|null>
     */
    private array $fvMemoPriceTableCliente = [];

    /**
     * @var array<int, int|null>
     */
    private array $fvMemoPriceTableVendedor = [];

    public string $codigoBarras = '';

    /** @var list<array{id: int, codigo: string, nome: string, atual: string, reservado: string, disponivel: string, preco: string}> */
    public array $produtoSugestoes = [];

    public bool $produtoSugestoesOpen = false;

    public int $selectedProdutoSugestaoIndex = 0;

    public string $quantidade = '1,000';

    public string $precoUnitario = '0,00';

    /** Preço sugerido (tabela/promo) do produto em lançamento — referência; o digitado é o unitário efetivo. */
    public string $precoListaItem = '0,00';

    public string $descontoPct = '0,00';

    public string $descontoValor = '0,00';

    public string $acrescimoPct = '0,00';

    public string $acrescimoValor = '0,00';

    public string $totalItem = '0,00';

    public bool $descontoModalOpen = false;

    public bool $excluirItemModalOpen = false;

    /** Modal de confirmação do botão Sair / navegação SPA (grava pendente). */
    public bool $fvSairConfirmOpen = false;

    /** Destino SPA após confirmar o modal (null = Monitor). */
    public ?string $fvSairNavigateUrl = null;

    /** Pós-faturamento TV: perguntar se deseja imprimir o comprovante. */
    public bool $fvImprimirPosVendaOpen = false;

    /** Popup de impressão bloqueado pelo navegador — oferece link manual. */
    public bool $fvImprimirPosVendaPopupBloqueado = false;

    /** Evita duplo clique em Sim/Não no modal de impressão. */
    public bool $fvImprimirPosVendaProcessando = false;

    /** order id (forca_vendas_orders) da venda recém-faturada. */
    public ?int $fvImprimirPosVendaOrderId = null;

    public string $fvImprimirPosVendaUrl = '';

    public string $fvImprimirPosVendaMonitorUrl = '';

    /** Se true, abre a pergunta de impressão ao encerrar o fluxo de boleto. */
    public bool $fvImprimirPosVendaAposBoleto = false;

    /** Após concluir: permanece na TV e inicia nova venda (em vez de Monitor). */
    public bool $fvAposConcluirPermanecer = false;

    /** Incrementa a cada nova venda pós-conclusão (protege contra Livewire atrasado). */
    public int $fvVendaGeracao = 0;

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
     *   preco_manual?: bool,
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

    /** CPF na Nota (NFC-e) — só no payload do DAV; não é o CPF do cadastro do cliente. */
    public string $cpfNota = '';

    public bool $observacaoPedidoModalOpen = false;

    public bool $observacaoClienteModalOpen = false;

    public string $aberturaData = '';

    public string $aberturaHora = '';

    /** @var list<array{id: int, descricao: string, atalho: string, valor: string}> */
    public array $meiosPagamento = [];

    /** Divisão informativa da conta (F4) — não altera pagamentos. */
    public string $fvDividirPor = '1';

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

        if (! $this->iniciarContextoVendaNova()) {
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
        // F2 não altera status no banco; limpeza só cobre IMPORTADO órfão legado.
        $this->devolverOrcamentoOrigemSeNaoGravado();

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
        $this->cpfNota = '';
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

        $this->vendaEmpresaId = null;
        $this->operacaoUuid = '';
        $this->orcamentoOrigemId = null;

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
        $this->fvSairConfirmOpen = false;
        $this->fvSairNavigateUrl = null;
        $this->resetImprimirPosVendaState();
        $this->resetFinalizarCartaoCanhoto();

        $this->fvMemoPriceTableCliente = [];
        $this->fvMemoPriceTableVendedor = [];

        $this->carregarClientePadrao();
        $this->carregarOpcoesOperacao();
        $this->carregarVendedorPadrao();

        // Caixa: carregarOpcoes não pré-carrega mais (evita 2×). Se não veio com o vendedor, resolve agora.
        if ($this->caixaOpcoes === []) {
            $this->caixaOpcoes = $this->opcoesCaixaDaTela();
            $this->caixaOperacionalDefinido();
        }

        // Tabela: aplicarCliente/aplicarVendedor já tentaram; uma passagem final cobre o caso sem vendedor.
        $this->carregarTabelaPrecoPadrao();

        // Meios de pagamento: só na finalização (irParaFinalizacao) — não na abertura da venda.
    }

    public function novoPedido(): void
    {
        $this->pedidoId = null;
        $this->resetarVenda();

        if (! $this->iniciarContextoVendaNova()) {
            return;
        }

        Notification::make()->title('Nova venda iniciada.')->success()->send();
        $this->dispatch('fv-tela-venda-focus-cliente');
    }

    /**
     * F2 mantém FECHADO no banco — abandono sem gravação não depende desta rotina.
     * Mantida só para recuperar IMPORTADO órfão de versões anteriores.
     * Com pedidoId, o cancelamento do Monitor trata o vínculo via orcamento_id.
     */
    private function devolverOrcamentoOrigemSeNaoGravado(): void
    {
        $orcamentoId = (int) ($this->orcamentoOrigemId ?? 0);

        if ($orcamentoId <= 0 || $this->pedidoId) {
            return;
        }

        app(\App\Support\ForcaVendas\ForcaVendasOrcamentoVinculo::class)
            ->devolverSeImportacaoNaoGravada($orcamentoId);
        $this->orcamentoOrigemId = null;
    }

    /**
     * Congela empresa + UUID de operação para venda nova (idempotência F3/Sair).
     */
    private function iniciarContextoVendaNova(): bool
    {
        $this->vendaEmpresaId = ErpContext::currentEmpresaId();

        if ($this->vendaEmpresaId === null || $this->vendaEmpresaId <= 0) {
            Notification::make()
                ->title('Nenhuma empresa selecionada.')
                ->body('Selecione a empresa ativa antes de iniciar uma venda.')
                ->warning()
                ->send();
            $this->redirect(ForcaVendasMonitorResource::getUrl('index'));

            return false;
        }

        $this->operacaoUuid = (string) Str::uuid();

        return true;
    }

    /**
     * Venda com itens em memória (ainda não gravados via F3/F4 nesta sessão de tela).
     * Usado pela proteção de F5 / beforeunload / navegação SPA.
     */
    public function vendaTemAlteracoesPendentes(): bool
    {
        return $this->itens !== [];
    }

    public function cancelarVenda(): void
    {
        if ($this->etapa === 'finalizacao') {
            $this->voltarParaVenda();

            return;
        }

        $this->sair();
    }

    /**
     * Sair: com itens, abre modal laranja UNI. Sem itens, retorna ao Monitor.
     */
    public function sair(): void
    {
        if ($this->tabelaProgressOpen) {
            return;
        }

        if ($this->vendaTemAlteracoesPendentes()) {
            $this->fvSairNavigateUrl = null;
            $this->fvSairConfirmOpen = true;
            $this->dispatch('erp-fv-focus-sair-confirm');

            return;
        }

        // Sem itens: limpeza defensiva de IMPORTADO órfão (F2 atual não muda status).
        $this->devolverOrcamentoOrigemSeNaoGravado();
        $this->liberarSaidaTelaVenda();
        $this->redirect(ForcaVendasMonitorResource::getUrl('index'));
    }

    /**
     * Navegação SPA do menu com itens: mesmo modal (destino = URL pretendida).
     */
    public function abrirSairConfirmNavegacao(?string $url = null): void
    {
        if (! $this->vendaTemAlteracoesPendentes()) {
            return;
        }

        $this->fvSairNavigateUrl = $this->normalizarUrlSaidaInterna($url);
        $this->fvSairConfirmOpen = true;
        $this->dispatch('erp-fv-focus-sair-confirm');
    }

    public function dismissSairConfirm(): void
    {
        $this->fvSairConfirmOpen = false;
        $this->fvSairNavigateUrl = null;
        $this->rearmarGuardSaidaTelaVenda();
    }

    /**
     * Confirmação do modal: grava/atualiza pendente (fluxo F3) e só então navega.
     */
    public function confirmSairSalvarPendente(?string $qtdRaw = null, ?string $precoRaw = null): void
    {
        $this->fvSairConfirmOpen = false;
        $this->gravarPedidoPendente(aoSair: true, qtdRaw: $qtdRaw, precoRaw: $precoRaw);
    }

    /**
     * Libera livewire:navigate do redirect após gravação/saída autorizada.
     */
    private function liberarSaidaTelaVenda(): void
    {
        $this->dispatch('fv-tela-venda-allow-leave');
    }

    /**
     * Reativa o bloqueio SPA se a gravação/validação falhou.
     */
    private function rearmarGuardSaidaTelaVenda(): void
    {
        $this->dispatch('fv-tela-venda-keep-guard');
    }

    /**
     * Aceita só URLs internas (mesmo host ou path relativo).
     */
    private function normalizarUrlSaidaInterna(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $app = rtrim((string) config('app.url'), '/');
        if ($app !== '' && str_starts_with($url, $app.'/')) {
            return $url;
        }

        try {
            $parsed = parse_url($url);
            $host = $parsed['host'] ?? null;
            $appHost = parse_url($app, PHP_URL_HOST);

            if ($host && $appHost && strcasecmp((string) $host, (string) $appHost) === 0) {
                $path = (string) ($parsed['path'] ?? '/');
                $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

                return $path.$query;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
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
            $this->assertContextoEmpresaOperavel();
            app(ForcaVendasTelaVendaService::class)->assertOrderDaEmpresa($order, (int) $this->vendaEmpresaId);
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

        if ($this->etapa === 'finalizacao') {
            // Crediário/cheque/boleto: prazo depende do cliente — força remonte no F10.
            if ($this->fvCrediarioTotalValor > 0) {
                $this->tabelaPrazoDias = null;
                $this->fvTabelaPrazoId = null;
                $this->fvParcelasRows = [];
                $this->fvTabelaPrazoConsulta = false;
            }

            $this->dispatch('erp-fv-fin-cliente-synced', value: $this->clienteBusca);
            $this->dispatch('erp-fv-fin-cpf-synced', value: $this->cpfNota);

            return;
        }

        if ($this->etapa === 'venda') {
            $this->dispatch('fv-tela-venda-focus-barcode');
        }
    }

    /**
     * Antes do F10/Concluir: o texto visível no campo Cliente do F4 deve
     * corresponder ao cadastro selecionado (não aceita texto livre).
     */
    public function sincronizarClienteFinalizacaoBusca(string $buscaVisivel, bool $skipRender = true): bool
    {
        if ($this->etapa !== 'finalizacao') {
            return true;
        }

        $term = trim($buscaVisivel);

        if ($this->clienteId && ($term === '' || $this->clienteJaSelecionadoCorresponde($term))) {
            if ($term === '') {
                $this->restaurarClienteBuscaSelecionado();
            }

            $this->fecharSugestoesCliente();
            if ($skipRender) {
                $this->skipRender();
            }

            return true;
        }

        $this->restaurarClienteBuscaSelecionado();
        $this->fecharSugestoesCliente();
        Notification::make()
            ->title('Selecione o cliente na lista.')
            ->body('A troca só vale após escolher um cadastro válido.')
            ->warning()
            ->send();

        $this->dispatch('erp-fv-fin-cliente-synced', value: $this->clienteBusca);

        return false;
    }

    private function restaurarClienteBuscaSelecionado(): void
    {
        if (! $this->clienteId) {
            $this->clienteBusca = '';

            return;
        }

        $person = Person::query()->find((int) $this->clienteId);

        if ($person) {
            $this->clienteBusca = $this->formatarClienteBusca($person);

            return;
        }

        $this->clienteBusca = trim(
            ($this->clienteCodigo !== '' ? $this->clienteCodigo.' — ' : '').$this->clienteNome
        );
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
        // Pesquisa de sugestões: GET /admin/erp/forca-vendas/produtos-lookup (JS/DOM).
        // Mantém o estado Livewire limpo — não remonta a venda a cada tecla.
        $term = trim($value);

        if ($term === '' || ($this->produtoAtualNome !== '' && mb_strtoupper($term, 'UTF-8') === mb_strtoupper($this->produtoAtualNome, 'UTF-8'))) {
            $this->fecharSugestoesProduto();
        }
    }

    public function moverSugestaoProduto(int $delta): void
    {
        // Navegação ↑↓ é feita no cliente (JS/DOM) para ficar imediata.
        $this->skipRender();
    }

    public function fecharSugestoesProduto(): void
    {
        // Não usar skipRender aqui: limparEntradaItem() (após adicionarItem) e
        // aplicarProdutoSelecionado() precisam remorphar grade/formulário.
        // A lista de sugestões é DOM/GET; limpar estes arrays é barato.
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

    public function confirmarCodigoProduto(?string $typed = null): void
    {
        if (is_string($typed)) {
            $this->codigoBarras = trim($typed);
        }

        // Lista de sugestões agora é DOM/GET — Enter com lista aberta já seleciona no JS.
        // Aqui resolve código/EAN/descrição digitados (inclusive antes da lista carregar).
        $term = trim($this->codigoBarras);

        if ($term === '') {
            // Evita aviso fantasma quando Enter chega após limpar a barra.
            $this->skipRender();

            return;
        }

        // Já confirmado (campo mostra a descrição): só avança para quantidade.
        if ($this->produtoAtualId && $this->produtoAtualNome !== ''
            && mb_strtoupper($term, 'UTF-8') === mb_strtoupper($this->produtoAtualNome, 'UTF-8')) {
            $this->skipRender();
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
                // Lista no DOM (sem remorph Livewire das sugestões).
                $this->produtoSugestoes = [];
                $this->produtoSugestoesOpen = false;
                $this->selectedProdutoSugestaoIndex = 0;
                $this->skipRender();
                $this->dispatch('fv-tela-venda-produto-lookup-results', term: $term, results: $sugestoes);

                return;
            }

            Notification::make()->title('Produto não encontrado.')->warning()->send();

            return;
        }

        $this->aplicarProdutoSelecionado($product);
    }

    /**
     * Enter na quantidade: navegação local no JS.
     * Mantido para Tab. Preço (Enter inclui) e fallback sem o script de Enter.
     */
    public function focoPrecoAposQtd(?string $qtdRaw = null): void
    {
        if (is_string($qtdRaw)) {
            $this->quantidade = $qtdRaw;
        }

        if ($this->parseDecimal($this->quantidade) <= 0) {
            Notification::make()->title('Quantidade inválida.')->warning()->send();

            return;
        }

        // Após confirmar Tab. Preço, Enter na qtde já inclui (preço já veio da tabela).
        if (
            $this->tabPrecoItemConfirmada
            && $this->produtoAtualId
            && $this->parseDecimal($this->precoUnitario) > 0
        ) {
            $this->adicionarItem($this->quantidade, $this->precoUnitario);

            return;
        }

        $this->skipRender();
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

    public function adicionarItem(?string $qtdRaw = null, ?string $precoRaw = null): void
    {
        if ($this->isConfirmingItem) {
            return;
        }

        if (is_string($qtdRaw)) {
            $this->quantidade = $qtdRaw;
        }

        // Enter/JS pode enviar o preço sem disparar updatedPrecoUnitario.
        // Se o unitário mudou de fato, limpa %/R$ da barra (mesmo contrato do blur).
        if (is_string($precoRaw)) {
            $precoAnterior = $this->parseDecimal($this->precoUnitario);
            $this->precoUnitario = $precoRaw;
            $precoNovo = $this->parseDecimal($this->precoUnitario);

            if (abs($precoNovo - $precoAnterior) >= 0.005) {
                $this->limparAjustePercentualSePrecoDiferenteDaLista();
            }
        }

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

        if ((int) $this->produtoAtualId !== (int) $product->id) {
            $this->carregarProdutoNoForm($product);

            if ($this->tabPrecoItemModalOpen) {
                return;
            }
        } elseif (($product->usa_tab_preco ?? false) && ! $this->tabPrecoItemConfirmada) {
            $this->abrirTabPrecoItemModal($product, $this->parseDecimal($this->quantidade) ?: 1);

            Notification::make()
                ->title('Confirme a tabela de preço do produto.')
                ->warning()
                ->send();

            return;
        }

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

        // Origem explícita na confirmação: digitado ≠ sugerido da tabela atual.
        // Não inferir em reabertura histórica (lá o flag vem só do payload).
        $sugeridoTabela = round($this->precoProdutoNaTabela($product, $qtd > 0 ? $qtd : 1), 2);
        $precoManual = abs(round($preco, 2) - $sugeridoTabela) >= 0.005;

        if ($qtd <= 0) {
            Notification::make()->title('Quantidade inválida.')->warning()->send();

            return;
        }

        if ($this->bloquearSeOperacaoInvalida(exigeEstoque: ! $product->is_servico)) {
            return;
        }

        if (EstoqueNegativoPolicy::ativo() && ! $product->is_servico) {
            $editKey = $this->editingItemKey;
            $jaNoPedido = 0.0;
            foreach ($this->itens as $existente) {
                // Edição não destrutiva: não contar a própria linha duas vezes.
                if ($editKey !== null && (string) ($existente['key'] ?? '') === $editKey) {
                    continue;
                }
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

        $this->isConfirmingItem = true;

        try {
            $productId = (int) $product->id;
            $gradeId = null;
            $agrupado = false;
            $editado = false;
            $removedKey = null;
            $editingKey = $this->editingItemKey;
            $editIndex = ($editingKey !== null && $editingKey !== '')
                ? $this->findItemIndexByKey($editingKey)
                : null;

            if ($editIndex !== null) {
                $editado = true;
                // Outra linha (não a editada) com mesmo produto+preço+ajuste → agrupa.
                $existenteIndex = $this->indiceItemAgrupavel(
                    $productId,
                    $gradeId,
                    $preco,
                    $qtd,
                    $desconto,
                    $acrescimo,
                    $editingKey,
                );

                if ($existenteIndex !== null) {
                    $agrupado = true;
                    $removedKey = $editingKey;
                    $item = $this->itens[$existenteIndex];
                    $consolidado = ForcaVendasItemAgrupamento::consolidar(
                        (float) $item['quantidade'],
                        (float) ($item['desconto'] ?? 0),
                        (float) ($item['acrescimo'] ?? 0),
                        $qtd,
                        $desconto,
                        $acrescimo,
                    );
                    $item['quantidade'] = $consolidado['quantidade'];
                    $item['acrescimo'] = $consolidado['acrescimo'];
                    $item['desconto'] = $consolidado['desconto'];
                    $item['preco_manual'] = (bool) ($item['preco_manual'] ?? false) || $precoManual;
                    $item['total'] = round(
                        ($consolidado['quantidade'] * $preco) + $consolidado['acrescimo'] - $consolidado['desconto'],
                        2,
                    );
                    // Remove a linha em edição e sobe a consolidada.
                    array_splice($this->itens, $editIndex, 1);
                    if ($existenteIndex > $editIndex) {
                        $existenteIndex--;
                    }
                    array_splice($this->itens, $existenteIndex, 1);
                    array_unshift($this->itens, $item);
                } else {
                    $item = $this->itens[$editIndex];
                    $item['quantidade'] = $qtd;
                    $item['preco_unitario'] = $preco;
                    $item['preco_manual'] = $precoManual;
                    $item['acrescimo'] = $acrescimo;
                    $item['desconto'] = $desconto;
                    $item['total'] = round(($qtd * $preco) + $acrescimo - $desconto, 2);
                    $item['price_table_id'] = ($product->usa_tab_preco ?? false) ? $this->tabelaPrecoId : ($item['price_table_id'] ?? null);
                    $item['tabela_nivel'] = ($product->usa_tab_preco ?? false)
                        ? $this->nivelTabelaPrecoSelecionada()
                        : ($item['tabela_nivel'] ?? null);
                    array_splice($this->itens, $editIndex, 1);
                    array_unshift($this->itens, $item);
                }
            } else {
                // Inclusão nova: agrupa se já existir mesma linha (preço + ajuste).
                $existenteIndex = $this->indiceItemAgrupavel(
                    $productId,
                    $gradeId,
                    $preco,
                    $qtd,
                    $desconto,
                    $acrescimo,
                );

                if ($existenteIndex !== null) {
                    $agrupado = true;
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
                    $item['preco_manual'] = (bool) ($item['preco_manual'] ?? false) || $precoManual;
                    $item['total'] = round(($novaQtd * $preco) + $novoAcrescimo - $novoDesconto, 2);

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
                        'preco_manual' => $precoManual,
                        'price_table_id' => ($product->usa_tab_preco ?? false) ? $this->tabelaPrecoId : null,
                        'tabela_nivel' => ($product->usa_tab_preco ?? false)
                            ? $this->nivelTabelaPrecoSelecionada()
                            : null,
                        'acrescimo' => $acrescimo,
                        'desconto' => $desconto,
                        'total' => round(($qtd * $preco) + $acrescimo - $desconto, 2),
                        'foto' => $product->fotoUrl(),
                    ]);
                }
            }

            $this->itens = array_values($this->itens);
            $this->itemSelecionado = 0;
            $this->tabPrecoItemConfirmada = false;
            $this->editingItemKey = null;
            $this->editingItemSnapshot = null;

            unset($this->totalBruto, $this->totalDescontosItens, $this->totalAcrescimosItens);

            $included = $this->fvItemIncludedPayload($agrupado);
            $this->limparEntradaItem();

            // Grade + totais no DOM (1º item usa <template>; demais clonam/atualizam linha).
            if ($included !== null) {
                $this->skipRender();
                $this->dispatch(
                    'fv-tela-venda-item-included',
                    item: $included['item'],
                    totais: $included['totais'],
                    foto: $included['foto'] ?? null,
                    nome: $included['nome'] ?? '',
                    agrupado: $agrupado,
                    editado: $editado,
                    removed_key: $removedKey,
                );
            }

            $this->dispatch('fv-tela-venda-focus-barcode');
        } finally {
            $this->isConfirmingItem = false;
        }
    }

    private function findItemIndexByKey(string $key): ?int
    {
        foreach ($this->itens as $index => $item) {
            if ((string) ($item['key'] ?? '') === $key) {
                return (int) $index;
            }
        }

        return null;
    }

    public function selecionarItemByKey(string $key): void
    {
        $index = $this->findItemIndexByKey($key);

        if ($index === null) {
            $this->skipRender();

            return;
        }

        $this->selecionarItem($index);
        // Sem remorph: foto/seleção azul via JS (payload oficial do servidor).
        $this->skipRender();
        $this->dispatch('fv-tela-venda-item-selected', [
            'key' => $key,
            'foto' => $this->produtoAtualFoto,
            'nome' => $this->produtoAtualNome,
        ]);
    }

    public function pedirConfirmacaoExcluirItemPorKey(string $key): void
    {
        $index = $this->findItemIndexByKey($key);

        if ($index === null) {
            $this->skipRender();

            return;
        }

        $this->pedirConfirmacaoExcluirItemPorIndice($index);
    }

    public function puxarItemParaInclusaoByKey(string $key): void
    {
        $index = $this->findItemIndexByKey($key);

        if ($index === null) {
            $this->skipRender();

            return;
        }

        $this->puxarItemParaInclusao($index);
    }

    /**
     * Índice da linha existente para agrupar o mesmo item
     * (produto + grade + preço unitário + desconto/acréscimo unitários).
     */
    private function indiceItemAgrupavel(
        int $productId,
        ?int $gradeId,
        float $preco,
        float $quantidade,
        float $desconto,
        float $acrescimo,
        ?string $excludeKey = null,
    ): ?int {
        $descUnit = $quantidade > 0 ? round($desconto / $quantidade, 4) : 0.0;
        $acrUnit = $quantidade > 0 ? round($acrescimo / $quantidade, 4) : 0.0;

        foreach ($this->itens as $index => $item) {
            if ($excludeKey !== null && (string) ($item['key'] ?? '') === $excludeKey) {
                continue;
            }

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

            $itemQtd = (float) ($item['quantidade'] ?? 0);
            $itemDescUnit = $itemQtd > 0
                ? round(((float) ($item['desconto'] ?? 0)) / $itemQtd, 4)
                : 0.0;
            $itemAcrUnit = $itemQtd > 0
                ? round(((float) ($item['acrescimo'] ?? 0)) / $itemQtd, 4)
                : 0.0;

            // Não misturar linha limpa com linha que tem Ctrl+D (ou acréscimo legado).
            if (abs($itemDescUnit - $descUnit) > 0.0001 || abs($itemAcrUnit - $acrUnit) > 0.0001) {
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
     * Duplo clique: carrega o item na barra sem retirar da grade (edição não destrutiva).
     * Totais e $itens permanecem intactos até confirmar com Enter.
     */
    public function puxarItemParaInclusao(int $index): void
    {
        if ($this->etapa !== 'venda' || $this->descontoModalOpen || $this->excluirItemModalOpen) {
            return;
        }

        if (! isset($this->itens[$index])) {
            return;
        }

        // Troca de item em edição: só limpa a barra (linha permanece na grade).
        if ($this->editingItemKey !== null) {
            $this->editingItemKey = null;
            $this->editingItemSnapshot = null;
        }

        $item = $this->itens[$index];
        $key = (string) ($item['key'] ?? '');
        $preco = (float) ($item['preco_unitario'] ?? 0);
        $qtd = (float) ($item['quantidade'] ?? 0);
        $desconto = (float) ($item['desconto'] ?? 0);
        $acrescimo = (float) ($item['acrescimo'] ?? 0);

        $this->editingItemKey = $key !== '' ? $key : null;
        $this->editingItemSnapshot = $item;
        $this->itemSelecionado = $index;

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

        // Item permanece em $itens — totais inalterados. JS só marca is-editing + preenche a barra.
        $this->skipRender();
        $this->dispatch('fv-tela-venda-item-edit-loaded', [
            'key' => $key,
            'nome' => $this->produtoAtualNome,
            'codigo' => (string) ($item['codigo'] ?? ''),
            'qtd' => $this->quantidade,
            'preco' => $this->precoUnitario,
            'total' => $this->totalItem,
            'foto' => $this->produtoAtualFoto,
        ]);
        $this->dispatch('fv-tela-venda-focus-qtd');
    }

    /**
     * Esc: cancela edição — item na grade fica como estava; limpa a barra.
     */
    public function cancelarEdicaoItem(): void
    {
        if ($this->editingItemKey === null) {
            $this->skipRender();

            return;
        }

        $key = $this->editingItemKey;
        $this->editingItemKey = null;
        $this->editingItemSnapshot = null;
        $this->limparEntradaItem();

        $this->skipRender();
        $this->dispatch('fv-tela-venda-item-edit-cancelled', key: $key);
        $this->dispatch('fv-tela-venda-focus-barcode');
    }

    /**
     * @return array<string, string|bool>
     */
    private function fvTotaisPayload(): array
    {
        return [
            'bruto' => $this->formatMoney($this->totalBruto),
            'acrescimos' => $this->formatMoney($this->totalAcrescimosItens),
            'descontos' => $this->formatMoney($this->totalDescontosItens),
            'liquido' => $this->formatMoney($this->totalLiquido()),
            'itens_qtd_label' => $this->rotuloQuantidadeItens(),
            'has_itens' => $this->itens !== [],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function fvItemRowPayload(array $row, bool $agrupado): array
    {
        $qtd = (float) ($row['quantidade'] ?? 0);
        $preco = (float) ($row['preco_unitario'] ?? 0);
        $productId = (int) ($row['product_id'] ?? 0);
        $custo = 0.0;

        if ($this->exibirCustoProdutoGrade && $productId > 0) {
            $custos = ForcaVendasMargemVendaCalculator::custosUnitariosPorProduto(
                [$productId],
                ErpContext::currentEmpresaId(),
            );
            $custo = (float) ($custos[$productId] ?? 0);
        }

        return [
            'key' => (string) ($row['key'] ?? ''),
            'product_id' => $productId,
            'codigo' => (string) ($row['codigo'] ?? ''),
            'descricao' => (string) ($row['descricao'] ?? ''),
            'quantidade' => $this->formatQty($qtd),
            'preco_unitario' => $this->formatMoney($preco),
            'bruto' => $this->formatMoney($qtd * $preco),
            'acrescimo' => $this->formatMoney((float) ($row['acrescimo'] ?? 0)),
            'desconto' => $this->formatMoney((float) ($row['desconto'] ?? 0)),
            'total' => $this->formatMoney((float) ($row['total'] ?? 0)),
            'custo' => $this->formatMoney($custo),
            'agrupado' => $agrupado,
        ];
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
        $key = null;
        if ($this->itemSelecionado !== null && isset($this->itens[$this->itemSelecionado])) {
            $key = (string) ($this->itens[$this->itemSelecionado]['key'] ?? '');
        }

        $this->excluirItemModalOpen = false;
        $this->removerItemSelecionado();

        unset($this->totalBruto, $this->totalDescontosItens, $this->totalAcrescimosItens);

        // Ainda há linhas: atualiza DOM sem remorph (modal já fechou no estado; JS remove o nó).
        // Último item: remorph para restaurar a linha vazia da grade.
        if ($key !== null && $key !== '' && $this->itens !== []) {
            $nextKey = isset($this->itens[$this->itemSelecionado])
                ? (string) ($this->itens[$this->itemSelecionado]['key'] ?? '')
                : '';
            $this->skipRender();
            $this->dispatch(
                'fv-tela-venda-item-deleted',
                key: $key,
                totais: [
                    'bruto' => $this->formatMoney($this->totalBruto),
                    'acrescimos' => $this->formatMoney($this->totalAcrescimosItens),
                    'descontos' => $this->formatMoney($this->totalDescontosItens),
                    'liquido' => $this->formatMoney($this->totalLiquido()),
                    'itens_qtd_label' => $this->rotuloQuantidadeItens(),
                    'has_itens' => true,
                ],
                next_key: $nextKey,
                foto: $this->produtoAtualFoto,
                nome: $this->produtoAtualNome,
            );
        }

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

        if ($this->itemSelecionado !== null && isset($this->itens[$this->itemSelecionado])) {
            $next = $this->itens[$this->itemSelecionado];
            $this->produtoAtualId = (int) ($next['product_id'] ?? 0) ?: null;
            $this->produtoAtualNome = (string) ($next['descricao'] ?? '');
            $this->produtoAtualFoto = $next['foto'] ?? null;
        } else {
            $this->produtoAtualId = null;
            $this->produtoAtualNome = '';
            $this->produtoAtualFoto = null;
        }
    }

    public function irParaFinalizacao(?string $qtdRaw = null, ?string $precoRaw = null): void
    {
        if ($this->isConfirmingItem || $this->tabelaProgressOpen || $this->gravando) {
            return;
        }

        try {
            $this->assertContextoEmpresaOperavel();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->warning()->send();

            return;
        }

        if ($this->editingItemKey !== null) {
            $this->adicionarItem($qtdRaw, $precoRaw);
            if ($this->editingItemKey !== null) {
                return;
            }
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

        $this->etapa = 'finalizacao';
        ErpScreen::set('Finalização do Pedido');
        $this->fvDividirPor = '1';
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
        if ($this->gravando) {
            return;
        }

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

    /**
     * Esc/Voltar em 1 round-trip: grava valores/CPF/infos do DOM e fecha o F4.
     *
     * @param  array<int|string, string>  $valores
     */
    public function voltarParaVendaComFlush(
        array $valores,
        ?int $selectedIndex,
        string $cpfNota,
        string $observacoes,
    ): void {
        if ($this->gravando) {
            return;
        }

        if ($this->fvTabelaPrazoConsulta) {
            $this->cancelarFvTabelaPrazoConsulta();

            return;
        }

        if ($this->finalizarCartaoCanhotoAberta) {
            $this->cancelFinalizarCartaoCanhoto();

            return;
        }

        if ($this->etapa !== 'finalizacao') {
            return;
        }

        $this->flushValoresPagamento($valores, $selectedIndex, skipRender: false);
        $this->flushCpfNotaEObservacoes($cpfNota, $observacoes, validarCpf: false, skipRender: false);
        $this->voltarParaVenda();
    }

    /**
     * Seleciona a linha (clique na forma / foco). NÃO preenche restante.
     * Restante automático: somente selectPagamentoByAtalho (tecla ou botão A/P/D/C/K).
     *
     * @param  bool  $sugerirRestante  reservado; deve permanecer false fora de atalho.
     */
    public function selectPagamentoRow(int $index, bool $sugerirRestante = false): void
    {
        if (! isset($this->meiosPagamento[$index])) {
            return;
        }

        $this->selectedPagamentoIndex = $index;
        $this->formaSelecionadaId = (int) $this->meiosPagamento[$index]['id'];

        // Restante NÃO é preenchido por clique/foco — só por atalho explícito.
        if (
            $sugerirRestante
            && ErpMoney::parseBr($this->meiosPagamento[$index]['valor'] ?? '0') <= 0
        ) {
            $this->aplicarValorRestanteNaForma($index, focus: true);

            return;
        }

        // Foco/seleção já feitos no DOM (mousedown). Sem dispatch — evita corrida com setas/Enter.
        $this->skipRender();
    }

    /**
     * Compat: setas/F8 não chamam mais isto (navegação 100% local).
     * Mantido só para não quebrar clients antigos em cache.
     */
    public function syncPagamentoSelecao(int $index, ?string $valorSugerido = null): void
    {
        if (! isset($this->meiosPagamento[$index])) {
            $this->skipRender();

            return;
        }

        $this->selectedPagamentoIndex = $index;
        $this->formaSelecionadaId = (int) ($this->meiosPagamento[$index]['id'] ?? 0);

        if (is_string($valorSugerido) && trim($valorSugerido) !== '') {
            $this->meiosPagamento[$index]['valor'] = ErpMoney::formatBr(ErpMoney::parseBr($valorSugerido, 2), 2);
        }

        $this->skipRender();
    }

    /**
     * Antes do F10: alinha valores visíveis + índice local (navegação sem request por seta).
     *
     * @param  array<int|string, string>  $valores
     */
    public function flushValoresPagamento(array $valores, ?int $selectedIndex = null, bool $skipRender = true): void
    {
        foreach ($valores as $index => $raw) {
            $i = (int) $index;

            if (! isset($this->meiosPagamento[$i])) {
                continue;
            }

            $this->normalizarValorPagamentoLinha($i, (string) $raw);
        }

        if ($selectedIndex !== null && isset($this->meiosPagamento[$selectedIndex])) {
            $this->selectedPagamentoIndex = $selectedIndex;
            $this->formaSelecionadaId = (int) ($this->meiosPagamento[$selectedIndex]['id'] ?? 0);
        }

        if ($skipRender) {
            $this->skipRender();
        }
    }

    /**
     * Atalho/Enter local + abre canhoto/carnê no servidor se necessário (UI já atualizou).
     *
     * @param  array<int|string, string>  $valores
     */
    public function aplicarPagamentoLocalEEspeciais(array $valores, int $index): void
    {
        if ($this->etapa !== 'finalizacao') {
            return;
        }

        if (! isset($this->meiosPagamento[$index])) {
            $this->flushValoresPagamento($valores, $index, skipRender: true);

            return;
        }

        $meioPreview = $this->meiosPagamento[$index];
        // Cartão ainda depende do remorph do canhoto; carnê abre via Alpine + skipRender.
        $abreCartao = PdvFinalizarPagamentosHelper::isFormaCartao($meioPreview);

        $this->flushValoresPagamento($valores, $index, skipRender: ! $abreCartao);

        if (! isset($this->meiosPagamento[$index])) {
            $this->skipRender();

            return;
        }

        $meio = $this->meiosPagamento[$index];
        $valor = ErpMoney::parseBr($meio['valor'] ?? '0');

        if ($valor > 0.009 && $abreCartao) {
            $this->finalizarCartaoCanhotoConfirmado = false;
            $this->finalizarCartaoParcelasRows = [];
            $this->ensureCartaoCanhoto();

            return;
        }

        if ($valor > 0.009 && PdvFinalizarPagamentosHelper::precisaParcelasCarne($this->fvPagamentoComoPdv($meio))) {
            $this->fvParcelasRows = [];
            $this->ensureFvTabelaPrazoCrediario();

            return;
        }

        $this->skipRender();
    }

    /**
     * Enter especial (cartão/carnê/TEF): 1 round-trip flush + confirmarValorPagamentoLinha.
     *
     * @param  array<int|string, string>  $valores
     */
    public function confirmarValorPagamentoLinhaComFlush(array $valores, int $index, string $valor): void
    {
        if ($this->etapa !== 'finalizacao') {
            return;
        }

        $this->flushValoresPagamento($valores, $index, skipRender: false);
        $this->confirmarValorPagamentoLinha($valor, $index);
    }

    /**
     * Concluir em 1 round-trip: flush cliente/CPF/valores + faturarPedido (fluxo oficial).
     *
     * @param  array<int|string, string>  $valores
     */
    public function faturarPedidoComFlush(
        array $valores,
        ?int $selectedIndex,
        string $clienteBusca,
        string $cpfNota,
        string $observacoes,
    ): void {
        if ($this->etapa !== 'finalizacao' || $this->gravando) {
            return;
        }

        // Flush sem skipRender: falhas de cliente/CPF/pagamento ainda remorpham e
        // exibem Notification + estado digitado. Só após validações OK evitamos
        // remorph da grade durante o faturamento (PC fraco).
        $this->flushValoresPagamento($valores, $selectedIndex, skipRender: false);

        if (! $this->sincronizarClienteFinalizacaoBusca($clienteBusca, skipRender: false)) {
            return;
        }

        if (! $this->flushCpfNotaEObservacoes($cpfNota, $observacoes, true, skipRender: false)) {
            return;
        }

        // Estado já sincronizado; faturar + redirect/notify sem remontar a grade.
        $this->skipRender();
        $this->faturarPedido();
    }

    /**
     * Avanço programático (ex.: após Enter). Nunca preenche restante —
     * restante só via atalho A/P/D/C/K.
     */
    public function movePagamentoSelection(int $delta, bool $sugerirRestante = false): void
    {
        $count = count($this->meiosPagamento);

        if ($count === 0) {
            return;
        }

        $index = $this->selectedPagamentoIndex + $delta;
        $index = max(0, min($count - 1, $index));
        $this->selectedPagamentoIndex = $index;
        $this->formaSelecionadaId = (int) ($this->meiosPagamento[$index]['id'] ?? 0);

        // Parâmetro legado ignorado: Enter/seta não preenchem restante.
        unset($sugerirRestante);

        $this->dispatch('erp-fv-focus-pagamento', index: $index);
    }

    /**
     * Único caminho que preenche o restante automaticamente (tecla ou botão do atalho).
     * Não sobrescreve valor já digitado (> 0) — ver aplicarValorRestanteNaForma.
     */
    public function selectPagamentoByAtalho(string $atalho): void
    {
        $atalho = strtoupper(trim($atalho));

        foreach ($this->meiosPagamento as $index => $meio) {
            if (strtoupper((string) ($meio['atalho'] ?? '')) === $atalho) {
                if (ErpMoney::parseBr($meio['valor'] ?? '0') > 0.009) {
                    // Já tem valor: só seleciona/foca, sem recalcular.
                    $this->selectedPagamentoIndex = $index;
                    $this->formaSelecionadaId = (int) ($meio['id'] ?? 0);
                    $this->dispatch('erp-fv-focus-pagamento', index: $index);

                    return;
                }

                $this->aplicarValorRestanteNaForma($index);

                return;
            }
        }
    }

    /**
     * Enter no valor: grava o digitado e segue o fluxo do PDV
     * (canhoto cartão → próxima linha → foco no botão de confirmar).
     * $index vem do input (DOM) — não depende de selectedPagamentoIndex atrasado das setas.
     */
    public function confirmarValorPagamentoLinha(?string $valorFromInput = null, ?int $index = null): void
    {
        if ($index !== null && isset($this->meiosPagamento[$index])) {
            $this->selectedPagamentoIndex = $index;
            $this->formaSelecionadaId = (int) ($this->meiosPagamento[$index]['id'] ?? 0);
        }

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
                // Modal aberto (ou bloqueio) já com skipRender em abrirFvParcelasCrediario.
                return;
            }
        } elseif ($valor > 0) {
            // Troca de parcelado/cartão para à vista: zera prazo na tela.
            $this->limparPrazoVendaAVista();
        }

        if ($valor > 0 && PdvFinalizarPagamentosHelper::isFormaTef($meio)) {
            $count = count($this->meiosPagamento);

            if ($this->selectedPagamentoIndex < $count - 1) {
                // Enter só avança o foco — não preenche restante.
                $this->movePagamentoSelection(1, false);
            }

            return;
        }

        if ($this->valorRestante() <= 0) {
            // skipRender: evita remorph que devolve o foco ao valor após ir ao Concluir.
            $this->skipRender();
            $this->dispatch('erp-fv-focus-finalizar-ok');

            return;
        }

        $count = count($this->meiosPagamento);

        if ($this->selectedPagamentoIndex < $count - 1) {
            $next = $this->selectedPagamentoIndex + 1;
            $this->selectedPagamentoIndex = $next;
            $this->formaSelecionadaId = (int) ($this->meiosPagamento[$next]['id'] ?? 0);
            $this->skipRender();
            $this->dispatch('erp-fv-focus-pagamento', index: $next);
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
     * Mesmo fluxo do F3; o faturamento fica para o monitor.
     *
     * @param  bool  $aoSair  Quando true (botão Sair), usa aviso amarelo de atenção.
     */
    public function gravarPedidoPendente(bool $aoSair = false, ?string $qtdRaw = null, ?string $precoRaw = null): void
    {
        if ($this->gravando || $this->isConfirmingItem || $this->tabelaProgressOpen) {
            return;
        }

        // Trava cedo contra duplo F3/Sair (Livewire pode enfileirar dois requests).
        $this->gravando = true;

        try {
            $this->assertContextoEmpresaOperavel();
        } catch (\Throwable $e) {
            $this->gravando = false;
            $this->fvSairNavigateUrl = null;
            $this->rearmarGuardSaidaTelaVenda();
            Notification::make()->title($e->getMessage())->warning()->send();

            return;
        }

        // Edição aberta: confirma na barra (mesmo fluxo do Enter) antes de gravar.
        if ($this->editingItemKey !== null) {
            $this->adicionarItem($qtdRaw, $precoRaw);
            if ($this->editingItemKey !== null) {
                $this->gravando = false;
                if ($aoSair) {
                    $this->fvSairNavigateUrl = null;
                    $this->rearmarGuardSaidaTelaVenda();
                }

                return;
            }
        }

        if (
            $this->descontoModalOpen
            || $this->excluirItemModalOpen
            || $this->fvImportarOrcamentoOpen
            || $this->margemModalOpen
            || $this->fvImprimirPosVendaOpen
            || ($this->fvSairConfirmOpen && ! $aoSair)
        ) {
            $this->gravando = false;
            $this->fvSairNavigateUrl = null;
            $this->rearmarGuardSaidaTelaVenda();

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
                $this->gravando = false;
                $this->fvSairNavigateUrl = null;
                $this->rearmarGuardSaidaTelaVenda();
                Notification::make()->title('Selecione o cliente.')->warning()->send();

                return;
            }
        }

        if ($this->itens === []) {
            $this->gravando = false;
            $this->fvSairNavigateUrl = null;
            $this->rearmarGuardSaidaTelaVenda();
            Notification::make()->title('Inclua ao menos um item.')->warning()->send();

            return;
        }

        if ($this->bloquearSeOperacaoInvalida(exigeEstoque: $this->pedidoExigeEstoque())) {
            $this->gravando = false;
            $this->fvSairNavigateUrl = null;
            $this->rearmarGuardSaidaTelaVenda();

            return;
        }

        $user = Auth::user();

        if (! $user) {
            $this->gravando = false;
            $this->fvSairNavigateUrl = null;
            $this->rearmarGuardSaidaTelaVenda();

            return;
        }

        $this->fvSairConfirmOpen = false;

        try {
            $svc = app(ForcaVendasTelaVendaService::class);
            $existente = null;

            if ($this->pedidoId) {
                $existente = ForcaVendasOrder::query()->find($this->pedidoId);

                if (! $existente) {
                    throw new \RuntimeException('Pedido em edição não encontrado.');
                }

                $svc->assertOrderDaEmpresa($existente, (int) $this->vendaEmpresaId);
            }

            // Revalida sessão × empresa congelada imediatamente antes da TX.
            $this->assertContextoEmpresaOperavel();

            if ($this->operacaoUuid === '') {
                $this->operacaoUuid = (string) Str::uuid();
            }

            $order = $this->gravarPedidoComIdempotencia($svc, $user, [
                'uuid' => $this->operacaoUuid,
                'empresa_id' => (int) $this->vendaEmpresaId,
                'orcamento_id' => $this->orcamentoOrigemId,
                'cliente_id' => (int) $this->clienteId,
                'vendedor_id' => $this->vendedorId,
                'caixa_conta_id' => $this->caixaId,
                'estoque_id' => $this->estoqueId,
                'observacoes' => trim($this->observacoes) !== '' ? trim($this->observacoes) : null,
                'cpf_nota' => trim($this->cpfNota) !== '' ? trim($this->cpfNota) : null,
                'desconto_valor' => 0,
                'percentual_desconto' => 0,
                'forma_pagamento_id' => null,
                'forma_pagamento' => null,
                'tabela_prazo_dias' => $this->tabelaPrazoDias,
                'cartao_canhoto' => null,
                'transporte' => $this->fvTransportePayload(),
                'itens' => $this->itensParaGravar(),
            ], $existente);

            if (! $existente) {
                $this->pedidoId = (int) $order->id;
            }

            if ($order->orcamento_id) {
                $this->orcamentoOrigemId = (int) $order->orcamento_id;
            }

            $dav = $order->pedido?->numero;

            // Sair: aviso amarelo de atenção. F3: sucesso verde (comportamento atual).
            if ($aoSair) {
                Notification::make()
                    ->title($existente
                        ? 'Pedido atualizado e mantido como pendente.'
                        : 'Venda gravada como pendente.')
                    ->body('DAV Nº '.$dav.' — aguardando faturamento no monitor. Você pode abrir, concluir ou cancelar pelo Monitor.')
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title($existente ? 'Pedido atualizado.' : 'Pedido gravado como pendente.')
                    ->body('DAV Nº '.$dav.' — aguardando faturamento no monitor.')
                    ->success()
                    ->send();
            }

            // Destino: URL da navegação SPA (se houver) ou Monitor (botão Sair / F3).
            $destino = $this->fvSairNavigateUrl
                ?: ForcaVendasMonitorResource::getUrl('index');
            $this->fvSairNavigateUrl = null;

            // JS já liberou no clique; reforça allowLeave até o redirect SPA.
            $this->liberarSaidaTelaVenda();
            $this->redirect($destino);
        } catch (\Throwable $e) {
            $this->gravando = false;
            $this->fvSairNavigateUrl = null;
            $this->rearmarGuardSaidaTelaVenda();
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

        // Trava cedo contra duplo F10/Concluir durante validações.
        $this->gravando = true;

        try {
            $this->assertContextoEmpresaOperavel();
        } catch (\Throwable $e) {
            $this->gravando = false;
            Notification::make()->title($e->getMessage())->warning()->send();

            return;
        }

        if ($this->etapa === 'finalizacao') {
            if ($this->clienteId === null) {
                $this->gravando = false;
                Notification::make()->title('Selecione o cliente.')->warning()->send();

                return;
            }

            if (! $this->clienteJaSelecionadoCorresponde(trim((string) $this->clienteBusca))) {
                $this->gravando = false;
                $this->restaurarClienteBuscaSelecionado();
                Notification::make()
                    ->title('Selecione o cliente na lista.')
                    ->body('A troca só vale após escolher um cadastro válido.')
                    ->warning()
                    ->send();
                $this->dispatch('erp-fv-fin-cliente-synced', value: $this->clienteBusca);

                return;
            }

            if ($msgCpf = (new PdvNotaClienteService())->validaDocumentoCpfNota(trim($this->cpfNota))) {
                $this->gravando = false;
                Notification::make()->title($msgCpf)->warning()->send();

                return;
            }
        }

        // Restante automático só por atalho — F10 não preenche sozinho.
        $totalInformado = $this->totalInformado();
        $totalLiquido = $this->totalLiquido();

        if ($totalInformado + 0.009 < $totalLiquido) {
            $this->gravando = false;
            Notification::make()
                ->title('Informe o valor nos meios de pagamento.')
                ->body('Valor restante: R$ '.$this->formatMoney($this->valorRestante()))
                ->warning()
                ->send();

            return;
        }

        $pagamentosPositivos = $this->pagamentosPositivosParaFaturar();

        if ($pagamentosPositivos === []) {
            $this->gravando = false;
            Notification::make()->title('Selecione um meio de pagamento.')->warning()->send();

            return;
        }

        if ($msgTroco = $this->validarExcessoPagamentoNaoMonetario($totalInformado, $totalLiquido, $pagamentosPositivos)) {
            $this->gravando = false;
            Notification::make()
                ->title('Pagamento inválido.')
                ->body($msgTroco)
                ->warning()
                ->send();

            return;
        }

        $formaPrincipal = $pagamentosPositivos[0];

        if ($faturar) {
            try {
                $clienteGuard = $this->clienteId
                    ? Person::query()->find((int) $this->clienteId)
                    : null;

                foreach ($this->meiosPagamento as $meio) {
                    if (ErpMoney::parseBr($meio['valor'] ?? '0') <= 0) {
                        continue;
                    }

                    ConsumidorFinalGuard::assertPagamentoPermitido($clienteGuard, $meio);
                }
            } catch (\DomainException $e) {
                $this->gravando = false;
                Notification::make()
                    ->title($e->getMessage())
                    ->warning()
                    ->send();

                return;
            }
        }

        if ($msg = $this->validaCartaoCanhotoFinalizar()) {
            $this->gravando = false;
            Notification::make()->title($msg)->warning()->send();
            $this->ensureCartaoCanhoto();

            return;
        }

        if (! $this->ensureFvTabelaPrazoCrediario(abrirSeNecessario: true)) {
            $this->gravando = false;

            return;
        }

        if ($this->bloquearSeOperacaoInvalida(exigeEstoque: $this->pedidoExigeEstoque())) {
            $this->gravando = false;

            return;
        }

        $user = Auth::user();

        if (! $user) {
            $this->gravando = false;

            return;
        }

        try {
            $svc = app(ForcaVendasTelaVendaService::class);
            $existente = null;

            if ($this->pedidoId) {
                $existente = ForcaVendasOrder::query()->find($this->pedidoId);

                if (! $existente) {
                    throw new \RuntimeException('Pedido em edição não encontrado.');
                }

                $svc->assertOrderDaEmpresa($existente, (int) $this->vendaEmpresaId);
            }

            // Revalida sessão × empresa congelada imediatamente antes da TX.
            $this->assertContextoEmpresaOperavel();

            $canhoto = $this->finalizarCartaoCanhotoPayload();
            $tabelaPrazo = $this->tabelaPrazoDias;
            $temCartao = false;
            $temCarne = false;

            foreach ($pagamentosPositivos as $pagMeio) {
                $pdvMeio = $this->fvPagamentoComoPdv($pagMeio);
                if (PdvFinalizarPagamentosHelper::isFormaCartao($pdvMeio)) {
                    $temCartao = true;
                }
                if (PdvFinalizarPagamentosHelper::precisaParcelasCarne($pdvMeio)) {
                    $temCarne = true;
                }
            }

            $aVista = ! $temCartao && ! $temCarne;

            // À vista / sem carnê: limpa prazo residual da UI e do payload.
            if ($aVista) {
                $this->limparPrazoVendaAVista();
                $tabelaPrazo = null;
                $canhoto = null;
            } elseif (
                $temCartao
                && ! $temCarne
                && ($canhoto === null || empty($canhoto['dias']))
            ) {
                $this->tabelaPrazoDias = null;
                $this->resetFvParcelasFaturamento();
                $tabelaPrazo = null;
            } elseif ($canhoto !== null && ! empty($canhoto['dias']) && is_array($canhoto['dias'])) {
                $tabelaPrazo = implode(',', array_map('intval', $canhoto['dias']));
            }

            if ($this->operacaoUuid === '') {
                $this->operacaoUuid = (string) Str::uuid();
            }

            $rotuloFormaCabecalho = $this->rotuloFormaPagamentoCabecalho($pagamentosPositivos);

            $dadosGravar = [
                'uuid' => $this->operacaoUuid,
                'empresa_id' => (int) $this->vendaEmpresaId,
                'orcamento_id' => $this->orcamentoOrigemId,
                'cliente_id' => (int) $this->clienteId,
                'vendedor_id' => $this->vendedorId,
                'caixa_conta_id' => $this->caixaId,
                'estoque_id' => $this->estoqueId,
                'observacoes' => trim($this->observacoes) !== '' ? trim($this->observacoes) : null,
                'cpf_nota' => trim($this->cpfNota) !== '' ? trim($this->cpfNota) : null,
                // Desconto/acréscimo do pedido já vão rateados nos itens.
                'desconto_valor' => 0,
                'percentual_desconto' => 0,
                'forma_pagamento_id' => (int) $formaPrincipal['id'],
                'forma_pagamento' => $rotuloFormaCabecalho,
                // Detalhe misto: cada meio com valor real (faturamento lê isto).
                'pagamentos' => $pagamentosPositivos,
                'tabela_prazo_dias' => $tabelaPrazo,
                'cartao_canhoto' => $canhoto,
                'transporte' => $this->fvTransportePayload(),
                'itens' => $this->itensParaGravar(),
            ];

            // Só força null na condição quando à vista (parcelado preserva Prazo Avulso do app).
            if ($aVista) {
                $dadosGravar['condicao_pagamento'] = null;
            }

            $order = $this->gravarPedidoComIdempotencia(
                $svc,
                $user,
                $dadosGravar,
                $existente,
                $faturar
                    ? function (ForcaVendasOrder $orderGravado) use ($existente): void {
                        if (! $existente) {
                            $this->pedidoId = (int) $orderGravado->id;
                        }

                        if ($orderGravado->orcamento_id) {
                            $this->orcamentoOrigemId = (int) $orderGravado->orcamento_id;
                        }

                        // Mantém o Cache::lock durante estoque + CR (anti duplo F10).
                        $this->finalizarFaturamento($orderGravado);
                    }
                    : null,
            );

            if (! $faturar) {
                if (! $existente) {
                    $this->pedidoId = (int) $order->id;
                }

                if ($order->orcamento_id) {
                    $this->orcamentoOrigemId = (int) $order->orcamento_id;
                }

                Notification::make()
                    ->title($existente ? 'Pedido atualizado.' : 'Pedido gravado.')
                    ->body('DAV Nº '.$order->pedido?->numero.' — aguardando faturamento no monitor.')
                    ->success()
                    ->send();

                $this->liberarSaidaTelaVenda();
                $this->redirect(ForcaVendasMonitorResource::getUrl('index'));

                return;
            }
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

        // Empresa da sessão × pedido × contexto congelado (antes da TX de faturamento).
        $this->assertContextoEmpresaOperavel();
        app(ForcaVendasTelaVendaService::class)->assertOrderDaEmpresa($order, (int) $this->vendaEmpresaId);

        $restricao = $this->restricaoFinanceira($order);

        if ($restricao !== null) {
            $this->marcarPedidoFinanceiro($order, $restricao);

            Notification::make()
                ->title('Pedido em análise financeira.')
                ->body('DAV Nº '.$order->pedido->numero.' retido: '.$restricao)
                ->warning()
                ->send();

            $this->liberarSaidaTelaVenda();
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

        $contas = $resultado['contas_receber'] ?? [];
        $this->encerrarAposFaturamentoSucesso((int) $order->id, $contas);
    }

    /**
     * Destino pós-faturamento TV: boleto → impressão → permanecer/Monitor.
     *
     * @param  list<mixed>  $contas
     */
    private function encerrarAposFaturamentoSucesso(int $orderIdFaturado, array $contas): void
    {
        $empresaId = $this->vendaEmpresaId
            ? (int) $this->vendaEmpresaId
            : ErpContext::currentEmpresaId();
        $monitorUrl = ForcaVendasMonitorResource::getUrl('index');
        $perguntarImprimir = EmpresaParametros::perguntarImprimirPosVendaTv($empresaId);
        $permanecer = EmpresaParametros::permanecerTelaVendaAposConcluir($empresaId);

        $this->fvAposConcluirPermanecer = $permanecer;

        // Fica na TV se for perguntar impressão ou permanecer (boleto não manda ao Monitor).
        $redirectAposBoleto = ($perguntarImprimir || $permanecer) ? null : $monitorUrl;

        if ($this->offerEmitirBoletosPosDocumento($contas, $redirectAposBoleto)) {
            $this->gravando = false;
            $this->limparTelaAposFaturamentoSucesso();

            if ($perguntarImprimir) {
                $this->prepararImprimirPosVenda($orderIdFaturado, $monitorUrl);
                $this->fvImprimirPosVendaAposBoleto = true;
            }

            return;
        }

        $this->gravando = false;

        if ($perguntarImprimir) {
            $this->prepararImprimirPosVenda($orderIdFaturado, $monitorUrl);

            if ($permanecer) {
                $this->reiniciarVendaAposConcluir(preservarImpressao: true);
            } else {
                $this->limparTelaAposFaturamentoSucesso();
            }

            $this->fvImprimirPosVendaOpen = true;
            $this->dispatch('erp-fv-focus-imprimir-pos-venda');

            return;
        }

        if ($permanecer) {
            $this->reiniciarVendaAposConcluir(preservarImpressao: false);
            $this->fvAposConcluirPermanecer = false;

            return;
        }

        $this->fvAposConcluirPermanecer = false;
        $this->limparTelaAposFaturamentoSucesso();
        $this->redirect($monitorUrl);
    }

    /**
     * Após boletos (ou dispensa): pergunta impressão, nova venda ou Monitor.
     */
    protected function afterBoletoPosDocumentoFluxo(): void
    {
        if ($this->fvImprimirPosVendaAposBoleto && $this->fvImprimirPosVendaOrderId) {
            $this->fvImprimirPosVendaAposBoleto = false;
            $orderId = (int) $this->fvImprimirPosVendaOrderId;
            $monitorUrl = $this->fvImprimirPosVendaMonitorUrl !== ''
                ? $this->fvImprimirPosVendaMonitorUrl
                : ForcaVendasMonitorResource::getUrl('index');

            $this->prepararImprimirPosVenda($orderId, $monitorUrl);

            if ($this->fvAposConcluirPermanecer) {
                $this->reiniciarVendaAposConcluir(preservarImpressao: true);
            }

            $this->fvImprimirPosVendaOpen = true;
            $this->dispatch('erp-fv-focus-imprimir-pos-venda');

            return;
        }

        $this->finalizarDestinoAposConcluir();
    }

    private function limparTelaAposFaturamentoSucesso(): void
    {
        $this->liberarSaidaTelaVenda();
        $this->pedidoId = null;
        $this->davNumero = null;
        $this->itens = [];
        $this->etapa = 'venda';
        $this->meiosPagamento = [];
        $this->resetFvParcelasFaturamento();
        $this->resetFinalizarCartaoCanhoto();
    }

    /**
     * Nova venda na mesma TV após faturamento OK (sem reload completo).
     */
    private function reiniciarVendaAposConcluir(bool $preservarImpressao = false): void
    {
        $caixaId = $this->caixaId;
        $caixaLabel = $this->caixaLabel;
        $estoqueId = $this->estoqueId;
        $estoqueLabel = $this->estoqueLabel;
        $vendedorId = $this->vendedorId;
        $vendedorLabel = $this->vendedorLabel;
        $tabelaPrecoId = $this->tabelaPrecoId;
        $tabelaPrecoLabel = $this->tabelaPrecoLabel;

        $printSnap = null;
        if ($preservarImpressao) {
            $printSnap = [
                'open' => $this->fvImprimirPosVendaOpen,
                'bloqueado' => $this->fvImprimirPosVendaPopupBloqueado,
                'processando' => $this->fvImprimirPosVendaProcessando,
                'orderId' => $this->fvImprimirPosVendaOrderId,
                'url' => $this->fvImprimirPosVendaUrl,
                'monitorUrl' => $this->fvImprimirPosVendaMonitorUrl,
                'aposBoleto' => $this->fvImprimirPosVendaAposBoleto,
                'permanecer' => $this->fvAposConcluirPermanecer,
            ];
        }

        // Evita devolver orçamento já consumido no faturamento.
        $this->orcamentoOrigemId = null;
        $this->pedidoId = null;
        $this->fvVendaGeracao++;

        $this->resetarVenda();

        if (is_array($printSnap)) {
            $this->fvImprimirPosVendaOpen = (bool) $printSnap['open'];
            $this->fvImprimirPosVendaPopupBloqueado = (bool) $printSnap['bloqueado'];
            $this->fvImprimirPosVendaProcessando = (bool) $printSnap['processando'];
            $this->fvImprimirPosVendaOrderId = $printSnap['orderId'];
            $this->fvImprimirPosVendaUrl = (string) $printSnap['url'];
            $this->fvImprimirPosVendaMonitorUrl = (string) $printSnap['monitorUrl'];
            $this->fvImprimirPosVendaAposBoleto = (bool) $printSnap['aposBoleto'];
            $this->fvAposConcluirPermanecer = (bool) $printSnap['permanecer'];
        }

        // Restaura contexto operacional da sessão (quando ainda válido).
        if ($vendedorId) {
            $this->vendedorId = $vendedorId;
            $this->vendedorLabel = $vendedorLabel;
        }
        if ($caixaId) {
            $this->caixaId = $caixaId;
            $this->caixaLabel = $caixaLabel;
        }
        if ($estoqueId) {
            $this->estoqueId = $estoqueId;
            $this->estoqueLabel = $estoqueLabel;
        }
        if ($tabelaPrecoId) {
            $this->tabelaPrecoId = $tabelaPrecoId;
            $this->tabelaPrecoLabel = $tabelaPrecoLabel;
        }

        if (! $this->iniciarContextoVendaNova()) {
            return;
        }

        $this->gravando = false;
        $this->editingItemKey = null;
        $this->descontoModalOpen = false;
        $this->excluirItemModalOpen = false;
        $this->fvImportarOrcamentoOpen = false;
        $this->tabPrecoItemModalOpen = false;
        $this->rearmarGuardSaidaTelaVenda();

        if (! $this->fvImprimirPosVendaOpen) {
            $this->focarCodigoProdutoNovaVenda();
        }
    }

    private function focarCodigoProdutoNovaVenda(): void
    {
        $this->js('window.__erpFvTvInitialFocusDone = true;');
        $this->dispatch('fv-tela-venda-focus-barcode');
    }

    private function prepararImprimirPosVenda(int $orderId, string $monitorUrl): void
    {
        $this->fvImprimirPosVendaOrderId = $orderId > 0 ? $orderId : null;
        $this->fvImprimirPosVendaMonitorUrl = $monitorUrl;
        $this->fvImprimirPosVendaUrl = $orderId > 0
            ? route('erp.reports.monitor-pedidos', [
                'ids' => (string) $orderId,
                'auto' => 1,
            ])
            : '';
        $this->fvImprimirPosVendaPopupBloqueado = false;
        $this->fvImprimirPosVendaProcessando = false;
    }

    public function confirmarImprimirPosVenda(): void
    {
        if (! $this->fvImprimirPosVendaOpen || $this->fvImprimirPosVendaProcessando) {
            return;
        }

        $this->fvImprimirPosVendaProcessando = true;
        $url = trim($this->fvImprimirPosVendaUrl);

        if ($url === '' || ! $this->fvImprimirPosVendaOrderId) {
            $this->finalizarAposPerguntaImprimir();

            return;
        }

        $urlJs = Js::from($url);
        $this->js(<<<JS
            (() => {
                let win = null;
                try { win = window.open({$urlJs}, '_blank'); } catch (e) { win = null; }
                if (!win || win.closed) {
                    \$wire.marcarPopupImpressaoBloqueado();
                    return;
                }
                \$wire.finalizarAposPerguntaImprimir();
            })();
        JS);
    }

    public function marcarPopupImpressaoBloqueado(): void
    {
        if (! $this->fvImprimirPosVendaOpen) {
            return;
        }

        $this->fvImprimirPosVendaPopupBloqueado = true;
        $this->fvImprimirPosVendaProcessando = false;
        $this->dispatch('erp-fv-focus-imprimir-pos-venda');
    }

    public function abrirComprovantePosVendaManual(): void
    {
        if (! $this->fvImprimirPosVendaOpen || $this->fvImprimirPosVendaUrl === '') {
            return;
        }

        if ($this->fvImprimirPosVendaProcessando) {
            return;
        }

        $this->fvImprimirPosVendaProcessando = true;
        $urlJs = Js::from($this->fvImprimirPosVendaUrl);
        $permanecerJs = $this->fvAposConcluirPermanecer ? 'true' : 'false';

        // Com "permanecer", não navega a aba atual (senão perde a nova venda).
        $this->js(<<<JS
            (() => {
                let win = null;
                try { win = window.open({$urlJs}, '_blank'); } catch (e) { win = null; }
                if (!win || win.closed) {
                    if ({$permanecerJs}) {
                        \$wire.marcarPopupImpressaoBloqueado();
                        return;
                    }
                    window.location.assign({$urlJs});
                    return;
                }
                \$wire.finalizarAposPerguntaImprimir();
            })();
        JS);
    }

    public function dispensarImprimirPosVenda(): void
    {
        if (! $this->fvImprimirPosVendaOpen || $this->fvImprimirPosVendaProcessando) {
            return;
        }

        $this->fvImprimirPosVendaProcessando = true;
        $this->finalizarAposPerguntaImprimir();
    }

    /** @deprecated Nome legado — use finalizarAposPerguntaImprimir(). */
    public function irAoMonitorAposImprimirPosVenda(): void
    {
        $this->finalizarAposPerguntaImprimir();
    }

    public function finalizarAposPerguntaImprimir(): void
    {
        $permanecer = $this->fvAposConcluirPermanecer;
        $this->resetImprimirPosVendaState();

        if ($permanecer) {
            $this->fvAposConcluirPermanecer = false;
            // Nova venda já foi reiniciada antes do modal (ou no pós-boleto).
            $this->focarCodigoProdutoNovaVenda();

            return;
        }

        $monitorUrl = ForcaVendasMonitorResource::getUrl('index');
        $this->redirect($monitorUrl);
    }

    private function finalizarDestinoAposConcluir(): void
    {
        if ($this->fvAposConcluirPermanecer) {
            $this->reiniciarVendaAposConcluir(preservarImpressao: false);
            $this->fvAposConcluirPermanecer = false;

            return;
        }

        $this->redirect(ForcaVendasMonitorResource::getUrl('index'));
    }

    private function resetImprimirPosVendaState(): void
    {
        $this->fvImprimirPosVendaOpen = false;
        $this->fvImprimirPosVendaPopupBloqueado = false;
        $this->fvImprimirPosVendaProcessando = false;
        $this->fvImprimirPosVendaOrderId = null;
        $this->fvImprimirPosVendaUrl = '';
        $this->fvImprimirPosVendaMonitorUrl = '';
        $this->fvImprimirPosVendaAposBoleto = false;
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

    /**
     * Mesmo parâmetro do PDV (`param_geral_rateio_pessoa_pdv`): só UI informativa no F4.
     */
    public function getFvRateioPessoaProperty(): bool
    {
        return (bool) (ErpContext::currentEmpresa()?->param_geral_rateio_pessoa_pdv ?? true);
    }

    public function getFvValorPorPessoaProperty(): string
    {
        $divisor = max(1, (int) ErpMoney::parseBr($this->fvDividirPor ?? '1', 0));

        return $this->formatMoney(round($this->totalLiquido() / $divisor, 2));
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

    /**
     * Linhas com valor > 0 no formato persistido no payload do pedido.
     *
     * @return list<array<string, mixed>>
     */
    private function pagamentosPositivosParaFaturar(): array
    {
        $out = [];

        foreach ($this->meiosPagamento as $meio) {
            $valor = ErpMoney::parseBr($meio['valor'] ?? '0');
            if ($valor <= 0.009) {
                continue;
            }

            $out[] = [
                'id' => (int) ($meio['id'] ?? 0),
                'forma_pagamento_id' => (int) ($meio['id'] ?? 0),
                'descricao' => (string) ($meio['descricao'] ?? ''),
                'forma_pagamento' => (string) ($meio['descricao'] ?? ''),
                'forma' => (string) ($meio['descricao'] ?? ''),
                'valor' => round($valor, 2),
                'valor_br' => ErpMoney::formatBr($valor, 2),
                'tipo' => (string) ($meio['tipo'] ?? ''),
                'tipo_movimento' => (string) ($meio['tipo_movimento'] ?? ''),
                'aparece_contas_receber' => (bool) ($meio['aparece_contas_receber'] ?? false),
                'atalho' => (string) ($meio['atalho'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $pagamentos
     */
    private function rotuloFormaPagamentoCabecalho(array $pagamentos): string
    {
        $nomes = [];
        foreach ($pagamentos as $pag) {
            $nome = mb_strtoupper(trim((string) ($pag['descricao'] ?? $pag['forma_pagamento'] ?? '')), 'UTF-8');
            if ($nome !== '') {
                $nomes[] = $nome;
            }
        }

        $nomes = array_values(array_unique($nomes));

        if ($nomes === []) {
            return 'DINHEIRO';
        }

        if (count($nomes) === 1) {
            return $nomes[0];
        }

        // Relatórios tratam "A / B" como misto.
        return implode(' / ', $nomes);
    }

    /**
     * Troco só pode vir de dinheiro. Excesso em cartão/PIX/boleto etc. é bloqueado.
     *
     * @param  list<array<string, mixed>>  $pagamentos
     */
    private function validarExcessoPagamentoNaoMonetario(
        float $totalInformado,
        float $totalLiquido,
        array $pagamentos,
    ): ?string {
        $excesso = round($totalInformado - $totalLiquido, 2);
        if ($excesso <= 0.009) {
            return null;
        }

        $somaDinheiro = 0.0;
        foreach ($pagamentos as $pag) {
            if ($this->meioEhDinheiro($pag)) {
                $somaDinheiro += round((float) ($pag['valor'] ?? 0), 2);
            }
        }

        if ($somaDinheiro + 0.009 < $excesso) {
            return 'Há valor a mais sem dinheiro correspondente para troco. '
                .'Ajuste os meios (troco só é permitido em dinheiro).';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meio
     */
    private function meioEhDinheiro(array $meio): bool
    {
        $tipo = mb_strtolower(trim((string) ($meio['tipo'] ?? '')), 'UTF-8');
        if ($tipo === 'dinheiro') {
            return true;
        }

        $desc = mb_strtoupper(trim((string) ($meio['descricao'] ?? $meio['forma'] ?? $meio['forma_pagamento'] ?? '')), 'UTF-8');

        return $desc === 'DINHEIRO' || str_contains($desc, 'DINHEIRO');
    }

    public function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    /**
     * Tecla de concluir venda (empresa) — padrão F10.
     */
    public function atalhoConcluirVenda(): string
    {
        return EmpresaParametros::atalhoConcluirVenda(
            $this->vendaEmpresaId ? (int) $this->vendaEmpresaId : ErpContext::currentEmpresaId()
        );
    }

    public function formatQty(float $value): string
    {
        return number_format($value, 3, ',', '.');
    }

    /**
     * Soma das quantidades lançadas (respeita agrupamento: 5+3 na mesma linha = 8).
     * Uso exclusivo do indicador visual do rodapé — não altera gravação/totais.
     */
    public function totalQuantidadeItens(): float
    {
        return round(array_sum(array_map(
            static fn (array $i): float => (float) ($i['quantidade'] ?? 0),
            $this->itens,
        )), 3);
    }

    /** Rótulo do rodapé: "10,000 itens" / "1,000 item". */
    public function rotuloQuantidadeItens(): string
    {
        $qtd = $this->totalQuantidadeItens();
        $sufixo = abs($qtd - 1.0) < 0.0005 ? 'item' : 'itens';

        return $this->formatQty($qtd).' '.$sufixo;
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
            $empresaSessao = ErpContext::currentEmpresaId();
            if ($empresaSessao === null || $empresaSessao <= 0) {
                throw new \RuntimeException('Nenhuma empresa selecionada na sessão.');
            }

            app(ForcaVendasTelaVendaService::class)->assertOrderDaEmpresa($order, (int) $empresaSessao);
            app(ForcaVendasTelaVendaService::class)->assertEditavel($order);
            $this->vendaEmpresaId = (int) $order->empresa_id;
            // Edição: UUID do pedido (idempotência de create não se aplica; update usa lockForUpdate).
            $this->operacaoUuid = filled($order->uuid) ? (string) $order->uuid : (string) Str::uuid();
            $this->orcamentoOrigemId = $order->orcamento_id ? (int) $order->orcamento_id : null;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não é possível editar este pedido.')
                ->body($e->getMessage())
                ->warning()
                ->send();
            $this->pedidoId = null;
            $this->vendaEmpresaId = null;
            $this->operacaoUuid = '';
            $this->orcamentoOrigemId = null;
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
        $cpfPayload = trim((string) ($payload['cpf_nota'] ?? ''));
        $this->cpfNota = $cpfPayload !== ''
            ? (PdvNotaClienteService::formatarCpfNota($cpfPayload) ?: $cpfPayload)
            : '';
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
                    // Só do payload explícito — DAV antigo sem flag NÃO é inferido como manual.
                    'preco_manual' => (bool) ($payloadItem['preco_manual'] ?? false),
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
                    'preco_manual' => (bool) ($raw['preco_manual'] ?? false),
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
        return app(\App\Support\ForcaVendas\FvClienteLookupService::class)->montarSugestoes($people);
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
        $empresaId = $this->vendaEmpresaId ?: ErpContext::currentEmpresaId();

        // Só operadores com RH (evita órfãos legados em vendedores). Value = vendedores.id.
        $vendedoresQuery = Vendedor::query()
            ->where('ativo', true)
            ->where('efetua_venda', true)
            ->whereHas('rhFuncionario')
            ->with('rhFuncionario');

        // Escopo da empresa da venda (congelada) ou sessão via pivot empresa_vendedor.
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

        // Caixa: carregado após o vendedor padrão (carregarCaixaDoVendedor), não aqui —
        // evita opcoesCaixaDaTela() + ensureCaixaGeral() duplicados na abertura.

        $tabSvc = app(ProductTabelaPrecoService::class);
        $ids = array_values($tabSvc->tabelasPadraoIds());

        $this->tabelaPrecoOpcoes = PriceTable::query()
            ->whereIn('id', $ids)
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
            $tabelaCliente = (int) ($this->priceTableIdDoCliente((int) $this->clienteId) ?? 0);

            if ($tabelaCliente > 0 && $this->aplicarTabelaPreco($tabelaCliente)) {
                return;
            }
        }

        // 2º Vendedor logado / selecionado.
        if ($this->vendedorId) {
            $tabelaVendedor = (int) ($this->priceTableIdDoVendedor((int) $this->vendedorId) ?? 0);

            if ($tabelaVendedor > 0 && $this->aplicarTabelaPreco($tabelaVendedor)) {
                return;
            }
        }

        $this->tabelaPrecoId = null;
        $this->tabelaPrecoLabel = '';
    }

    private function priceTableIdDoCliente(int $clienteId): ?int
    {
        if ($clienteId <= 0) {
            return null;
        }

        if (! array_key_exists($clienteId, $this->fvMemoPriceTableCliente)) {
            $raw = Person::query()->whereKey($clienteId)->value('price_table_id');
            $this->fvMemoPriceTableCliente[$clienteId] = $raw !== null ? (int) $raw : null;
        }

        $id = $this->fvMemoPriceTableCliente[$clienteId];

        return ($id !== null && $id > 0) ? $id : null;
    }

    private function priceTableIdDoVendedor(int $vendedorId): ?int
    {
        if ($vendedorId <= 0) {
            return null;
        }

        if (! array_key_exists($vendedorId, $this->fvMemoPriceTableVendedor)) {
            $raw = Vendedor::query()->whereKey($vendedorId)->value('tabela_venda_id');
            $this->fvMemoPriceTableVendedor[$vendedorId] = $raw !== null ? (int) $raw : null;
        }

        $id = $this->fvMemoPriceTableVendedor[$vendedorId];

        return ($id !== null && $id > 0) ? $id : null;
    }

    private function aplicarTabelaPreco(int $id): bool
    {
        // Reusa opção já carregada (evita SELECT repetido no mesmo request).
        $labelOpcao = null;
        foreach ($this->tabelaPrecoOpcoes as $op) {
            if ((int) ($op['id'] ?? 0) === $id) {
                $labelOpcao = (string) ($op['label'] ?? '');
                break;
            }
        }

        if ($labelOpcao !== null && $labelOpcao !== '') {
            $this->tabelaPrecoId = $id;
            $this->tabelaPrecoLabel = $labelOpcao;

            return true;
        }

        $tabela = PriceTable::query()->where('ativo', true)->find($id);

        if (! $tabela) {
            return false;
        }

        $this->tabelaPrecoId = (int) $tabela->id;
        $this->tabelaPrecoLabel = trim(($tabela->codigo ? $tabela->codigo.' - ' : '').($tabela->descricao ?? ''));

        array_unshift($this->tabelaPrecoOpcoes, [
            'id' => $this->tabelaPrecoId,
            'label' => $this->tabelaPrecoLabel,
        ]);

        return true;
    }

    public function updatedTabelaPrecoId(mixed $value): void
    {
        // Combo Alpine usa aplicarTabelaPrecoNaGrid (lote). Evita segunda execução.
        if ($this->tabelaProgressOpen) {
            return;
        }

        $this->aplicarTabelaPrecoNaGrid((int) $value);
    }

    /**
     * Inicia troca de tabela (legado / compat). Preferir {@see aplicarTabelaPrecoNaGrid()}.
     *
     * @return array{ok: bool, total: int, label: string}
     */
    public function iniciarAtualizacaoTabelaPreco(int $id): array
    {
        if ($this->tabelaProgressOpen) {
            return [
                'ok' => false,
                'total' => 0,
                'label' => '',
            ];
        }

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
     * Aplica o preço da tabela em um único item (compat / testes). Preferir lote.
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
            'promo_data_inicio', 'promo_data_fim',
        ]);

        $qtd = (float) ($item['quantidade'] ?? 0);
        $acrescimo = (float) ($item['acrescimo'] ?? 0);
        $desconto = (float) ($item['desconto'] ?? 0);
        $resolvido = $this->resolverUnitarioTrocaTabela($item, $product);
        $novoPreco = $resolvido['preco'];

        if ($resolvido['alterado']) {
            $this->tabelaRecalcAlterados++;
        }

        if (! $resolvido['preservado']) {
            $itens[$index]['preco_unitario'] = $novoPreco;
            $itens[$index]['total'] = round(($qtd * $novoPreco) + $acrescimo - $desconto, 2);
            $this->itens = array_values($itens);
        }

        $atual = $index + 1;

        return [
            'done' => $atual >= $total,
            'atual' => $atual,
            'total' => $total,
            'pct' => (int) round(($atual / max(1, $total)) * 100),
            'alterado' => $resolvido['alterado'],
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
     * Troca de tabela em lote: 1 request Livewire, produtos carregados de uma vez.
     * Mesma regra comercial de {@see aplicarPrecoItemNaGrid()} —
     * destino com preço válido aplica; destino zerado / preço manual preserva o unitário.
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

        $total = (int) ($start['total'] ?? 0);
        $label = (string) ($start['label'] ?? '');

        try {
            if ($total <= 0) {
                $this->finalizarAtualizacaoTabelaPreco(0);

                return [
                    'ok' => true,
                    'alterados' => 0,
                    'total' => 0,
                    'label' => $label,
                ];
            }

            $productIds = [];
            foreach ($this->itens as $item) {
                $pid = (int) ($item['product_id'] ?? 0);
                if ($pid > 0) {
                    $productIds[$pid] = $pid;
                }
            }

            /** @var \Illuminate\Support\Collection<int, Product> $products */
            $products = Product::query()
                ->whereIn('id', array_values($productIds))
                ->get([
                    'id', 'preco_venda', 'preco_atacado', 'preco_especial',
                    'qtd_atacado', 'usa_tab_preco', 'promo_preco_venda',
                    'promo_data_inicio', 'promo_data_fim',
                ])
                ->keyBy('id');

            $comTab = $products->filter(static fn (Product $p): bool => (bool) ($p->usa_tab_preco ?? false));
            if ($comTab->isNotEmpty()) {
                app(ProductTabelaPrecoService::class)->carregarItens($comTab->values());
            }

            // Resolve nível uma vez (evita N× nivelDaTabelaId no loop).
            $this->nivelTabelaPrecoSelecionada();

            $itens = $this->itens;
            $alterados = 0;

            foreach ($itens as $index => $item) {
                $qtd = (float) ($item['quantidade'] ?? 0);
                $acrescimo = (float) ($item['acrescimo'] ?? 0);
                $desconto = (float) ($item['desconto'] ?? 0);
                $productId = (int) ($item['product_id'] ?? 0);
                $product = $productId > 0 ? ($products->get($productId)) : null;
                $resolvido = $this->resolverUnitarioTrocaTabela($item, $product);

                if ($resolvido['alterado']) {
                    $alterados++;
                }

                if ($resolvido['preservado']) {
                    $precoAtual = round((float) ($item['preco_unitario'] ?? 0), 2);
                    $itens[$index]['total'] = round(($qtd * $precoAtual) + $acrescimo - $desconto, 2);

                    continue;
                }

                $novoPreco = $resolvido['preco'];
                $itens[$index]['preco_unitario'] = $novoPreco;
                $itens[$index]['total'] = round(($qtd * $novoPreco) + $acrescimo - $desconto, 2);
            }

            $this->itens = array_values($itens);
            $this->tabelaRecalcAlterados = $alterados;
            unset($this->totalBruto, $this->totalDescontosItens, $this->totalAcrescimosItens);

            $this->finalizarAtualizacaoTabelaPreco($alterados);

            return [
                'ok' => true,
                'alterados' => $alterados,
                'total' => $total,
                'label' => $label,
            ];
        } catch (\Throwable $e) {
            // Falha: libera o modal e não deixa a UI travada. Itens podem estar
            // parcialmente atualizados só se o exception ocorrer após o assign —
            // o assign é atômico no final do foreach (uma atribuição a $this->itens).
            $this->tabelaProgressOpen = false;
            $this->tabelaRecalcAlterados = 0;

            Notification::make()
                ->title('Não foi possível atualizar os preços da tabela.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return [
                'ok' => false,
                'alterados' => 0,
                'total' => $total,
                'label' => $label,
            ];
        }
    }

    /**
     * Preço do produto na tabela selecionada:
     * com usa_tab_preco → preço do nível habilitado (Varejo/Atacado/Especial da Precificação);
     * sem flag → preço padrão por nível da tabela da venda.
     */
    private function precoNaTabelaSelecionada(Product $product): ?float
    {
        return $this->precoProdutoNaTabela($product);
    }

    /**
     * Resolve o unitário na troca de tabela da Tela de Venda (somente esta tela).
     * - preço manual digitado → preserva
     * - nível de destino sem preço cadastrado (> 0) → preserva o unitário em uso
     *   (não aplica fallback do motor para varejo/cadastro)
     * - destino com preço válido → aplica {@see precoProdutoNaTabela()}
     *
     * @param  array<string, mixed>  $item
     * @return array{preco: float, alterado: bool, preservado: bool}
     */
    private function resolverUnitarioTrocaTabela(array $item, ?Product $product): array
    {
        $precoAtual = round((float) ($item['preco_unitario'] ?? 0), 2);
        $qtd = (float) ($item['quantidade'] ?? 0);

        if (! empty($item['preco_manual'])) {
            return ['preco' => $precoAtual, 'alterado' => false, 'preservado' => true];
        }

        if (! $product) {
            return ['preco' => $precoAtual, 'alterado' => false, 'preservado' => true];
        }

        $definido = $this->precoCadastradoNoNivelSelecionado($product, $qtd > 0 ? $qtd : 1);

        // Ex.: ATACADO 0,00 — mantém R$ 80,00 (não volta para cadastro 0,01).
        if ($definido === null) {
            return ['preco' => $precoAtual, 'alterado' => false, 'preservado' => true];
        }

        $novoPreco = $this->precoProdutoNaTabela($product, $qtd > 0 ? $qtd : 1);

        return [
            'preco' => $novoPreco,
            'alterado' => $precoAtual !== $novoPreco,
            'preservado' => false,
        ];
    }

    /**
     * Preço efetivamente cadastrado no nível da tabela selecionada, sem fallback para varejo.
     * null = nível sem preço definido (ex.: preco_atacado = 0,00).
     * Não altera o motor global — uso exclusivo na troca de tabela desta tela.
     */
    private function precoCadastradoNoNivelSelecionado(Product $product, float $quantidade = 1): ?float
    {
        $tabSvc = app(ProductTabelaPrecoService::class);
        $nivel = $tabSvc->nivelDaTabelaId($this->tabelaPrecoId) ?? ProductTabelaPrecoService::NIVEL_VAREJO;

        if ($product->usa_tab_preco ?? false) {
            $tabSvc->carregarItens($product);

            if (! $tabSvc->habilitaNivel($product, $nivel)) {
                return null;
            }

            $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
            $precos = app(ProductEmpresaPrecoService::class)->resolve(
                $product,
                $empresaId > 0 ? $empresaId : null,
            );

            $bruto = match ($nivel) {
                ProductTabelaPrecoService::NIVEL_ATACADO => (float) ($precos['preco_atacado'] ?? 0),
                ProductTabelaPrecoService::NIVEL_ESPECIAL => (float) ($precos['preco_especial'] ?? $product->preco_especial ?? 0),
                default => $tabSvc->resolvePrecoNivel(
                    $product,
                    ProductTabelaPrecoService::NIVEL_VAREJO,
                    $quantidade,
                    $empresaId > 0 ? $empresaId : null,
                ),
            };

            return $bruto > 0 ? round($bruto, 2) : null;
        }

        $bruto = match ($nivel) {
            ProductTabelaPrecoService::NIVEL_ATACADO => (float) ($product->preco_atacado ?? 0),
            ProductTabelaPrecoService::NIVEL_ESPECIAL => (float) ($product->preco_especial ?? 0),
            default => $this->precoVarejoProduto($product),
        };

        return $bruto > 0 ? round($bruto, 2) : null;
    }

    private function precoProdutoNaTabela(Product $product, float $quantidade = 1): float
    {
        $tabSvc = app(ProductTabelaPrecoService::class);
        $nivel = $tabSvc->nivelDaTabelaId($this->tabelaPrecoId) ?? ProductTabelaPrecoService::NIVEL_VAREJO;

        if ($product->usa_tab_preco ?? false) {
            $tabSvc->carregarItens($product);

            if ($tabSvc->habilitaNivel($product, $nivel)) {
                return $tabSvc->resolvePrecoNivel($product, $nivel, $quantidade);
            }

            $habilitados = $tabSvc->niveisHabilitados($product);

            if ($habilitados !== []) {
                return $tabSvc->resolvePrecoNivel($product, $habilitados[0], $quantidade);
            }
        }

        return $this->precoBasePorNivel($product, $nivel, $quantidade);
    }

    /**
     * @return 'varejo'|'atacado'|'especial'
     */
    private function nivelTabelaPrecoSelecionada(): string
    {
        return app(ProductTabelaPrecoService::class)->nivelDaTabelaId($this->tabelaPrecoId)
            ?? ProductTabelaPrecoService::NIVEL_VAREJO;
    }

    private function precoBasePorNivel(Product $product, string $nivel, float $quantidade = 1): float
    {
        $varejo = $this->precoVarejoProduto($product);

        return match ($nivel) {
            ProductTabelaPrecoService::NIVEL_ATACADO => $this->precoAtacadoProduto($product, $varejo),
            ProductTabelaPrecoService::NIVEL_ESPECIAL => $this->precoEspecialProduto($product, $varejo),
            default => $this->precoVarejoComAtacadoPorQtd($product, $varejo, $quantidade),
        };
    }

    private function abrirTabPrecoItemModal(Product $product, float $quantidade = 1): void
    {
        $tabSvc = app(ProductTabelaPrecoService::class);
        $tabSvc->carregarItens($product);
        $opcoes = $tabSvc->opcoesParaProduto($product, max(0.001, $quantidade));

        if ($opcoes === []) {
            Notification::make()
                ->title('Produto com Tab. Preço sem tabelas habilitadas. Revise o cadastro.')
                ->danger()
                ->send();

            return;
        }

        $this->tabPrecoItemProductId = (int) $product->id;
        $this->tabPrecoItemOpcoes = array_map(static fn (array $op): array => [
            'nivel' => $op['nivel'],
            'price_table_id' => $op['price_table_id'],
            'codigo' => $op['codigo'],
            'descricao' => $op['descricao'],
            'label' => $op['label'].' — R$ '.number_format($op['preco'], 2, ',', '.'),
            'preco' => $op['preco'],
        ], $opcoes);

        $selected = 0;

        foreach ($this->tabPrecoItemOpcoes as $index => $op) {
            if ((int) $op['price_table_id'] === (int) $this->tabelaPrecoId) {
                $selected = $index;
                break;
            }
        }

        $this->tabPrecoItemSelectedIndex = $selected;
        $this->tabPrecoItemModalOpen = true;
        $this->tabPrecoItemConfirmada = false;
    }

    public function selectTabPrecoItemRow(int $index): void
    {
        if (isset($this->tabPrecoItemOpcoes[$index])) {
            $this->tabPrecoItemSelectedIndex = $index;
            $this->skipRender();
        }
    }

    public function confirmTabPrecoItemAt(int $index): void
    {
        $this->tabPrecoItemSelectedIndex = $index;
        $this->confirmTabPrecoItem();
    }

    public function confirmTabPrecoItem(): void
    {
        $index = $this->tabPrecoItemSelectedIndex;

        if ($index === null || ! isset($this->tabPrecoItemOpcoes[$index])) {
            Notification::make()->title('Selecione uma tabela de preço.')->warning()->send();

            return;
        }

        $op = $this->tabPrecoItemOpcoes[$index];
        $productId = (int) ($this->tabPrecoItemProductId ?? 0);

        $this->aplicarTabelaPreco((int) $op['price_table_id']);
        $this->tabPrecoItemConfirmada = true;
        $this->tabPrecoItemModalOpen = false;

        $lista = (float) $op['preco'];
        $this->precoListaItem = $this->formatMoney($lista);
        $this->precoUnitario = $this->formatMoney($lista);
        $this->recalcularTotalItem();

        $this->tabPrecoItemProductId = null;
        $this->tabPrecoItemOpcoes = [];
        $this->tabPrecoItemSelectedIndex = null;

        // Mantém o produto no formulário e inclui na grade (igual ao PDV após escolher a tabela).
        if ($productId > 0) {
            $this->produtoAtualId = $productId;
            $this->adicionarItem();
        }
    }

    public function cancelTabPrecoItem(): void
    {
        $this->tabPrecoItemModalOpen = false;
        $this->tabPrecoItemProductId = null;
        $this->tabPrecoItemOpcoes = [];
        $this->tabPrecoItemSelectedIndex = null;
        $this->tabPrecoItemConfirmada = false;
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
        $digitado = $this->parseDecimal($this->precoUnitario);
        $listaAnterior = $this->parseDecimal($this->precoListaItem);
        $definido = $this->precoCadastradoNoNivelSelecionado($product, $qtd > 0 ? $qtd : 1);

        // Só acompanha a tabela se o operador ainda não havia digitado um valor ≠ lista.
        $precoManualmenteNaBarra = abs(round($digitado - $listaAnterior, 2)) >= 0.005
            && ($listaAnterior > 0 || $digitado > 0);

        // Destino sem preço (0,00): atualiza só a lista de referência; mantém o unitário em uso.
        if ($definido === null) {
            $this->precoListaItem = $this->formatMoney(0);
            $this->recalcularTotalItem();

            return;
        }

        $this->precoListaItem = $this->formatMoney($definido);

        if (! $precoManualmenteNaBarra) {
            $this->precoUnitario = $this->formatMoney(
                $this->precoProdutoNaTabela($product, $qtd > 0 ? $qtd : 1)
            );
        }

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
        $vendedor = $user->vendedor()->with(['rhFuncionario', 'empresas'])->first();

        if ($vendedor && $this->vendedorOperacionalValido($vendedor)) {
            $this->aplicarVendedor($vendedor);

            return;
        }

        // 2º: sessão do PDV (se o operador já estava ativo neste terminal).
        $sessionVendedorId = (int) (session('erp.pdv.vendedor_id') ?? 0);

        if ($sessionVendedorId > 0) {
            $vendedor = Vendedor::query()
                ->with(['rhFuncionario', 'empresas'])
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
     * Grava com lock por operação (user + empresa + UUID) para F3/Sair/F10 create.
     * Update (existente) usa lockForUpdate no service; o Cache::lock ainda serializa
     * double-submit da mesma tela.
     *
     * Quando $aposGravar é informado (F10/faturar), o callback roda AINDA com o lock
     * — evita segundo request entrar no faturamento enquanto o primeiro baixa estoque/CR.
     *
     * @param  array<string, mixed>  $data
     * @param  (callable(ForcaVendasOrder): void)|null  $aposGravar
     */
    private function gravarPedidoComIdempotencia(
        ForcaVendasTelaVendaService $svc,
        \App\Models\User $user,
        array $data,
        ?ForcaVendasOrder $existente,
        ?callable $aposGravar = null,
    ): ForcaVendasOrder {
        $uuid = trim((string) ($data['uuid'] ?? $this->operacaoUuid));
        $empresaId = (int) ($this->vendaEmpresaId ?? 0);
        $lockKey = 'fv-tv-gravar:'.(int) $user->id.':'.$empresaId.':'.$uuid;
        // Faturar (estoque + N parcelas CR) pode passar de 25s em pedidos grandes.
        $lockTtl = $aposGravar !== null ? 120 : 25;
        $lock = Cache::lock($lockKey, $lockTtl);

        if (! $lock->get()) {
            throw new \RuntimeException('A gravação desta venda já está em andamento. Aguarde.');
        }

        try {
            $order = $svc->gravarPedido($user, $data, $existente);
            if ($aposGravar !== null) {
                $aposGravar($order);
            }

            return $order;
        } finally {
            $lock->release();
        }
    }

    /**
     * Garante que a sessão ainda é a empresa congelada da venda.
     * Bloqueia F3/F4/F10/Sair após troca de empresa em outra aba.
     *
     * @throws \RuntimeException
     */
    private function assertContextoEmpresaOperavel(): void
    {
        $vendaEmpresaId = (int) ($this->vendaEmpresaId ?? 0);

        if ($vendaEmpresaId <= 0) {
            throw new \RuntimeException(
                'Contexto de empresa da venda não definido. Recarregue a Tela de Venda.'
            );
        }

        if (! ErpContext::userCanAccessEmpresa($vendaEmpresaId)) {
            throw new \RuntimeException('Sem permissão para operar a empresa desta venda.');
        }

        $sessao = ErpContext::currentEmpresaId();

        if ($sessao === null || (int) $sessao !== $vendaEmpresaId) {
            throw new \RuntimeException(
                'A empresa ativa foi alterada. Retorne à empresa original da venda '
                .'antes de gravar, sair com pendente, fechar ou faturar.'
            );
        }
    }

    /**
     * Operador válido para NOVA venda (combo / padrão).
     */
    private function vendedorOperacionalValido(Vendedor $vendedor, ?int $empresaId = null): bool
    {
        if (! $vendedor->ativo || ! $vendedor->efetua_venda) {
            return false;
        }

        $vendedor->loadMissing('rhFuncionario');

        if (! $vendedor->rhFuncionario) {
            return false;
        }

        $empresaId ??= $this->vendaEmpresaId ?? ErpContext::currentEmpresaId();

        if (! $empresaId) {
            return false;
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
        // Sempre há destino: vínculo do usuário ou CAIXA GERAL.
        if ($this->caixaId === null || $this->caixaId <= 0) {
            $this->aplicarCaixaGeralPadrao();
        }

        return $this->caixaId !== null && $this->caixaId > 0;
    }

    private function bloquearSeOperacaoInvalida(bool $exigeEstoque = true): bool
    {
        $this->caixaOperacionalDefinido();

        if ($exigeEstoque && ! $this->estoqueOperacionalDefinido()) {
            $this->notificarEstoqueNaoDefinido();

            return true;
        }

        return false;
    }

    private function pedidoExigeEstoque(): bool
    {
        $productIds = [];

        foreach ($this->itens as $item) {
            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId > 0) {
                $productIds[$productId] = $productId;
            }
        }

        if ($productIds === []) {
            return false;
        }

        // 1 query: se existir qualquer produto físico, a venda exige depósito.
        return Product::query()
            ->whereIn('id', array_values($productIds))
            ->where(function ($q): void {
                $q->whereNull('is_servico')->orWhere('is_servico', false);
            })
            ->exists();
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

    /**
     * @return list<array{id: int, label: string, situacao: string}>
     */
    private function opcoesCaixaDaTela(?Vendedor $vendedor = null): array
    {
        $empresaId = $this->vendaEmpresaId
            ?: ErpContext::currentEmpresaId()
            ?: ((int) (Auth::user()?->empresa_id ?? 0) ?: null);

        $usuario = null;
        $vendedor ??= $this->vendedorId
            ? Vendedor::query()->with('usuario')->find((int) $this->vendedorId)
            : null;

        if ($vendedor) {
            $vendedor->loadMissing('usuario');
            $usuario = $vendedor->usuario;
        }

        if (! $usuario) {
            $usuario = Auth::user();
        }

        return app(VendaCaixaDestinoResolver::class)->opcoesParaUsuario(
            $usuario instanceof \App\Models\User ? $usuario : null,
            $empresaId,
        );
    }

    private function aplicarCaixaGeralPadrao(): void
    {
        $geral = CaixaConta::ensureCaixaGeral();
        $this->caixaId = (int) $geral->id;
        $this->caixaLabel = trim(($geral->codigo ? $geral->codigo.' - ' : '').($geral->nome ?? CaixaConta::NOME_CAIXA_GERAL));

        $existe = collect($this->caixaOpcoes)->contains(
            fn (array $op): bool => (int) ($op['id'] ?? 0) === $this->caixaId
        );

        if (! $existe) {
            array_unshift($this->caixaOpcoes, [
                'id' => $this->caixaId,
                'label' => $this->caixaLabel,
                'situacao' => (string) ($geral->situacao ?? ''),
            ]);
        }
    }

    private function carregarCaixaDoVendedor(Vendedor $vendedor): void
    {
        $empresaId = $this->vendaEmpresaId
            ?: ErpContext::currentEmpresaId()
            ?: ($vendedor->empresa_id ? (int) $vendedor->empresa_id : null)
            ?: ((int) (Auth::user()?->empresa_id ?? 0) ?: null);

        $this->caixaOpcoes = $this->opcoesCaixaDaTela($vendedor);

        // Somente Permissões → Caixas do usuário vinculado; senão CAIXA GERAL.
        $caixa = $empresaId ? $vendedor->caixaContaDaEmpresa((int) $empresaId) : null;

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

            return;
        }

        $this->aplicarCaixaGeralPadrao();
    }

    public function updatedVendedorId(mixed $value): void
    {
        $id = (int) $value;

        if ($id <= 0) {
            return;
        }

        try {
            $this->assertContextoEmpresaOperavel();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->warning()->send();

            return;
        }

        $vendedor = Vendedor::query()->with(['rhFuncionario', 'empresas', 'usuario'])->find($id);

        if (! $vendedor) {
            Notification::make()->title('Vendedor não encontrado.')->warning()->send();

            return;
        }

        if (! $this->vendedorOperacionalValido($vendedor, (int) $this->vendaEmpresaId)) {
            Notification::make()
                ->title('Vendedor não autorizado para a empresa desta venda.')
                ->warning()
                ->send();

            return;
        }

        // Ao trocar de vendedor, o caixa amarrado (ou GERAL) é sugerido de novo.
        $this->caixaId = null;
        $this->caixaLabel = '';
        $this->fvMemoPriceTableVendedor = [];
        $this->aplicarVendedor($vendedor);
        $this->caixaOperacionalDefinido();

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

        try {
            $this->assertContextoEmpresaOperavel();
        } catch (\Throwable $e) {
            $this->caixaId = null;
            $this->caixaLabel = '';
            Notification::make()->title($e->getMessage())->warning()->send();

            return;
        }

        $caixa = CaixaConta::query()->find($id);

        if (! $caixa) {
            Notification::make()->title('Caixa não encontrado.')->warning()->send();

            return;
        }

        $autorizados = collect($this->opcoesCaixaDaTela())
            ->pluck('id')
            ->map(fn ($cid): int => (int) $cid)
            ->all();

        if (! in_array((int) $caixa->id, $autorizados, true)) {
            Notification::make()
                ->title('Caixa não autorizado para a empresa desta venda.')
                ->warning()
                ->send();
            $this->caixaId = null;
            $this->caixaLabel = '';

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
        $cadastrado = \App\Support\Erp\FormaPagamentoAtalho::cadastradoUtilizavel($atalho, $usados);

        if ($cadastrado !== null) {
            $usados[$cadastrado] = true;

            return $cadastrado;
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

            return;
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
        $this->clienteWhatsapp = (string) ($person->fone1 ?? $person->celular1 ?? '');
        $this->clienteObservacoes = trim((string) ($person->observacoes ?? ''));
        $this->observacaoClienteModalOpen = false;
        $this->syncCpfNotaFromCliente($person);

        // Sempre realinha a tabela: cliente (se tiver) senão vendedor.
        $this->carregarTabelaPrecoPadrao();
    }

    /**
     * Troca efetiva de cliente: realinha CPF na Nota (não mantém documento de outra pessoa).
     * PF com CPF válido → preenche; CNPJ / CF sem CPF / inválido → limpa.
     * Pesquisa/clique sem selecionar não chama este método.
     */
    private function syncCpfNotaFromCliente(Person $person): void
    {
        $cpfParaNota = PdvNotaClienteService::extrairCpfParaNota(
            (string) ($person->cpf_cnpj ?? ''),
            (string) ($person->pessoa_tipo ?? Person::PESSOA_FISICA),
        );

        $this->cpfNota = $cpfParaNota ?? '';
    }

    /**
     * DOM → Livewire no F4 (CPF na Nota + mesma Observação do Pedido).
     * Sem remorph a cada tecla; chamado no flush antes de F10/atalhos/Voltar.
     *
     * @param  bool  $validarCpf  false no Voltar/Esc (preserva texto; F10 valida).
     */
    public function flushCpfNotaEObservacoes(
        string $cpfNota,
        string $observacoes,
        bool $validarCpf = true,
        bool $skipRender = true,
    ): bool {
        $raw = trim($cpfNota);
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if ($digits === '') {
            $this->cpfNota = '';
        } elseif (strlen($digits) === 11 && \App\Support\Erp\DocumentoBrasileiroValidator::isValidCpf($digits)) {
            $this->cpfNota = PdvNotaClienteService::formatarCpfNota($digits);
        } else {
            $this->cpfNota = $raw;
        }

        $this->observacoes = $this->observacaoPedidoCaixaAlta($observacoes);

        if ($validarCpf && ($msg = (new PdvNotaClienteService())->validaDocumentoCpfNota($this->cpfNota))) {
            Notification::make()->title($msg)->warning()->send();
            // Falha: nunca skipRender — o input/CPF precisa refletir no DOM.

            return false;
        }

        // Garante máscara após validação (vazio permanece vazio).
        if ($this->cpfNota !== '') {
            $this->cpfNota = PdvNotaClienteService::formatarCpfNota($this->cpfNota) ?: $this->cpfNota;
        }

        $this->persistirObservacaoPedidoGravada();
        $this->persistirCpfNotaNoPayload();
        if ($skipRender) {
            $this->skipRender();
        }
        $this->dispatch('erp-fv-fin-cpf-synced', value: $this->cpfNota);

        return true;
    }

    private function persistirCpfNotaNoPayload(): void
    {
        if (! $this->pedidoId) {
            return;
        }

        $order = ForcaVendasOrder::query()->find($this->pedidoId);

        if (! $order) {
            return;
        }

        try {
            $this->assertContextoEmpresaOperavel();
            app(ForcaVendasTelaVendaService::class)->assertOrderDaEmpresa($order, (int) $this->vendaEmpresaId);
            app(ForcaVendasTelaVendaService::class)->assertEditavel($order);
        } catch (\Throwable) {
            return;
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        $payload['cpf_nota'] = $this->cpfNota !== '' ? $this->cpfNota : null;
        $order->update(['payload' => $payload]);
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

        // Com Tab. Preço o foco fica no modal; senão skipRender + payload JS (sem remorph).
        if (! $this->tabPrecoItemModalOpen) {
            if ($this->parseDecimal($this->quantidade) <= 0) {
                $this->quantidade = '1,000';
            }
            $this->recalcularTotalItem();
            $this->skipRender();
            $this->dispatchFvItemStaged();
        }
    }

    private function dispatchFvItemStaged(): void
    {
        $this->dispatch('fv-tela-venda-item-staged', [
            'nome' => $this->produtoAtualNome,
            'codigo' => '',
            'qtd' => $this->quantidade,
            'preco' => $this->precoUnitario,
            'total' => $this->totalItem,
            'foto' => $this->produtoAtualFoto,
        ]);
        $this->dispatch('fv-tela-venda-focus-qtd');
    }

    /**
     * Payload para atualizar grade/totais no DOM após inclusão (sem remorph).
     *
     * @return array{item: array<string, mixed>, totais: array<string, string>, foto: ?string, nome: string}|null
     */
    private function fvItemIncludedPayload(bool $agrupado): ?array
    {
        $row = $this->itens[0] ?? null;

        if (! is_array($row)) {
            return null;
        }

        return [
            'item' => $this->fvItemRowPayload($row, $agrupado),
            'totais' => $this->fvTotaisPayload(),
            'foto' => $row['foto'] ?? $this->produtoAtualFoto,
            'nome' => (string) ($row['descricao'] ?? ''),
        ];
    }

    /**
     * Fallback Enter (código sem match exato) — mesmo serviço do GET de pesquisa.
     *
     * @return list<array{id: int, codigo: string, nome: string, atual: string, reservado: string, disponivel: string, preco: string, em_promocao?: bool}>
     */
    private function montarSugestoesProduto(string $term): array
    {
        return app(\App\Support\ForcaVendas\FvProdutoLookupService::class)->buscar(
            $term,
            ErpContext::currentEmpresaId(),
            $this->vendedorId,
            $this->estoqueId,
            $this->tabelaPrecoId,
        );
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
        $this->tabPrecoItemConfirmada = false;

        if ($product->usa_tab_preco ?? false) {
            $this->abrirTabPrecoItemModal($product, $this->parseDecimal($this->quantidade) ?: 1);

            return;
        }

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
        $this->editingItemKey = null;
        $this->editingItemSnapshot = null;
        $this->fecharSugestoesProduto();
    }

    /**
     * Contrato Tela de Venda (autorizado): o Valor Unitário digitado é o preço efetivo.
     * Não converte diferença em relação à tabela em desconto/acréscimo automático.
     * Desconto/acréscimo só entram via Ctrl+D (valores já na barra) ou linha já ajustada.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private function resolverPrecoDescontoAcrescimoDigitado(
        float $qtd,
        float $digitado,
        float $desconto,
        float $acrescimo,
    ): array {
        // $qtd permanece na assinatura por compatibilidade com os callers.
        return [$digitado, $desconto, $acrescimo];
    }

    /**
     * Ao mudar o Vlr. unit. para algo ≠ tabela sugerida, remove ajuste %/R$ da barra
     * para não manter desconto/acréscimo calculado sobre o preço anterior.
     * Ctrl+D depois da alteração de preço continua válido.
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
                'preco_manual' => (bool) ($item['preco_manual'] ?? false),
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
