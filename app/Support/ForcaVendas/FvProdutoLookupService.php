<?php

namespace App\Support\ForcaVendas;

use App\Models\PriceTable;
use App\Models\Product;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Support\Erp\ProductTabelaPrecoService;
use App\Support\Erp\Promocao\ProdutoPromocaoIndicador;
use App\Support\Erp\Promocao\PromocaoPrecoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pesquisa de produtos da Tela de Venda (sugestões).
 * Isolada do Livewire para não hidratar a venda a cada tecla.
 */
final class FvProdutoLookupService
{
    /** @var array<string, float> */
    public array $lastTimingsMs = [];

    /**
     * @return list<array{
     *   id: int,
     *   codigo: string,
     *   nome: string,
     *   atual: string,
     *   reservado: string,
     *   disponivel: string,
     *   preco: string,
     *   usa_tab_preco: bool,
     *   em_promocao: bool
     * }>
     */
    public function buscar(
        string $term,
        ?int $empresaId,
        ?int $vendedorId,
        ?int $estoqueId,
        ?int $tabelaPrecoId,
    ): array {
        $this->lastTimingsMs = [];
        $tAll = microtime(true);

        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $empresaId = $empresaId ?? (int) (ErpContext::currentEmpresaId() ?? 0);
        $empresaId = $empresaId > 0 ? $empresaId : null;

        $ctx = $this->resolverContextoAutorizado($empresaId, $vendedorId, $estoqueId, $tabelaPrecoId);
        $estoqueId = $ctx['estoque_id'];
        $tabelaPrecoId = $ctx['tabela_preco_id'];

        $t = microtime(true);
        $produtos = $this->queryProdutos($term);
        $this->lastTimingsMs['produtos'] = (microtime(true) - $t) * 1000;

        if ($produtos->isEmpty()) {
            $this->lastTimingsMs['total'] = (microtime(true) - $tAll) * 1000;

            return [];
        }

        $productIds = $produtos->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $t = microtime(true);
        $estoqueGlobal = [];
        foreach ($produtos as $product) {
            $estoqueGlobal[(int) $product->id] = (float) ($product->estoque ?? 0);
        }
        $fisicos = app(ProductEstoqueSaldoService::class)
            ->fisicoParaProdutos($productIds, $estoqueId, $estoqueGlobal);
        $this->lastTimingsMs['fisico'] = (microtime(true) - $t) * 1000;

        $t = microtime(true);
        $reservas = app(EstoqueReservaService::class)
            ->totaisReservadosAtivosParaProdutos($productIds, $estoqueId);
        $this->lastTimingsMs['reservas'] = (microtime(true) - $t) * 1000;

        $t = microtime(true);
        $tabSvc = app(ProductTabelaPrecoService::class);
        if ($empresaId) {
            $produtos->load([
                'empresaPrecos' => fn ($relation) => $relation->where('empresa_id', $empresaId),
            ]);
        }
        app(PromocaoPrecoService::class)->hidratarLote($produtos, $empresaId);
        $comTab = $produtos->filter(static fn (Product $p): bool => (bool) ($p->usa_tab_preco ?? false));
        if ($comTab->isNotEmpty()) {
            $tabSvc->carregarItens($comTab);
        }
        $this->lastTimingsMs['precos'] = (microtime(true) - $t) * 1000;

        $t = microtime(true);
        $promoIndicador = app(ProdutoPromocaoIndicador::class);
        $rows = $produtos
            ->map(function (Product $product) use (
                $fisicos,
                $reservas,
                $tabSvc,
                $tabelaPrecoId,
                $empresaId,
                $promoIndicador,
            ): array {
                $productId = (int) $product->id;
                $atual = (float) ($fisicos[$productId] ?? 0);
                $reservado = (float) ($reservas[$productId] ?? 0);
                $disponivel = $atual - $reservado;
                $preco = $this->precoProdutoNaTabela($product, $tabelaPrecoId, 1.0, $empresaId, $tabSvc);

                return [
                    'id' => $productId,
                    'codigo' => (string) ($product->codigo ?? ''),
                    'nome' => (string) ($product->descricao ?? ''),
                    'atual' => ErpMoney::formatBr($atual, 3),
                    'reservado' => ErpMoney::formatBr($reservado, 3),
                    'disponivel' => ErpMoney::formatBr($disponivel, 3),
                    'preco' => ErpMoney::formatBr($preco, 2),
                    'usa_tab_preco' => (bool) ($product->usa_tab_preco ?? false),
                    'em_promocao' => $promoIndicador->paraPrecoExibido(
                        $product,
                        $preco,
                        $empresaId,
                    ),
                ];
            })
            ->values()
            ->all();
        $this->lastTimingsMs['map'] = (microtime(true) - $t) * 1000;
        $this->lastTimingsMs['total'] = (microtime(true) - $tAll) * 1000;

        return $rows;
    }

