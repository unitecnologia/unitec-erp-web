<?php

namespace App\Support\Erp\Os;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\PromocaoItem;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\ProductTabelaPrecoService;
use App\Support\Erp\Promocao\ProdutoPromocaoIndicador;
use Carbon\Carbon;

/**
 * Pesquisa de peça/serviço da OS (lista da barra).
 * Isolada do Livewire para não hidratar o formulário a cada tecla.
 */
final class OsProdutoLookupService
{
    /** @var array<string, float> */
    public array $lastTimingsMs = [];

    /**
     * @return list<array<string, mixed>>
     */
    public function buscar(string $term, string $tab, ?int $empresaId = null): array
    {
        $this->lastTimingsMs = [];
        $tAll = microtime(true);

            $term = mb_strtoupper(trim($term), 'UTF-8');

            if ($term === '') {
                return [];
            }

            $empresaId = $empresaId ?? (int) (ErpContext::currentEmpresaId() ?? 0);
            $somenteServico = $tab === 'servicos';
            $like = '%'.$term.'%';
            $prefix = $term.'%';

            $t = microtime(true);
            $imeiProductIds = $this->imeiProductIds($term, $like, $somenteServico);
            $this->lastTimingsMs['imei_ids'] = (microtime(true) - $t) * 1000;

            $t = microtime(true);
            $produtos = $this->queryProdutos($like, $term, $prefix, $imeiProductIds, $somenteServico);
            $this->lastTimingsMs['produtos'] = (microtime(true) - $t) * 1000;

            $productIds = $produtos->pluck('id')->map(fn ($id): int => (int) $id)->all();

            $t = microtime(true);
            $reservas = ($somenteServico || $productIds === [])
                ? []
                : app(EstoqueReservaService::class)->totaisReservadosAtivosParaProdutos($productIds, null);
            $this->lastTimingsMs['reservas'] = (microtime(true) - $t) * 1000;

            $t = microtime(true);
            $imeiResumo = $somenteServico
                ? ['counts' => [], 'labels' => []]
                : $this->imeiResumoMaps($produtos, $like, $term);
            $this->lastTimingsMs['imei_resumo'] = (microtime(true) - $t) * 1000;

            $precoSvc = app(ProductTabelaPrecoService::class);

            $t = microtime(true);
            if ($produtos->isNotEmpty()) {
                if ($empresaId > 0) {
                    $produtos->load([
                        'empresaPrecos' => fn ($relation) => $relation->where('empresa_id', $empresaId),
                    ]);
                }

                $this->hydratePromocoes($produtos, $empresaId);
            }
            $this->lastTimingsMs['precos'] = (microtime(true) - $t) * 1000;

            $t = microtime(true);
            $promoIndicador = app(ProdutoPromocaoIndicador::class);
            $rows = $produtos
                ->map(function (Product $product) use ($reservas, $imeiResumo, $somenteServico, $precoSvc, $empresaId, $promoIndicador): array {
                    $descricao = mb_strtoupper((string) $product->descricao, 'UTF-8');
                    $preco = $precoSvc->resolvePrecoNivel(
                        $product,
                        ProductTabelaPrecoService::NIVEL_VAREJO,
                        1.0,
                        $empresaId > 0 ? $empresaId : null,
                    );
                    $emPromocaoLista = $promoIndicador->paraPrecoExibido(
                        $product,
                        $preco,
                        $empresaId > 0 ? $empresaId : null,
                    );
                    $row = [
                        'id' => (int) $product->id,
                        'codigo' => mb_strtoupper((string) ($product->codigo ?? ''), 'UTF-8'),
                        'descricao' => $descricao,
                        'codigo_barras' => $this->formatCodigoBarras($product),
                        'imei_resumo' => '—',
                        'atual' => '—',
                        'reservado' => '—',
                        'disponivel' => '—',
                        'preco' => ErpMoney::formatBr($preco, 2),
                        // Opção B: pesquisa e inclusão usam o mesmo preço efetivo (promo quando aplicável).
                        'preco_venda' => $preco,
                        'em_promocao' => $emPromocaoLista,
                        'preco_inclusao_diverge' => false,
                        'usa_tab_preco' => (bool) ($product->usa_tab_preco ?? false),
                        'foto_path' => $somenteServico ? null : (string) ($product->foto_path ?? ''),
                        'tipo' => $somenteServico ? 'S' : 'P',
                    ];

                    if ($somenteServico) {
                        return $row;
                    }

                    $atual = (float) ($product->estoque ?? 0);
                    $reservado = (float) ($reservas[$product->id] ?? 0);
                    $row['atual'] = ErpMoney::formatBr($atual, 3);
                    $row['reservado'] = ErpMoney::formatBr($reservado, 3);
                    $row['disponivel'] = ErpMoney::formatBr($atual - $reservado, 3);
                    $row['imei_resumo'] = $this->formatImeiResumo(
                        (bool) $product->usa_imei,
                        (int) $product->id,
                        $imeiResumo['counts'],
                        $imeiResumo['labels'],
                    );

                    return $row;
                })
                ->values()
                ->all();
            $this->lastTimingsMs['map'] = (microtime(true) - $t) * 1000;
            $this->lastTimingsMs['total'] = (microtime(true) - $tAll) * 1000;

            return $rows;
    }

