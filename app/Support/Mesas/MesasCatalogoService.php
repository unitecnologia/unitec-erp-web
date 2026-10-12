<?php

namespace App\Support\Mesas;

use App\Models\Empresa;
use App\Models\Product;
use App\Models\ProductEmpresaPreco;
use App\Models\ProductPriceTableItem;
use App\Models\Promocao;
use App\Models\PromocaoItem;
use App\Models\Terminal;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Pdv\PdvProductPriceService;
use App\Support\Erp\ProductAppCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Catálogo do garçom com o mesmo preço de venda do PDV (preço da empresa,
 * promoção de campanha e tabela de preço). Baixado inteiro só quando a versão muda.
 */
final class MesasCatalogoService
{
    /** Colunas de cada linha de `produtos` no JSON (lista compacta para celular fraco). */
    public const CAMPOS = ['id', 'codigo', 'barras', 'descricao', 'unidade', 'preco', 'grupo', 'pesado'];

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
        ];

        $partes[] = $this->assinaturaTabela((new ProductPriceTableItem)->getTable());

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
     * @return list<array{0: int, 1: string, 2: string, 3: string, 4: string, 5: float, 6: string, 7: int}>
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
     * @return list<array{0: int, 1: string, 2: string, 3: string, 4: string, 5: float, 6: string, 7: int}>
     */
    private function montarProdutos(Empresa $empresa): array
    {
        $linhas = [];

        $this->comEmpresa($empresa, function () use ($empresa, &$linhas): void {
            self::produtosLancaveis()
                ->orderBy('id')
                ->chunkById(self::CHUNK, function (Collection $chunk) use ($empresa, &$linhas): void {
                    $service = $this->carregarPrecos($empresa, $chunk);

                    foreach ($chunk as $product) {
                        $linhas[] = [
                            (int) $product->id,
                            (string) ($product->codigo ?? ''),
                            (string) ($product->codigo_barras ?? ''),
                            mb_strtoupper(trim((string) $product->descricao), 'UTF-8'),
                            (string) ($product->unidade ?: 'UN'),
                            round($service->resolvePrecoVenda($product, 1), 2),
                            mb_strtoupper(trim((string) ($product->grupo ?? '')), 'UTF-8'),
                            $product->produto_pesado ? 1 : 0,
                        ];
                    }
                });
        });

        usort($linhas, static fn (array $a, array $b): int => strcmp($a[3], $b[3]));

        return $linhas;
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

        $products->load([
            'empresaPrecos' => fn ($q) => $q->where('empresa_id', $empresaId),
        ]);

        app(\App\Support\Erp\ProductTabelaPrecoService::class)->carregarItens($products);

        $hoje = Carbon::today()->toDateString();
        $mins = PromocaoItem::query()
            ->selectRaw('product_id, MIN(preco_promocao) as preco_promocao')
            ->whereIn('product_id', $products->modelKeys())
            ->whereHas('promocao', function ($q) use ($empresaId, $hoje): void {
                $q->where('empresa_id', $empresaId)
                    ->where('ativa', true)
                    ->whereDate('data_inicio', '<=', $hoje)
                    ->whereDate('data_fim', '>=', $hoje);
            })
            ->groupBy('product_id')
            ->pluck('preco_promocao', 'product_id');

        foreach ($products as $product) {
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