    /**
     * @return array{estoque_id: ?int, tabela_preco_id: ?int, vendedor_id: ?int}
     */
    private function resolverContextoAutorizado(
        ?int $empresaId,
        ?int $vendedorId,
        ?int $_estoqueIdCliente,
        ?int $tabelaPrecoIdCliente,
    ): array {
        $vendedorId = $vendedorId && $vendedorId > 0 ? $vendedorId : null;
        $estoqueId = null;
        $tabelaPrecoId = null;

        if ($vendedorId) {
            $vendedor = Vendedor::query()
                ->whereKey($vendedorId)
                ->where('ativo', true)
                ->where('efetua_venda', true)
                ->whereHas('rhFuncionario')
                ->first(['id', 'ativo', 'efetua_venda', 'empresa_id', 'estoque_id']);

            if ($vendedor && $this->vendedorAutorizadoNaEmpresa($vendedor, $empresaId)) {
                // Depósito sempre do vendedor autorizado — nunca confiar no estoque_id do browser.
                $estoqueId = $vendedor->estoque_id ? (int) $vendedor->estoque_id : null;
            } else {
                $vendedorId = null;
            }
        }

        if ($tabelaPrecoIdCliente && $tabelaPrecoIdCliente > 0) {
            $existe = PriceTable::query()
                ->whereKey($tabelaPrecoIdCliente)
                ->where('ativo', true)
                ->exists();

            if ($existe) {
                $tabelaPrecoId = (int) $tabelaPrecoIdCliente;
            }
        }

        return [
            'estoque_id' => $estoqueId,
            'tabela_preco_id' => $tabelaPrecoId,
            'vendedor_id' => $vendedorId,
        ];
    }

    private function vendedorAutorizadoNaEmpresa(Vendedor $vendedor, ?int $empresaId): bool
    {
        if (! $empresaId) {
            return true;
        }

        if ((int) ($vendedor->empresa_id ?? 0) === (int) $empresaId) {
            return true;
        }

        return $vendedor->empresas()->where('empresas.id', (int) $empresaId)->exists();
    }

    /**
     * @return Collection<int, Product>
     */
    private function queryProdutos(string $term): Collection
    {
        $like = '%'.$term.'%';

        return Product::query()
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
            ->get([
                'id',
                'codigo',
                'descricao',
                'estoque',
                'preco_venda',
                'preco_atacado',
                'preco_especial',
                'qtd_atacado',
                'usa_tab_preco',
                'promo_preco_venda',
                'promo_data_inicio',
                'promo_data_fim',
            ]);
    }

    /**
     * Mesma regra de ForcaVendasTelaVendaPage::precoProdutoNaTabela (sugestão qtd=1).
     */
    private function precoProdutoNaTabela(
        Product $product,
        ?int $tabelaPrecoId,
        float $quantidade,
        ?int $empresaId,
        ProductTabelaPrecoService $tabSvc,
    ): float {
        $nivel = $tabSvc->nivelDaTabelaId($tabelaPrecoId) ?? ProductTabelaPrecoService::NIVEL_VAREJO;

        if ($product->usa_tab_preco ?? false) {
            if ($tabSvc->habilitaNivel($product, $nivel)) {
                return $tabSvc->resolvePrecoNivel(
                    $product,
                    $nivel,
                    $quantidade,
                    $empresaId,
                );
            }

            $habilitados = $tabSvc->niveisHabilitados($product);

            if ($habilitados !== []) {
                return $tabSvc->resolvePrecoNivel(
                    $product,
                    $habilitados[0],
                    $quantidade,
                    $empresaId,
                );
            }
        }

        return $this->precoBasePorNivel($product, $nivel, $quantidade, $empresaId);
    }

    private function precoBasePorNivel(
        Product $product,
        string $nivel,
        float $quantidade,
        ?int $empresaId,
    ): float {
        $varejo = $this->precoVarejoProduto($product, $empresaId);

        return match ($nivel) {
            ProductTabelaPrecoService::NIVEL_ATACADO => $this->precoAtacadoProduto($product, $varejo),
            ProductTabelaPrecoService::NIVEL_ESPECIAL => $this->precoEspecialProduto($product, $varejo),
            default => $this->precoVarejoComAtacadoPorQtd($product, $varejo, $quantidade),
        };
    }

    private function precoVarejoProduto(Product $product, ?int $empresaId): float
    {
        $campanha = app(PromocaoPrecoService::class)
            ->precoVarejoAtivo($product, $empresaId);

        if ($campanha !== null) {
            return $campanha;
        }

        $hoje = Carbon::today();
        $inicio = $product->promo_data_inicio;
        $fim = $product->promo_data_fim;
        $promo = (float) ($product->promo_preco_venda ?? 0);

        if ($promo > 0 && filled($inicio) && filled($fim) && $hoje->between(
            Carbon::parse($inicio)->startOfDay(),
            Carbon::parse($fim)->endOfDay(),
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

        return $varejo ?? round((float) ($product->preco_venda ?? 0), 2);
    }

    private function precoEspecialProduto(Product $product, ?float $varejo = null): float
    {
        $especial = (float) ($product->preco_especial ?? 0);

        if ($especial > 0) {
            return round($especial, 2);
        }

        return $varejo ?? round((float) ($product->preco_venda ?? 0), 2);
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
}
