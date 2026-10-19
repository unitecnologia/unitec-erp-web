<?php

namespace App\Support\Mesas;

use App\Models\Empresa;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\ProductEmpresaPreco;
use App\Models\ProductPriceTableItem;
use App\Models\Promocao;
use App\Models\PromocaoItem;
use App\Models\Terminal;
use App\Support\Erp\GrupoAppCatalog;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Pdv\PdvProductPriceService;
use App\Support\Erp\ProductAppCatalog;
use App\Support\Erp\ProductSalesQty;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Catálogo do garçom com o mesmo preço de venda do PDV (preço da empresa,
 * promoção de campanha e tabela de preço). Baixado inteiro só quando a versão muda.
 *
 * Grupos e "Mostrar no App" reutilizam GrupoAppCatalog / ProductAppCatalog
 * (mesmas regras do Força de Vendas). Ordenação por vendas concluídas dos
 * últimos 60 dias (canceladas ignoradas), calculada uma vez e cacheada.
 */
final class MesasCatalogoService
{
    /** Colunas de cada linha de `produtos` no JSON (lista compacta para celular fraco). */
    public const CAMPOS = [
        'id', 'codigo', 'barras', 'descricao', 'unidade', 'preco', 'grupo', 'pesado',
        'foto_url', 'qtd_vendida',
    ];

    /** Janela do ranking "mais vendidos" dentro de cada grupo. */
    public const DIAS_RANKING = 60;

    private const CHUNK = 500;

    public function versao(Empresa $empresa): string
    {
        $empresaId = (int) $empresa->id;
        $partes = [
            Carbon::today()->toDateString(),
            (string) $empresa->updated_at,
            $this->assinaturaTabela((new Product)->getTable()),
            $this->assinaturaTabela((new ProductEmpresaPreco)->getTable(), $empresaId),
            $this->assinaturaTabela((new Promocao)->getTable(), $empresaId),
            $this->assinaturaTabela((new PromocaoItem)->getTable()),
            $this->assinaturaTabela((new ProductPriceTableItem)->getTable()),
            $this->assinaturaTabela((new ProductComposition)->getTable()),
            $this->assinaturaTabela('grupos'),
            // Ranking muda no máximo 1×/dia (today já no hash); evita invalidar a cada venda.
            'ranking:'.self::DIAS_RANKING,
            'campos:'.implode(',', self::CAMPOS),
            'composicao:v1',
        ];

        return substr(md5(implode('|', $partes)), 0, 20);
    }

    /**
     * Map product_id => níveis habilitados e preços da Precificação (apps Mesas).
     *
     * @return array<int, array{niveis: list<string>, precos: array<string, float>}>
     */
    public function tabPrecos(Empresa $empresa, string $versao): array
    {
        return Cache::remember(
            'mesas_tab_preco:'.$empresa->id.':'.$versao,
            now()->addMinutes(15),
            fn (): array => $this->montarTabPrecos($empresa),
        );
    }

    /**
     * Componentes de composição dos produtos lançáveis (só kits com linhas).
     *
     * @return array<string, list<array{id: int, codigo: string, descricao: string, quantidade: float, preco: float, is_adicional: bool}>>
     */
    public function composicoes(Empresa $empresa, string $versao): array
    {
        return Cache::remember(
            'mesas_composicao:'.$empresa->id.':'.$versao,
            now()->addMinutes(15),
            fn (): array => $this->montarComposicoes(),
        );
    }

    /**
     * @return list<array{0: int, 1: string, 2: string, 3: string, 4: string, 5: float, 6: string, 7: int, 8: string|null, 9: float}>
     */
    public function produtos(Empresa $empresa, string $versao): array
    {
        return Cache::remember(
            'mesas_catalogo:'.$empresa->id.':'.$versao,
            now()->addMinutes(15),
            fn (): array => $this->montarProdutos($empresa),
        );
    }

    /**
     * Mesmos grupos do Força (ativo + Mostrar no App).
     *
     * @return list<array{id: int, nome: string, ativo: bool, mostrar_no_app: bool, updated_at: string|null}>
     */
    public function grupos(Empresa $empresa, string $versao): array
    {
        return Cache::remember(
            'mesas_grupos:'.$empresa->id.':'.$versao,
            now()->addMinutes(15),
            static fn (): array => GrupoAppCatalog::paraSync(),
        );
    }

