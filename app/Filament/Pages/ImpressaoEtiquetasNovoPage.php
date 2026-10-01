<?php

namespace App\Filament\Pages;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Support\Erp\Dashboard\ErpDashboardCertificadoAlert;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Printing\Documents\GondolaEtiquetaPrintDocument;
use App\Support\Erp\Printing\EtiquetasPrintPrefs;
use App\Support\Erp\Printing\PrintFacade;
use App\Support\Erp\Printing\PrintTarget;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Product\ProductPriceHistoryRecorder;
use App\Support\Erp\Terminais\TerminalFormOptions;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ImpressaoEtiquetasNovoPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $title = '';

    protected static ?string $slug = 'impressao-etiquetas-novo';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('etiquetas.access');
    }

    public string $tipoBusca = 'codigo_barras';

    public string $termoBusca = '';

    /** @var list<array{id: int, codigo: string, codigo_barras: string, descricao: string}> */
    public array $produtoSugestoes = [];

    public bool $produtoSugestoesOpen = false;

    public int $selectedProdutoSugestaoIndex = 0;

    public int $qtdEtiquetas = 1;

    public string $modeloEtiqueta = 'gondola';

    public string $impressoraSelecionada = '';

    /** @var list<string> */
    public array $impressorasRaw = [];

    public string $filtroRomaneio = '';

    public ?int $filtroRomaneioId = null;

    public string $filtroNfEntrada = '';

    public ?int $filtroNfEntradaId = null;

    public bool $somentePrecoAlterados = false;

    public string $filtroValidade = '';

    public string $filtroAlteradosEm = '';

    /** @var ''|'romaneio'|'nf' */
    public string $docLookupTipo = '';

    public string $docLookupBusca = '';

    public string $docLookupData = '';

    /** @var list<array{id: int, numero: string, nota: string, data: string, fornecedor: string}> */
    public array $docLookupResults = [];

    /**
     * Lista local de impressão (não altera cadastro/estoque).
     *
     * @var list<array{
     *     uid: string,
     *     product_id: int,
     *     codigo: string,
     *     codigo_barras: string,
     *     descricao: string,
     *     preco: string,
     *     unidade: string,
     *     validade: string,
     *     quantidade: int|string
     * }>
     */
    public array $itensImpressao = [];

    public ?int $linhaSelecionada = null;

    public function mount(): void
    {
        ErpScreen::set('Etiquetas');

        $prefs = EtiquetasPrintPrefs::load();
        if ($prefs !== null) {
            if ($prefs['modelo'] !== '') {
                $this->modeloEtiqueta = $prefs['modelo'];
            }
            if ($prefs['impressora'] !== '') {
                $this->impressoraSelecionada = $prefs['impressora'];
                $this->impressorasRaw = [$this->impressoraSelecionada];

                return;
            }
        }

        $nome = PrintFacade::targetFromTerminal()->printerName;
        if (filled($nome)) {
            $fromRaw = TerminalFormOptions::windowsPrinterFromPorta($nome);
            $this->impressoraSelecionada = $fromRaw ?? (string) $nome;
            $this->impressorasRaw = [$this->impressoraSelecionada];
        }
    }

    public function salvarConfiguracoes(): void
    {
        EtiquetasPrintPrefs::save($this->modeloEtiqueta, $this->impressoraSelecionada);

        Notification::make()
            ->title('Configurações salvas.')
            ->success()
            ->send();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-list-page', 'erp-etiquetas-novo-page', 'erp-etiquetas-modal'];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.etiquetas-novo.screen'),
            ]);
    }

    public function limpar(): void
    {
        $this->termoBusca = '';
        $this->fecharSugestoesProduto();
        $this->qtdEtiquetas = 1;
        $this->filtroRomaneio = '';
        $this->filtroRomaneioId = null;
        $this->filtroNfEntrada = '';
        $this->filtroNfEntradaId = null;
        $this->somentePrecoAlterados = false;
        $this->filtroValidade = '';
        $this->filtroAlteradosEm = '';
        $this->itensImpressao = [];
        $this->linhaSelecionada = null;
        $this->fecharDocLookup();
    }

    public function updatedTermoBusca(): void
    {
        $term = mb_strtoupper(trim($this->termoBusca), 'UTF-8');
        if ($this->termoBusca !== $term) {
            $this->termoBusca = $term;
        }

        $minLen = $this->termoBuscaEhNumerico($term) ? 1 : 2;
        if (mb_strlen($term) < $minLen) {
            $this->fecharSugestoesProduto();

            return;
        }

        $this->produtoSugestoes = $this->montarSugestoesProduto($term);
        $this->produtoSugestoesOpen = $this->produtoSugestoes !== [];
        $this->selectedProdutoSugestaoIndex = 0;
    }

    public function moverSugestaoProduto(int $delta): void
    {
        if (! $this->produtoSugestoesOpen || $this->produtoSugestoes === []) {
            return;
        }

        $count = count($this->produtoSugestoes);
        $this->selectedProdutoSugestaoIndex = max(0, min($count - 1, $this->selectedProdutoSugestaoIndex + $delta));
    }

    public function fecharSugestoesProduto(): void
    {
        $this->produtoSugestoes = [];
        $this->produtoSugestoesOpen = false;
        $this->selectedProdutoSugestaoIndex = 0;
    }

    public function confirmarProdutoBusca(): void
    {
        if ($this->produtoSugestoesOpen && $this->produtoSugestoes !== []) {
            $index = $this->selectedProdutoSugestaoIndex;
            if (! isset($this->produtoSugestoes[$index])) {
                $index = 0;
            }
            $this->selecionarProdutoBusca((int) $this->produtoSugestoes[$index]['id']);

            return;
        }

        $term = trim($this->termoBusca);
        if ($term === '' || ! $this->termoBuscaEhNumerico($term)) {
            return;
        }

        $exact = Product::query()
            ->where('ativo', true)
            ->where(function (Builder $query) use ($term): void {
                $query->where('codigo', $term)
                    ->orWhere('codigo_barras', $term);
            })
            ->first();

        if ($exact) {
            $this->selecionarProdutoBusca((int) $exact->id);
        }
    }

    public function selecionarProdutoBusca(int $productId): void
    {
        $product = Product::query()
            ->where('ativo', true)
            ->find($productId);

        if (! $product) {
            Notification::make()->title('Produto não encontrado.')->warning()->send();

            return;
        }

        $this->adicionarProdutoImpressao($product, 1);
        $this->termoBusca = '';
        $this->fecharSugestoesProduto();
        $this->dispatch('erp-etiquetas-focus-busca');
    }

    /**
     * @return list<array{id: int, codigo: string, codigo_barras: string, descricao: string}>
     */
    protected function montarSugestoesProduto(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $limit = 20;
        $query = Product::query()->where('ativo', true);

        if ($this->termoBuscaEhNumerico($term)) {
            $prefix = $term.'%';
            $query->where(function (Builder $q) use ($term, $prefix): void {
                $q->where('codigo', $term)
                    ->orWhere('codigo', 'like', $prefix)
                    ->orWhere('codigo_barras', $term)
                    ->orWhere('codigo_barras', 'like', $prefix);
            })
                ->orderByRaw(
                    'CASE WHEN codigo = ? THEN 0 WHEN codigo_barras = ? THEN 1 WHEN codigo LIKE ? THEN 2 ELSE 3 END',
                    [$term, $term, $term.'%']
                )
                ->orderBy('descricao');
        } else {
            $parte = PdvConfig::make()->pesquisaPartesDescricao() ? '%' : '';
            $like = $parte.$term.'%';
            $query->where('descricao', 'like', $like)
                ->orderByRaw('CASE WHEN descricao LIKE ? THEN 0 ELSE 1 END', [$term.'%'])
                ->orderBy('descricao');
        }

        return $query
            ->limit($limit)
            ->get(['id', 'codigo', 'codigo_barras', 'descricao'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'codigo' => (string) ($product->codigo ?? ''),
                'codigo_barras' => (string) ($product->codigo_barras ?? ''),
                'descricao' => mb_strtoupper((string) ($product->descricao ?? ''), 'UTF-8'),
            ])
            ->values()
            ->all();
    }

    protected function termoBuscaEhNumerico(string $term): bool
    {
        return $term !== '' && ctype_digit($term);
    }

    protected function adicionarProdutoImpressao(Product $product, int $quantidade = 1): void
    {
        $productId = (int) $product->id;

        foreach ($this->itensImpressao as $item) {
            if ((int) ($item['product_id'] ?? 0) === $productId) {
                Notification::make()
                    ->title('Produto já está na lista.')
                    ->info()
                    ->send();

                return;
            }
        }

        $this->itensImpressao[] = [
            'uid' => uniqid('e', true),
            'product_id' => $productId,
            'codigo' => (string) ($product->codigo ?? ''),
            'codigo_barras' => (string) ($product->codigo_barras ?? ''),
            'descricao' => (string) ($product->descricao ?? ''),
            'preco' => number_format((float) ($product->preco_venda ?? 0), 2, ',', '.'),
            'unidade' => (string) ($product->unidade ?? ''),
            'validade' => $product->validade ? $product->validade->format('d/m/Y') : '',
            'quantidade' => max(1, $quantidade),
        ];

        $this->linhaSelecionada = count($this->itensImpressao) - 1;
    }

    public function selecionarLinha(int $index): void
    {
        if (! isset($this->itensImpressao[$index])) {
            return;
        }

        $this->linhaSelecionada = $index;
    }

    public function removerItemImpressao(int $index): void
    {
        if (! isset($this->itensImpressao[$index])) {
            return;
        }

        array_splice($this->itensImpressao, $index, 1);
        $this->itensImpressao = array_values($this->itensImpressao);

        $count = count($this->itensImpressao);

        if ($count === 0) {
            $this->linhaSelecionada = null;

            return;
        }

        if ($index < $count) {
            $this->linhaSelecionada = $index;
        } else {
            $this->linhaSelecionada = $count - 1;
        }
    }

    public function excluirLinhaSelecionada(): void
    {
        if ($this->linhaSelecionada === null) {
            return;
        }

        $this->removerItemImpressao($this->linhaSelecionada);
    }

    public function totalItensImpressao(): int
    {
        return count($this->itensImpressao);
    }

    public function imprimir(): void
    {
        if ($this->modeloEtiqueta !== 'gondola') {
            Notification::make()
                ->title('Selecione o modelo Etiqueta de Gôndola.')
                ->warning()
                ->send();

            return;
        }

        if ($this->itensImpressao === []) {
            Notification::make()
                ->title('Nenhum produto na lista de impressão.')
                ->warning()
                ->send();

            return;
        }

        $target = $this->resolverTargetImpressao();

        if (! $target->useDeviceService || ! $target->hasPrinter()) {
            Notification::make()
                ->title('Selecione uma impressora RAW.')
                ->body('Clique no botão 🖨 ao lado de Impressora para listar as do Device Service.')
                ->warning()
                ->send();

            return;
        }

        $productIds = collect($this->itensImpressao)
            ->map(fn (array $item): int => (int) ($item['product_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        /** @var \Illuminate\Support\Collection<int, Product> $products */
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'codigo', 'codigo_barras', 'descricao', 'preco_venda'])
            ->keyBy('id');

        $itens = [];

        foreach ($this->itensImpressao as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $product = $products->get($productId);

            if ($product === null) {
                continue;
            }

            $itens[] = [
                'codigo' => (string) ($product->codigo ?? $row['codigo'] ?? ''),
                'codigo_barras' => (string) ($product->codigo_barras ?: ($row['codigo_barras'] ?? '')),
                'descricao' => (string) ($product->descricao ?: ($row['descricao'] ?? '')),
                'preco' => (float) ($product->preco_venda ?? 0),
                'quantidade' => max(1, (int) ($row['quantidade'] ?? 1)),
            ];
        }

        if ($itens === []) {
            Notification::make()
                ->title('Nenhum produto válido para imprimir.')
                ->warning()
                ->send();

            return;
        }

        $document = new GondolaEtiquetaPrintDocument($itens);
        $payload = $document->buildEscPosPayload($target);
        $printer = $payload['printer'] ?? null;
        $raw = $payload['raw_base64'] ?? '';

        if (! filled($printer) || $raw === '') {
            Notification::make()
                ->title('Falha ao montar ESC/POS da etiqueta.')
                ->danger()
                ->send();

            return;
        }

        $this->js(
            '(async function () {'
            .'  const printer = '.json_encode($printer).';'
            .'  const data = '.json_encode($raw).';'
            .'  try {'
            .'    if (!window.ErpDeviceService) throw new Error("Device Service indisponível.");'
            .'    const online = await window.ErpDeviceService.status();'
            .'    if (!online) throw new Error("Device Service offline.");'
            .'    await window.ErpDeviceService.printRaw(printer, data, 1);'
            .'    if (window.FilamentNotification) {'
            .'      new FilamentNotification().title("Impressão").body("Etiquetas enviadas à impressora.").success().send();'
            .'    }'
            .'  } catch (e) {'
            .'    const msg = (e && e.message) ? e.message : String(e);'
            .'    if (window.FilamentNotification) {'
            .'      new FilamentNotification().title("Impressão").body(msg).danger().send();'
            .'    }'
            .'  }'
            .'})();'
        );
    }

    public function listarImpressorasRaw(): void
    {
        $this->js(<<<'JS'
            (async () => {
                try {
                    if (!window.ErpDeviceService) {
                        throw new Error('Device Service indisponível neste navegador.');
                    }
                    if (typeof window.ErpDeviceService.ensureLocal === 'function') {
                        await window.ErpDeviceService.ensureLocal();
                    }
                    const online = await window.ErpDeviceService.status();
                    if (!online) {
                        throw new Error('Device Service offline. Inicie o serviço local (porta 9330).');
                    }
                    const list = await window.ErpDeviceService.printers();
                    const names = (Array.isArray(list) ? list : []).map((p) => {
                        if (typeof p === 'string') return p;
                        return (p && (p.name || p.Name || p.printer || p.Printer)) || '';
                    }).map((n) => String(n).trim()).filter(Boolean);
                    await $wire.setImpressorasRaw(names);
                } catch (e) {
                    const msg = (e && e.message) ? e.message : String(e);
                    if (window.FilamentNotification) {
                        new FilamentNotification().title('Impressoras').body(msg).danger().send();
                    }
                }
            })();
        JS);
    }

    /**
     * @param  list<mixed>  $names
     */
    public function setImpressorasRaw(array $names): void
    {
        $clean = [];

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            if (preg_match('/^RAW:(.+)$/iu', $name, $m) === 1) {
                $name = trim($m[1]);
            }

            if ($name !== '') {
                $clean[] = $name;
            }
        }

        $clean = array_values(array_unique($clean));
        natcasesort($clean);
        $this->impressorasRaw = array_values($clean);

        if ($this->impressorasRaw === []) {
            Notification::make()
                ->title('Impressoras')
                ->body('Nenhuma impressora Windows retornada pelo Device Service.')
                ->warning()
                ->send();

            return;
        }

        if ($this->impressoraSelecionada === '' || ! in_array($this->impressoraSelecionada, $this->impressorasRaw, true)) {
            $this->impressoraSelecionada = $this->impressorasRaw[0];
        }

        Notification::make()
            ->title('Impressoras RAW')
            ->body(count($this->impressorasRaw).' impressora(s) listada(s). Selecione e imprima.')
            ->success()
            ->send();
    }

    protected function resolverTargetImpressao(): PrintTarget
    {
        $base = PrintFacade::targetFromTerminal(1);
        $nome = trim($this->impressoraSelecionada);

        if ($nome === '') {
            $nome = (string) ($base->printerName ?? '');
        }

        $fromRaw = TerminalFormOptions::windowsPrinterFromPorta($nome);
        if ($fromRaw !== null) {
            $nome = $fromRaw;
        }

        return new PrintTarget(
            printerName: $nome !== '' ? $nome : null,
            copies: 1,
            tipoImpressora: $base->tipoImpressora,
            useDeviceService: true,
        );
    }

    public function modulePending(string $module): void
    {
        Notification::make()->title($module)->body('Em implementação.')->info()->send();
    }

    public function handleEscape(): void
    {
        if ($this->docLookupTipo !== '') {
            $this->fecharDocLookup();

            return;
        }

        $this->closeScreen();
    }

    public function closeScreen(): void
    {
        $home = filament()->getUrl();

        $this->js(
            'window.history.length > 1'
            .' ? window.history.back()'
            .' : (window.location.href = '.json_encode($home).')'
        );
    }

    public function abrirLookupRomaneio(): void
    {
        $this->docLookupTipo = 'romaneio';
        $this->docLookupBusca = trim($this->filtroRomaneio);
        $this->docLookupData = now()->toDateString();
        $this->carregarDocLookup('romaneio', $this->docLookupBusca);
    }

    public function abrirLookupNfEntrada(): void
    {
        $this->docLookupTipo = 'nf';
        $this->docLookupBusca = trim($this->filtroNfEntrada);
        $this->docLookupData = now()->toDateString();
        $this->carregarDocLookup('nf', $this->docLookupBusca);
    }

    public function buscarDocLookup(): void
    {
        if ($this->docLookupTipo === '') {
            return;
        }

        $this->carregarDocLookup($this->docLookupTipo, $this->docLookupBusca);
    }

    public function selecionarDocLookup(int $id): void
    {
        $compra = Compra::query()
            ->select(['id', 'numero', 'numero_nota'])
            ->find($id);

        if ($compra === null) {
            return;
        }

        $tipo = $this->docLookupTipo;

        if ($tipo === 'romaneio') {
            $this->filtroRomaneioId = (int) $compra->id;
            $this->filtroRomaneio = (string) $compra->numero;
        } else {
            $this->filtroNfEntradaId = (int) $compra->id;
            $nota = trim((string) $compra->numero_nota);
            $this->filtroNfEntrada = $nota !== '' ? $nota : (string) $compra->numero;
        }

        $this->fecharDocLookup();
        $this->carregarProdutosDaCompra((int) $compra->id);
    }

    public function adicionarRomaneio(?string $valor = null): void
    {
        if ($valor !== null) {
            $this->filtroRomaneio = $valor;
        }

        $this->adicionarDocumentoDigitado('romaneio');
    }

    public function adicionarNfEntrada(?string $valor = null): void
    {
        if ($valor !== null) {
            $this->filtroNfEntrada = $valor;
        }

        $this->adicionarDocumentoDigitado('nf');
    }

    public function adicionarDocumentos(): void
    {
        $temRomaneio = trim($this->filtroRomaneio) !== '';
        $temNf = trim($this->filtroNfEntrada) !== '';

        if (! $temRomaneio && ! $temNf) {
            Notification::make()
                ->title('Informe Nº Romaneio ou NF Entrada.')
                ->warning()
                ->send();

            return;
        }

        if ($temRomaneio) {
            $this->adicionarDocumentoDigitado('romaneio');
        }

        if ($temNf) {
            $this->adicionarDocumentoDigitado('nf');
        }
    }

    public function fecharDocLookup(): void
    {
        $this->docLookupTipo = '';
        $this->docLookupBusca = '';
        $this->docLookupData = '';
        $this->docLookupResults = [];
    }

    public function updatedFiltroRomaneio(mixed $value): void
    {
        if (trim((string) $value) === '') {
            $this->filtroRomaneioId = null;
        }
    }

    public function updatedFiltroNfEntrada(mixed $value): void
    {
        if (trim((string) $value) === '') {
            $this->filtroNfEntradaId = null;
        }
    }

    public function updatedFiltroAlteradosEm(mixed $value): void
    {
        if (! filled(trim((string) $value))) {
            return;
        }

        $this->carregarProdutosPrecoAlteradosEm();
    }

    /**
     * Critérios ativos para filtrar produtos (só aplica o que estiver preenchido).
     * Obs.: "Somente preço alterados" NÃO entra aqui — só filtra itens ao carregar NF/Romaneio.
     *
     * @return array{
     *     romaneio_id: ?int,
     *     nf_entrada_id: ?int,
     *     validade: ?string,
     *     alterados_em: ?string
     * }
     */
    public function criteriosFiltroProdutos(): array
    {
        return [
            'romaneio_id' => $this->filtroRomaneioId,
            'nf_entrada_id' => $this->filtroNfEntradaId,
            'validade' => filled($this->filtroValidade) ? $this->filtroValidade : null,
            'alterados_em' => filled($this->filtroAlteradosEm) ? $this->filtroAlteradosEm : null,
        ];
    }

    /**
     * @param  Builder<\App\Models\Product>  $query
     * @return Builder<\App\Models\Product>
     */
    public function applyFiltrosProdutos(Builder $query): Builder
    {
        $criterios = $this->criteriosFiltroProdutos();

        if ($criterios['romaneio_id'] !== null) {
            $compraId = $criterios['romaneio_id'];
            $query->whereExists(function ($sub) use ($compraId): void {
                $sub->selectRaw('1')
                    ->from('compra_itens')
                    ->whereColumn('compra_itens.product_id', 'products.id')
                    ->where('compra_itens.compra_id', $compraId);
            });
        }

        if ($criterios['nf_entrada_id'] !== null) {
            $compraId = $criterios['nf_entrada_id'];
            $query->whereExists(function ($sub) use ($compraId): void {
                $sub->selectRaw('1')
                    ->from('compra_itens')
                    ->whereColumn('compra_itens.product_id', 'products.id')
                    ->where('compra_itens.compra_id', $compraId);
            });
        }

        if ($criterios['validade'] !== null) {
            $query->whereDate('validade', $criterios['validade']);
        }

        if ($criterios['alterados_em'] !== null) {
            $data = $criterios['alterados_em'];
            $empresaId = (int) (ErpDashboardCertificadoAlert::resolveEmpresaId() ?? 0);

            if ($empresaId <= 0) {
                $query->whereRaw('0 = 1');

                return $query;
            }

            $query->whereExists(function ($sub) use ($data, $empresaId): void {
                $sub->selectRaw('1')
                    ->from('product_empresa_precos')
                    ->whereColumn('product_empresa_precos.product_id', 'products.id')
                    ->where('product_empresa_precos.empresa_id', $empresaId)
                    ->whereDate('product_empresa_precos.updated_at', $data);
            });
        }

        return $query;
    }

    protected function carregarProdutosPrecoAlteradosEm(): void
    {
        $products = $this->applyFiltrosProdutos(
            Product::query()->where('ativo', true)
        )
            ->orderBy('descricao')
            ->get(['id', 'codigo', 'codigo_barras', 'descricao', 'preco_venda', 'unidade', 'validade']);

        /** @var array<int, true> $existentes */
        $existentes = [];
        foreach ($this->itensImpressao as $item) {
            $existentes[(int) $item['product_id']] = true;
        }

        $novos = 0;

        foreach ($products as $product) {
            $productId = (int) $product->id;
            if (isset($existentes[$productId])) {
                continue;
            }

            $this->itensImpressao[] = [
                'uid' => uniqid('e', true),
                'product_id' => $productId,
                'codigo' => (string) ($product->codigo ?? ''),
                'codigo_barras' => (string) ($product->codigo_barras ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'preco' => number_format((float) ($product->preco_venda ?? 0), 2, ',', '.'),
                'unidade' => (string) ($product->unidade ?? ''),
                'validade' => $product->validade ? $product->validade->format('d/m/Y') : '',
                'quantidade' => 1,
            ];
            $existentes[$productId] = true;
            $novos++;
        }

        if ($novos === 0) {
            Notification::make()
                ->title($products->isEmpty()
                    ? 'Nenhum preço alterado nesta data.'
                    : 'Produtos já estão na lista.')
                ->info()
                ->send();

            return;
        }

        $this->linhaSelecionada = count($this->itensImpressao) - 1;
    }

    protected function abrirDocLookup(string $tipo, string $buscaAtual): void
    {
        $this->docLookupTipo = $tipo;
        $this->docLookupBusca = trim($buscaAtual);
        $this->carregarDocLookup($tipo, $this->docLookupBusca);
    }

    protected function carregarDocLookup(string $tipo, string $busca): void
    {
        $term = trim($busca);

        $query = Compra::query()
            ->with(['fornecedor:id,nome_razao'])
            ->select(['id', 'numero', 'numero_nota', 'data_entrada', 'data_emissao', 'fornecedor_id', 'total'])
            ->orderByDesc('id')
            ->limit(40);

        if ($tipo === 'romaneio') {
            $query->whereRaw("UPPER(TRIM(COALESCE(numero_nota, ''))) = ?", ['ROMANEIO']);
        } else {
            $query->whereRaw("UPPER(TRIM(COALESCE(numero_nota, ''))) <> ?", ['ROMANEIO']);
        }

        $data = trim($this->docLookupData);
        if ($data !== '') {
            $query->where(function (Builder $q) use ($data): void {
                $q->whereDate('data_entrada', $data)
                    ->orWhereDate('data_emissao', $data);
            });
        }

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('numero', 'like', $like)
                    ->orWhere('numero_nota', 'like', $like);
            });
        }

        $this->docLookupResults = $query->get()->map(function (Compra $compra): array {
            $data = $compra->data_entrada ?? $compra->data_emissao;

            return [
                'id' => (int) $compra->id,
                'numero' => (string) $compra->numero,
                'nota' => (string) ($compra->numero_nota ?: '—'),
                'data' => $data ? $data->format('d/m/Y') : '—',
                'fornecedor' => (string) ($compra->fornecedor?->nome_razao ?? '—'),
            ];
        })->all();
    }

    protected function adicionarDocumentoDigitado(string $tipo): void
    {
        $termo = $tipo === 'romaneio'
            ? trim($this->filtroRomaneio)
            : trim($this->filtroNfEntrada);

        if ($termo === '') {
            Notification::make()
                ->title($tipo === 'romaneio' ? 'Informe o Nº Romaneio.' : 'Informe a NF Entrada.')
                ->warning()
                ->send();

            return;
        }

        $compra = $this->buscarCompraPorTermo($tipo, $termo);

        if ($compra === null) {
            Notification::make()
                ->title($tipo === 'romaneio' ? 'Romaneio não encontrado.' : 'NF Entrada não encontrada.')
                ->warning()
                ->send();

            return;
        }

        if ($tipo === 'romaneio') {
            $this->filtroRomaneioId = (int) $compra->id;
            $this->filtroRomaneio = (string) $compra->numero;
        } else {
            $this->filtroNfEntradaId = (int) $compra->id;
            $nota = trim((string) $compra->numero_nota);
            $this->filtroNfEntrada = $nota !== '' ? $nota : (string) $compra->numero;
        }

        $this->carregarProdutosDaCompra((int) $compra->id);
    }

    protected function buscarCompraPorTermo(string $tipo, string $termo): ?Compra
    {
        $query = Compra::query()->select(['id', 'numero', 'numero_nota']);

        if ($tipo === 'romaneio') {
            $query->whereRaw("UPPER(TRIM(COALESCE(numero_nota, ''))) = ?", ['ROMANEIO']);
        } else {
            $query->whereRaw("UPPER(TRIM(COALESCE(numero_nota, ''))) <> ?", ['ROMANEIO']);
        }

        $exata = (clone $query)
            ->where(function (Builder $q) use ($termo): void {
                $q->where('numero', $termo)
                    ->orWhere('numero_nota', $termo);
            })
            ->orderByDesc('id')
            ->first();

        if ($exata !== null) {
            return $exata;
        }

        $like = '%'.$termo.'%';

        return $query
            ->where(function (Builder $q) use ($like): void {
                $q->where('numero', 'like', $like)
                    ->orWhere('numero_nota', 'like', $like);
            })
            ->orderByDesc('id')
            ->first();
    }

    protected function carregarProdutosDaCompra(int $compraId): void
    {
        $qtdPadrao = max(1, (int) $this->qtdEtiquetas);

        /** @var array<int, true> $existentes */
        $existentes = [];
        foreach ($this->itensImpressao as $item) {
            $existentes[(int) $item['product_id']] = true;
        }

        $compra = Compra::query()->find($compraId);

        $itens = CompraItem::query()
            ->where('compra_id', $compraId)
            ->whereNotNull('product_id')
            ->with([
                'product:id,codigo,codigo_barras,descricao,preco_venda,unidade,validade',
            ])
            ->orderBy('id')
            ->get();

        $itensDocumento = $itens;

        if ($this->somentePrecoAlterados) {
            if ($compra === null) {
                Notification::make()
                    ->title('Documento não encontrado.')
                    ->warning()
                    ->send();

                return;
            }

            $idsAlterados = $this->productIdsComPrecoVendaAlteradoNaCompra($compra, $itens);
            $itens = $itens
                ->filter(fn (CompraItem $item): bool => isset($idsAlterados[(int) $item->product_id]))
                ->values();
        }

        $novos = 0;

        foreach ($itens as $item) {
            /** @var Product|null $product */
            $product = $item->product;

            if ($product === null) {
                continue;
            }

            $productId = (int) $product->id;

            if (isset($existentes[$productId])) {
                continue;
            }

            $this->itensImpressao[] = [
                'uid' => uniqid('e', true),
                'product_id' => $productId,
                'codigo' => (string) ($product->codigo ?? ''),
                'codigo_barras' => (string) ($product->codigo_barras ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'preco' => number_format((float) ($product->preco_venda ?? 0), 2, ',', '.'),
                'unidade' => (string) ($product->unidade ?? ''),
                'validade' => $product->validade ? $product->validade->format('d/m/Y') : '',
                'quantidade' => $qtdPadrao,
            ];

            $existentes[$productId] = true;
            $novos++;
        }

        if ($novos === 0) {
            $titulo = 'Produtos já estão na lista.';
            if ($itensDocumento->isEmpty()) {
                $titulo = 'Documento sem produtos.';
            } elseif ($this->somentePrecoAlterados && $itens->isEmpty()) {
                $titulo = 'Nenhum item com preço alterado neste documento.';
            }

            Notification::make()
                ->title($titulo)
                ->info()
                ->send();

            return;
        }

        $this->linhaSelecionada = count($this->itensImpressao) - 1;
    }

    /**
     * Itens do documento cujo preço de venda (varejo/atacado/especial) mudou na entrada
     * — via histórico gravado em FinalizarCompraLancamentoService (forma compra).
     *
     * @param  \Illuminate\Support\Collection<int, CompraItem>  $itens
     * @return array<int, true>
     */
    protected function productIdsComPrecoVendaAlteradoNaCompra(Compra $compra, $itens): array
    {
        $productIds = $itens
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return [];
        }

        $datas = array_values(array_unique(array_filter([
            $compra->data_entrada?->toDateString(),
            $compra->data_emissao?->toDateString(),
            $compra->updated_at?->toDateString(),
        ])));

        $historicos = ProductPriceHistory::query()
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->get([
                'id',
                'product_id',
                'ultimo_preco',
                'preco_atacado',
                'preco_especial',
                'forma_alteracao',
                'registrado_em',
            ])
            ->groupBy(fn (ProductPriceHistory $row): int => (int) $row->product_id);

        $alterados = [];

        foreach ($productIds as $productId) {
            $rows = $historicos->get($productId);
            if ($rows === null || $rows->isEmpty()) {
                continue;
            }

            foreach ($rows as $hist) {
                if ((string) $hist->forma_alteracao !== ProductPriceHistoryRecorder::FORMA_COMPRA) {
                    continue;
                }

                if ($datas !== []) {
                    $histDate = $hist->registrado_em?->toDateString() ?? '';
                    if (! in_array($histDate, $datas, true)) {
                        continue;
                    }
                }

                $prev = $rows
                    ->filter(fn (ProductPriceHistory $row): bool => (int) $row->id < (int) $hist->id)
                    ->last();

                if ($prev === null) {
                    continue;
                }

                if (
                    round((float) $prev->ultimo_preco, 2) !== round((float) $hist->ultimo_preco, 2)
                    || round((float) ($prev->preco_atacado ?? 0), 2) !== round((float) ($hist->preco_atacado ?? 0), 2)
                    || round((float) ($prev->preco_especial ?? 0), 2) !== round((float) ($hist->preco_especial ?? 0), 2)
                ) {
                    $alterados[$productId] = true;
                    break;
                }
            }
        }

        return $alterados;
    }
}
