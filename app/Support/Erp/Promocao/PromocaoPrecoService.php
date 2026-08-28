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
}