    /**
     * Preço de venda atual (PDV) por produto, para a quantidade informada.
     *
     * @param  Collection<int, Product>  $products
     * @param  array<int, float>  $quantidades  product_id => quantidade (ausente = 1)
     * @return array<int, float>
     */
    public function precos(Empresa $empresa, Collection $products, array $quantidades = []): array
    {
        return $this->comEmpresa($empresa, function () use ($empresa, $products, $quantidades): array {
            $service = $this->carregarPrecos($empresa, $products);
            $out = [];

            foreach ($products as $product) {
                $qtd = (float) ($quantidades[$product->id] ?? 1);
                $out[(int) $product->id] = round($service->resolvePrecoVenda($product, max(1.0, $qtd)), 2);
            }

            return $out;
        });
    }

    /**
     * Produtos que o garçom pode lançar: ativos, visíveis no app e sem grade, IMEI ou preço digitado
     * (esses exigem as telas próprias do PDV).
     *
     * @return Builder<Product>
     */
    public static function produtosLancaveis(): Builder
    {
        $query = Product::query();
        ProductAppCatalog::constrainVisivel($query);

        return $query
            ->where(fn (Builder $q) => $q->whereNull('is_grade')->orWhere('is_grade', false))
            ->where(fn (Builder $q) => $q->whereNull('usa_imei')->orWhere('usa_imei', false))
            ->where(fn (Builder $q) => $q->whereNull('preco_variavel')->orWhere('preco_variavel', false));
    }

    /**
     * @return list<array{0: int, 1: string, 2: string, 3: string, 4: string, 5: float, 6: string, 7: int, 8: string|null, 9: float}>
     */
    private function montarProdutos(Empresa $empresa): array
    {
        $linhas = [];
        // Uma agregação por empresa/período (sem whereIn do catálogo inteiro).
        $saidas = ProductSalesQty::byEmpresa((int) $empresa->id, self::DIAS_RANKING);

        $this->comEmpresa($empresa, function () use ($empresa, &$linhas, $saidas): void {
            self::produtosLancaveis()
                ->orderBy('id')
                ->chunkById(self::CHUNK, function (Collection $chunk) use ($empresa, &$linhas, $saidas): void {
                    $service = $this->carregarPrecos($empresa, $chunk);

                    foreach ($chunk as $product) {
                        $id = (int) $product->id;
                        $linhas[] = [
                            $id,
                            (string) ($product->codigo ?? ''),
                            (string) ($product->codigo_barras ?? ''),
                            mb_strtoupper(trim((string) $product->descricao), 'UTF-8'),
                            (string) ($product->unidade ?: 'UN'),
                            round($service->resolvePrecoVenda($product, 1), 2),
                            mb_strtoupper(trim((string) ($product->grupo ?? '')), 'UTF-8'),
                            $product->produto_pesado ? 1 : 0,
                            $this->fotoApp($product),
                            $saidas[$id] ?? 0.0,
                        ];
                    }
                });
        });

        // Dentro do grupo: mais vendidos primeiro; empate por descrição.
        usort($linhas, static function (array $a, array $b): int {
            $g = strcmp($a[6], $b[6]);
            if ($g !== 0) {
                return $g;
            }
            if ($a[9] !== $b[9]) {
                return $b[9] <=> $a[9];
            }

            return strcmp($a[3], $b[3]);
        });

        return $linhas;
    }

    /**
     * URL relativa da foto (Image.network no app não envia token).
     * Mesmo padrão do Força: rota pública + ?v= para cache-bust.
     */
    private function fotoApp(Product $p): ?string
    {
        if (blank($p->foto_path)) {
            return null;
        }

        $url = route('mesas.produto.foto', ['product' => $p->id], false);
        $version = optional($p->updated_at)->timestamp;

        return $version ? $url.'?v='.$version : $url;
    }

