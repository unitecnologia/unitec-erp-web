<?php

namespace App\Filament\Gestor\Pages;

use App\Filament\Gestor\Concerns\InteractsWithGestorShell;
use App\Models\Product;
use App\Support\Erp\AjusteEstoqueService;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\Fiscal\NcmCatalogService;
use App\Support\Erp\ProductEmpresaPrecoService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class ProdutosGestorPage extends Page
{
    use InteractsWithGestorShell;

    protected static ?string $slug = 'produtos';

    protected static ?string $title = 'Produtos';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.gestor.produtos';

    public string $busca = '';

    #[Url(as: 'produto')]
    public ?int $produtoId = null;

    public string $codigo = '';

    public string $descricao = '';

    public string $grupo = '';

    public string $marca = '';

    public string $unidade = '';

    public string $ncm = '';

    public string $ncmDescricao = '';

    public string $precoVenda = '';

    public string $precoAtacado = '';

    public string $precoEspecial = '';

    public string $estoque = '';

    public string $estoqueMinimo = '';

    public float $estoqueReservado = 0;

    public float $estoqueDisponivel = 0;

    public static function canAccess(): bool
    {
        return static::canAccessGestor();
    }

    public function mount(): void
    {
        $this->mountGestorShell();

        if ($this->produtoId) {
            $this->selecionar($this->produtoId);
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->produtoId ? 'Editar produto' : 'Produtos';
    }

    public function updatedBusca(string $value): void
    {
        $this->busca = ErpUppercase::uppercase($value);
    }

    /**
     * @return list<array{id: int, codigo: string, descricao: string, estoque: float, preco_venda: float, grupo: string}>
     */
    public function resultados(): array
    {
        $q = ErpUppercase::uppercase(trim($this->busca));

        if (mb_strlen($q) < 2) {
            return [];
        }

        $empresaId = $this->empresaId();
        $precos = app(ProductEmpresaPrecoService::class);
        $starts = $q.'%';
        $word = '% '.$q.'%';

        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($q, $starts, $word): void {
                $query->where('codigo', $q)
                    ->orWhere('codigo', 'like', $starts)
                    ->orWhere('codigo_barras', 'like', $starts)
                    ->orWhere('referencia', 'like', $starts)
                    // Nome: começa com o termo OU alguma palavra começa com o termo (não no meio da palavra).
                    ->orWhere('descricao', 'like', $starts)
                    ->orWhere('descricao', 'like', $word);
            })
            ->orderByRaw(
                'CASE
                    WHEN codigo = ? THEN 0
                    WHEN codigo LIKE ? THEN 1
                    WHEN codigo_barras LIKE ? THEN 2
                    WHEN descricao LIKE ? THEN 3
                    WHEN descricao LIKE ? THEN 4
                    WHEN referencia LIKE ? THEN 5
                    ELSE 6
                END',
                [$q, $starts, $starts, $starts, $word, $starts]
            )
            ->orderBy('descricao')
            ->limit(40)
            ->get(['id', 'codigo', 'descricao', 'estoque', 'preco_venda', 'grupo'])
            ->map(function (Product $product) use ($precos, $empresaId): array {
                return [
                    'id' => (int) $product->id,
                    'codigo' => (string) ($product->codigo ?? ''),
                    'descricao' => (string) ($product->descricao ?? ''),
                    'grupo' => (string) ($product->grupo ?? ''),
                    'estoque' => round((float) $product->estoque, 3),
                    'preco_venda' => $precos->resolvePrecoVenda($product, $empresaId),
                ];
            })
            ->all();
    }

    public function selecionar(int $id): void
    {
        $product = Product::query()->whereKey($id)->where('ativo', true)->first();

        if (! $product) {
            $this->produtoId = null;
            Notification::make()
                ->title('Produto não encontrado')
                ->danger()
                ->send();

            return;
        }

        $this->carregarProduto($product);
        $this->busca = '';
    }

    public function voltar(): void
    {
        $this->produtoId = null;
        $this->codigo = '';
        $this->descricao = '';
        $this->grupo = '';
        $this->marca = '';
        $this->unidade = '';
        $this->ncm = '';
        $this->ncmDescricao = '';
        $this->precoVenda = '';
        $this->precoAtacado = '';
        $this->precoEspecial = '';
        $this->estoque = '';
        $this->estoqueMinimo = '';
        $this->estoqueReservado = 0;
        $this->estoqueDisponivel = 0;
    }

    public function salvar(): void
    {
        if (! $this->produtoId) {
            return;
        }

        $user = Auth::user();

        try {
            $alterou = DB::transaction(function () use ($user): bool {
                $product = Product::query()->whereKey($this->produtoId)->lockForUpdate()->first();

                if (! $product) {
                    throw new \RuntimeException('Produto não encontrado.');
                }

                $mudou = false;

                if ($this->canEditCadastro() && ErpAccess::can($user, 'produtos.update')) {
                    $nome = ErpUppercase::uppercase(trim($this->descricao));
                    $grupo = ErpUppercase::uppercase(trim($this->grupo));
                    $marca = ErpUppercase::uppercase(trim($this->marca));
                    $unidade = ErpUppercase::uppercase(trim($this->unidade));
                    $ncm = preg_replace('/\D+/', '', trim($this->ncm)) ?? '';
                    // Descrição NCM só vem do catálogo (campo somente leitura).
                    $ncmDesc = ErpUppercase::uppercase(trim($this->ncmDescricao));

                    if ($nome === '') {
                        throw new \InvalidArgumentException('Informe o nome do produto.');
                    }

                    if ($unidade === '') {
                        $unidade = 'UN';
                    }

                    if (strlen($ncm) > 0 && strlen($ncm) !== 8) {
                        throw new \InvalidArgumentException('NCM deve ter 8 dígitos. Pressione Enter para buscar.');
                    }

                    if (strlen($ncm) === 8) {
                        $record = app(NcmCatalogService::class)->findByCodigo($ncm);
                        if (! $record) {
                            throw new \InvalidArgumentException('NCM não encontrado no catálogo. Digite o código e pressione Enter.');
                        }
                        $ncmDesc = ErpUppercase::uppercase(trim((string) $record->descricao));
                        $this->ncmDescricao = $ncmDesc;
                    } else {
                        $ncmDesc = '';
                        $this->ncmDescricao = '';
                    }

                    $payload = [
                        'descricao' => $nome,
                        'grupo' => $grupo !== '' ? $grupo : 'DIVERSOS',
                        'marca' => $marca !== '' ? $marca : null,
                        'unidade' => $unidade,
                        'ncm' => $ncm !== '' ? $ncm : null,
                        'ncm_descricao' => $ncmDesc !== '' ? $ncmDesc : null,
                    ];

                    $mudouCadastro = false;
                    foreach ($payload as $campo => $valor) {
                        $atual = $product->{$campo};
                        if ((string) ($atual ?? '') !== (string) ($valor ?? '')) {
                            $mudouCadastro = true;
                            break;
                        }
                    }

                    if ($mudouCadastro) {
                        $product->update($payload);
                        $this->descricao = $nome;
                        $this->grupo = (string) $payload['grupo'];
                        $this->marca = (string) ($payload['marca'] ?? '');
                        $this->unidade = $unidade;
                        $this->ncm = (string) ($payload['ncm'] ?? '');
                        $this->ncmDescricao = (string) ($payload['ncm_descricao'] ?? '');
                        $mudou = true;
                    }
                }

                if ($this->canEditPreco()) {
                    $novoVenda = BrDecimal::parse($this->precoVenda, 2);
                    $novoAtacado = BrDecimal::parse($this->precoAtacado, 2);
                    $novoEspecial = BrDecimal::parse($this->precoEspecial, 2);

                    if ($novoVenda <= 0) {
                        throw new \InvalidArgumentException('Preço de varejo deve ser maior que zero.');
                    }

                    if ($novoAtacado < 0 || $novoEspecial < 0) {
                        throw new \InvalidArgumentException('Preços não podem ser negativos.');
                    }

                    $empresaId = $this->empresaId();
                    $service = app(ProductEmpresaPrecoService::class);
                    $atual = $service->resolve($product->fresh(), $empresaId);

                    $mudouPreco = abs($novoVenda - (float) $atual['preco_venda']) >= 0.005
                        || abs($novoAtacado - (float) $atual['preco_atacado']) >= 0.005
                        || abs($novoEspecial - (float) $atual['preco_especial']) >= 0.005;

                    if ($mudouPreco) {
                        $prices = $atual;
                        $prices['preco_venda'] = $novoVenda;
                        $prices['preco_atacado'] = $novoAtacado;
                        $prices['preco_especial'] = $novoEspecial;

                        if ($empresaId > 0) {
                            $service->upsert($product, $empresaId, $prices);
                        }

                        $product->update([
                            'preco_venda' => $novoVenda,
                            'preco_atacado' => $novoAtacado,
                            'preco_especial' => $novoEspecial,
                        ]);

                        $this->precoVenda = $this->formatMoney($novoVenda);
                        $this->precoAtacado = $this->formatMoney($novoAtacado);
                        $this->precoEspecial = $this->formatMoney($novoEspecial);
                        $mudou = true;
                    }
                }

                if ($this->canEditEstoque() && ErpAccess::can($user, 'ajuste_estoque.create')) {
                    $novoEstoque = BrDecimal::parse($this->estoque, 3);
                    $atualEstoque = round((float) $product->fresh()->estoque, 3);
                    $delta = round($novoEstoque - $atualEstoque, 3);

                    if (abs($delta) >= 0.0005) {
                        app(AjusteEstoqueService::class)->criar(
                            (int) $product->id,
                            now()->toDateString(),
                            $delta,
                        );
                        $this->estoque = $this->formatQty($novoEstoque);
                        $this->atualizarSaldosEstoque((int) $product->id, $novoEstoque);
                        $mudou = true;
                    }
                }

                return $mudou;
            });
        } catch (\InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();

            return;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não foi possível salvar')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        if (! $alterou) {
            Notification::make()
                ->title('Nada para salvar')
                ->body('Os valores estão iguais aos atuais.')
                ->info()
                ->send();
            $this->voltar();

            return;
        }

        Notification::make()
            ->title('Produto atualizado')
            ->success()
            ->send();

        $this->voltar();
    }

    public function buscarNcm(): void
    {
        if (! $this->canEditCadastro()) {
            return;
        }

        $catalog = app(NcmCatalogService::class);
        $codigo = $catalog->normalizeCodigo($this->ncm);

        if ($codigo === null) {
            $this->ncmDescricao = '';
            if (trim($this->ncm) !== '') {
                Notification::make()
                    ->title('NCM inválido')
                    ->body('Informe os 8 dígitos do NCM.')
                    ->warning()
                    ->send();
            }

            return;
        }

        $this->ncm = $codigo;
        $record = $catalog->findByCodigo($codigo);

        if (! $record) {
            $this->ncmDescricao = '';
            Notification::make()
                ->title('NCM não encontrado')
                ->body('Código '.$codigo.' não existe no catálogo.')
                ->warning()
                ->send();

            return;
        }

        $this->ncmDescricao = ErpUppercase::uppercase(trim((string) $record->descricao));
    }

    public function canEditCadastro(): bool
    {
        return ErpAccess::currentCan('produtos.update');
    }

    public function canEditNome(): bool
    {
        return $this->canEditCadastro();
    }

    public function canEditPreco(): bool
    {
        return ErpAccess::currentCan('ajusta_preco.update')
            || ErpAccess::currentCan('produtos.update');
    }

    public function canEditEstoque(): bool
    {
        return ErpAccess::currentCan('ajuste_estoque.create');
    }

    private function carregarProduto(Product $product): void
    {
        $empresaId = $this->empresaId();
        $prices = app(ProductEmpresaPrecoService::class)->resolve($product, $empresaId);
        $fisico = round((float) $product->estoque, 3);

        $this->produtoId = (int) $product->id;
        $this->codigo = (string) ($product->codigo ?? '');
        $this->descricao = (string) ($product->descricao ?? '');
        $this->grupo = (string) ($product->grupo ?? '');
        $this->marca = (string) ($product->marca ?? '');
        $this->unidade = (string) ($product->unidade ?: 'UN');
        $this->ncm = (string) ($product->ncm ?? '');
        $this->ncmDescricao = (string) ($product->ncm_descricao ?? '');
        if ($this->ncm !== '' && $this->ncmDescricao === '') {
            $record = app(NcmCatalogService::class)->findByCodigo(
                app(NcmCatalogService::class)->normalizeCodigo($this->ncm) ?? $this->ncm
            );
            if ($record) {
                $this->ncmDescricao = ErpUppercase::uppercase(trim((string) $record->descricao));
            }
        }
        $this->precoVenda = $this->formatMoney((float) $prices['preco_venda']);
        $this->precoAtacado = $this->formatMoney((float) $prices['preco_atacado']);
        $this->precoEspecial = $this->formatMoney((float) $prices['preco_especial']);
        $this->estoque = $this->formatQty($fisico);
        $this->estoqueMinimo = $this->formatQty((float) ($product->estoque_minimo ?? 0));
        $this->atualizarSaldosEstoque((int) $product->id, $fisico);
    }

    private function atualizarSaldosEstoque(int $productId, float $fisico): void
    {
        try {
            $reservado = app(EstoqueReservaService::class)->reservadoAtivo($productId);
        } catch (\Throwable) {
            $reservado = 0.0;
        }

        $this->estoqueReservado = round((float) $reservado, 3);
        $this->estoqueDisponivel = round(max(0, $fisico - $this->estoqueReservado), 3);
    }

    public function formatQtyPublic(float $value): string
    {
        return $this->formatQty($value);
    }

    private function empresaId(): int
    {
        return (int) (session('erp_empresa_id') ?? Auth::user()?->empresa_id ?? 0);
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function formatQty(float $value): string
    {
        $formatted = number_format($value, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