    /**
     * IMEI só entra com pelo menos 4 dígitos. "ÓLEO" não consulta essa tabela.
     * Traços/pontos são aceitos (ex.: 1234-5678) e também batem no IMEI só-dígitos.
     *
     * @return list<int>
     */
    private function imeiProductIds(string $termUpper, string $like, bool $somenteServico): array
    {
        $digits = preg_replace('/\D+/', '', $termUpper) ?? '';

        if ($somenteServico || strlen($digits) < 4) {
            return [];
        }

        return ProductImei::query()
            ->where('ativo', true)
            ->where(function ($query) use ($like, $digits, $termUpper): void {
                $query->where('imei', 'like', $like);

                if ($digits !== $termUpper) {
                    $query->orWhere('imei', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('imei')
            ->limit(40)
            ->pluck('product_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $imeiProductIds
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function queryProdutos(
        string $like,
        string $termUpper,
        string $prefix,
        array $imeiProductIds,
        bool $somenteServico,
    ) {
        return Product::query()
            ->where('ativo', true)
            ->where('is_servico', $somenteServico)
            ->where(function ($query) use ($like, $termUpper, $imeiProductIds): void {
                $query->where('codigo', 'like', $like)
                    ->orWhere('descricao', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('codigo_barras_caixa', 'like', $like);

                if ($imeiProductIds !== []) {
                    $query->orWhereIn('id', $imeiProductIds);
                }

                if (ctype_digit($termUpper)) {
                    $query->orWhere('codigo', $termUpper)
                        ->orWhereRaw('CAST(codigo AS CHAR) = ?', [$termUpper]);
                }
            })
            ->orderByRaw(
                'CASE WHEN UPPER(TRIM(descricao)) = ? THEN 0 WHEN codigo = ? OR CAST(codigo AS CHAR) = ? THEN 1 WHEN codigo_barras = ? OR codigo_barras_caixa = ? OR referencia = ? THEN 2 WHEN descricao LIKE ? THEN 3 WHEN descricao LIKE ? THEN 4 ELSE 5 END',
                [$termUpper, $termUpper, $termUpper, $termUpper, $termUpper, $termUpper, $prefix, '% '.$termUpper.'%']
            )
            ->orderBy('descricao')
            ->limit(40)
            ->get([
                'id',
                'codigo',
                'descricao',
                'codigo_barras',
                'codigo_barras_caixa',
                'estoque',
                'usa_imei',
                'usa_tab_preco',
                'is_servico',
                'preco_venda',
                'promo_preco_venda',
                'promo_data_inicio',
                'promo_data_fim',
                'foto_path',
            ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $produtos
     */
    private function hydratePromocoes($produtos, int $empresaId): void
    {
        if ($produtos->isEmpty()) {
            return;
        }

        $mins = collect();

        if ($empresaId > 0) {
            $hoje = Carbon::today()->toDateString();
            $mins = PromocaoItem::query()
                ->selectRaw('product_id, MIN(preco_promocao) as preco_promocao')
                ->whereIn('product_id', $produtos->modelKeys())
                ->whereHas('promocao', function ($query) use ($empresaId, $hoje): void {
                    $query->where('empresa_id', $empresaId)
                        ->where('ativa', true)
                        ->whereDate('data_inicio', '<=', $hoje)
                        ->whereDate('data_fim', '>=', $hoje);
                })
                ->groupBy('product_id')
                ->pluck('preco_promocao', 'product_id');
        }

        foreach ($produtos as $product) {
            $min = $mins->get($product->id) ?? $mins->get((string) $product->id);
            $product->setRelation('precoPromocaoLote', $min === null ? null : (float) $min);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $produtos
     * @return array{counts: array<int, int>, labels: array<int, list<string>>}
     */
    private function imeiResumoMaps($produtos, string $like, string $termUpper): array
    {
        $usaImeiIds = $produtos
            ->filter(static fn (Product $product): bool => (bool) $product->usa_imei)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($usaImeiIds === []) {
            return ['counts' => [], 'labels' => []];
        }

        $counts = ProductImei::query()
            ->where('ativo', true)
            ->whereIn('product_id', $usaImeiIds)
            ->groupBy('product_id')
            ->selectRaw('product_id, COUNT(*) as total')
            ->pluck('total', 'product_id')
            ->map(static fn ($total): int => (int) $total)
            ->all();

        $labels = [];
        $digits = preg_replace('/\D+/', '', $termUpper) ?? '';
        $matched = ProductImei::query()
            ->where('ativo', true)
            ->whereIn('product_id', $usaImeiIds)
            ->where(function ($query) use ($like, $digits, $termUpper): void {
                $query->where('imei', 'like', $like);

                if (strlen($digits) >= 4 && $digits !== $termUpper) {
                    $query->orWhere('imei', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('imei')
            ->limit(80)
            ->get(['product_id', 'imei']);

        foreach ($matched as $row) {
            $productId = (int) $row->product_id;
            $labels[$productId] ??= [];

            if (count($labels[$productId]) < 2) {
                $labels[$productId][] = (string) $row->imei;
            }
        }

        $missing = [];

        foreach ($usaImeiIds as $productId) {
            if (! isset($labels[$productId]) && ($counts[$productId] ?? 0) > 0) {
                $missing[] = $productId;
            }
        }

        if ($missing !== []) {
            $samples = ProductImei::query()
                ->where('ativo', true)
                ->whereIn('product_id', $missing)
                ->orderBy('imei')
                ->get(['product_id', 'imei']);

            foreach ($samples as $row) {
                $productId = (int) $row->product_id;
                $labels[$productId] ??= [];

                if (count($labels[$productId]) < 2) {
                    $labels[$productId][] = (string) $row->imei;
                }
            }
        }

        return ['counts' => $counts, 'labels' => $labels];
    }

    private function formatCodigoBarras(Product $product): string
    {
        $barras = trim((string) ($product->codigo_barras ?? ''));

        if ($barras !== '') {
            return $barras;
        }

        $caixa = trim((string) ($product->codigo_barras_caixa ?? ''));

        return $caixa !== '' ? $caixa : '—';
    }

    /**
     * @param  array<int, int>  $counts
     * @param  array<int, list<string>>  $labels
     */
    private function formatImeiResumo(bool $usaImei, int $productId, array $counts, array $labels): string
    {
        if (! $usaImei) {
            return '—';
        }

        $total = (int) ($counts[$productId] ?? 0);

        if ($total <= 0) {
            return 'Sem IMEI';
        }

        $show = $labels[$productId] ?? [];
        $text = implode(', ', $show);
        $extra = $total - count($show);

        if ($extra > 0) {
            $text .= ($text !== '' ? ' +' : '+').$extra;
        }

        return $text !== '' ? $text : '—';
    }
}