    /**
     * Pré-carrega preço da empresa, tabela de preço e promoção do lote (evita N+1).
     *
     * @param  Collection<int, Product>  $products
     */
    private function carregarPrecos(Empresa $empresa, Collection $products): PdvProductPriceService
    {
        $config = PdvConfig::make($empresa, new Terminal);
        $empresaId = (int) $empresa->id;

        if ($products->isEmpty()) {
            return new PdvProductPriceService($config);
        }

        // `precos()` recebe Support\Collection (ex.: collect([$product]));
        // `load` / `modelKeys` existem só na Eloquent Collection.
        /** @var EloquentCollection<int, Product> $eloquent */
        $eloquent = $products instanceof EloquentCollection
            ? $products
            : new EloquentCollection($products->all());

        $eloquent->load([
            'empresaPrecos' => fn ($q) => $q->where('empresa_id', $empresaId),
        ]);

        app(\App\Support\Erp\ProductTabelaPrecoService::class)->carregarItens($eloquent);

        $hoje = Carbon::today()->toDateString();
        $mins = PromocaoItem::query()
            ->selectRaw('product_id, MIN(preco_promocao) as preco_promocao')
            ->whereIn('product_id', $eloquent->modelKeys())
            ->whereHas('promocao', function ($q) use ($empresaId, $hoje): void {
                $q->where('empresa_id', $empresaId)
                    ->where('ativa', true)
                    ->whereDate('data_inicio', '<=', $hoje)
                    ->whereDate('data_fim', '>=', $hoje);
            })
            ->groupBy('product_id')
            ->pluck('preco_promocao', 'product_id');

        foreach ($eloquent as $product) {
            $min = $mins->get($product->id);
            $product->setRelation('precoPromocaoLote', $min === null ? null : (float) $min);
        }

        return new PdvProductPriceService($config);
    }

    /**
     * O cálculo de preço do PDV lê a empresa da sessão; a API não tem sessão persistida,
     * então a empresa do garçom é posta só durante o cálculo.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function comEmpresa(Empresa $empresa, callable $callback): mixed
    {
        $session = session();
        $anterior = $session->get('erp_empresa_id');
        $session->put('erp_empresa_id', (int) $empresa->id);

        try {
            return $callback();
        } finally {
            if ($anterior === null) {
                $session->forget('erp_empresa_id');
            } else {
                $session->put('erp_empresa_id', $anterior);
            }
        }
    }

    /**
     * @return array<int, array{niveis: list<string>, precos: array<string, float>}>
     */
    private function montarTabPrecos(Empresa $empresa): array
    {
        $out = [];

        $this->comEmpresa($empresa, function () use ($empresa, &$out): void {
            $tabSvc = app(\App\Support\Erp\ProductTabelaPrecoService::class);
            $empresaId = (int) $empresa->id;

            self::produtosLancaveis()
                ->where('usa_tab_preco', true)
                ->orderBy('id')
                ->chunkById(self::CHUNK, function (Collection $chunk) use ($tabSvc, $empresaId, &$out): void {
                    $tabSvc->carregarItens($chunk);

                    foreach ($chunk as $product) {
                        $niveis = $tabSvc->niveisHabilitados($product);

                        if ($niveis === []) {
                            continue;
                        }

                        $precos = [];

                        foreach ($niveis as $nivel) {
                            $precos[$nivel] = $tabSvc->resolvePrecoNivel($product, $nivel, 1, $empresaId);
                        }

                        $out[(int) $product->id] = [
                            'niveis' => $niveis,
                            'precos' => $precos,
                        ];
                    }
                });
        });

        return $out;
    }

    /**
     * @return array<string, list<array{id: int, codigo: string, descricao: string, quantidade: float, preco: float, is_adicional: bool}>>
     */
    private function montarComposicoes(): array
    {
        $kitIds = self::produtosLancaveis()
            ->where('is_composicao', true)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($kitIds === []) {
            return [];
        }

        $out = [];

        ProductComposition::query()
            ->whereIn('product_id', $kitIds)
            ->with('componentProduct:id,codigo,descricao,ativo')
            ->orderBy('id')
            ->get()
            ->each(function (ProductComposition $item) use (&$out): void {
                $comp = $item->componentProduct;

                if (! $comp || ! ($comp->ativo ?? true)) {
                    return;
                }

                $kitId = (string) (int) $item->product_id;
                $out[$kitId] ??= [];
                $out[$kitId][] = [
                    'id' => (int) $comp->id,
                    'codigo' => (string) ($comp->codigo ?? ''),
                    'descricao' => mb_strtoupper(trim((string) $comp->descricao), 'UTF-8'),
                    'quantidade' => round((float) $item->quantidade, 3),
                    'preco' => round((float) $item->preco, 2),
                    'is_adicional' => (bool) ($item->is_adicional ?? false),
                ];
            });

        return $out;
    }

    private function assinaturaTabela(string $tabela, ?int $empresaId = null): string
    {
        try {
            $query = \Illuminate\Support\Facades\DB::table($tabela);

            if ($empresaId !== null) {
                $query->where('empresa_id', $empresaId);
            }

            $row = $query->selectRaw('COUNT(*) as c, MAX(updated_at) as u')->first();

            return ($row->c ?? 0).'@'.($row->u ?? '');
        } catch (Throwable) {
            return '-';
        }
    }
}
