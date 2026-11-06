<?php

namespace App\Support\Erp\Promocao;

use App\Models\Product;
use App\Models\PromocaoItem;
use App\Support\Erp\ErpContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Promoção de campanha — somente varejo.
 */
final class PromocaoPrecoService
{
    /**
     * Menor preço promo ativo (varejo) para o produto na empresa/data.
     */
    public function precoVarejoAtivo(Product|int $product, ?int $empresaId = null, ?Carbon $data = null): ?float
    {
        if ($product instanceof Product && $product->relationLoaded('precoPromocaoLote')) {
            $precoLote = $product->getRelation('precoPromocaoLote');

            if ($precoLote === null) {
                return null;
            }

            $valorLote = (float) $precoLote;

            return $valorLote > 0 ? round($valorLote, 2) : null;
        }

        $productId = $product instanceof Product ? (int) $product->id : (int) $product;
        $empresaId ??= ErpContext::currentEmpresaId();
        $data ??= Carbon::today();

        if ($productId <= 0 || ! $empresaId) {
            return null;
        }

        $preco = PromocaoItem::query()
            ->where('product_id', $productId)
            ->whereHas('promocao', function ($q) use ($empresaId, $data): void {
                $q->where('empresa_id', $empresaId)
                    ->where('ativa', true)
                    ->whereDate('data_inicio', '<=', $data->toDateString())
                    ->whereDate('data_fim', '>=', $data->toDateString());
            })
            ->min('preco_promocao');

        if ($preco === null) {
            return null;
        }

        $valor = (float) $preco;

        return $valor > 0 ? round($valor, 2) : null;
    }

    public function emPromocaoCampanha(Product|int $product, ?int $empresaId = null, ?Carbon $data = null): bool
    {
        return $this->precoVarejoAtivo($product, $empresaId, $data) !== null;
    }

    /**
     * Itens com Mostrar no PDV para carrossel idle.
     *
     * @return list<array{product_id:int,descricao:string,preco_promocao:float,preco_normal:float,foto_url:?string}>
     */
    public function itensPropagandaPdv(?int $empresaId = null, ?Carbon $data = null): array
    {
        $empresaId ??= ErpContext::currentEmpresaId();
        $data ??= Carbon::today();

        if (! $empresaId) {
            return [];
        }

        /** @var Collection<int, PromocaoItem> $itens */
        $itens = PromocaoItem::query()
            ->with(['product', 'promocao'])
            ->where('mostrar_pdv', true)
            ->whereHas('promocao', function ($q) use ($empresaId, $data): void {
                $q->where('empresa_id', $empresaId)
                    ->where('ativa', true)
                    ->whereDate('data_inicio', '<=', $data->toDateString())
                    ->whereDate('data_fim', '>=', $data->toDateString());
            })
            ->get();

        $byProduct = [];

        foreach ($itens as $item) {
            $product = $item->product;
            if (! $product || ! $product->ativo) {
                continue;
            }

            $pid = (int) $product->id;
            $preco = (float) $item->preco_promocao;
            if ($preco <= 0) {
                continue;
            }

            if (isset($byProduct[$pid]) && $byProduct[$pid]['preco_promocao'] <= $preco) {
                continue;
            }

            $byProduct[$pid] = [
                'product_id' => $pid,
                'descricao' => mb_strtoupper(trim((string) $product->descricao), 'UTF-8'),
                'preco_promocao' => round($preco, 2),
                'preco_normal' => round((float) ($product->preco_venda ?? 0), 2),
                'foto_url' => $product->fotoUrl(),
            ];
        }

        return array_values($byProduct);
    }

    /**
     * Preço de campanha em lote (uma consulta). Marca precoPromocaoLote em cada produto.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $produtos
     */
    public function hidratarLote($produtos, ?int $empresaId = null): void
    {
        if ($produtos->isEmpty()) {
            return;
        }

        $empresaId ??= ErpContext::currentEmpresaId();
        $mins = collect();

        if ($empresaId && $empresaId > 0) {
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
}
