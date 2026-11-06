<?php

namespace App\Support\Erp\Promocao;

use App\Models\Product;
use App\Support\Erp\ErpContext;
use Carbon\Carbon;

/**
 * Indicador visual P: só quando o preço mostrado é o promocional vigente da empresa ativa.
 * Não marca preço de tabela, overlay de empresa ou desconto manual.
 */
final class ProdutoPromocaoIndicador
{
    public function paraPrecoExibido(Product $product, float $precoExibido, ?int $empresaId = null): bool
    {
        $precoPromo = $this->precoPromocionalVigente($product, $empresaId);

        if ($precoPromo === null) {
            return false;
        }

        return abs($precoExibido - $precoPromo) < 0.005;
    }

    public function precoPromocionalVigente(Product $product, ?int $empresaId = null): ?float
    {
        $empresaId ??= ErpContext::currentEmpresaId();
        $hoje = Carbon::today();

        if ($empresaId && $empresaId > 0) {
            $campanha = app(PromocaoPrecoService::class)->precoVarejoAtivo($product, (int) $empresaId, $hoje);

            if ($campanha !== null && $campanha > 0) {
                return $campanha;
            }
        }

        if ($this->legadoAtivo($product, $hoje)) {
            return round((float) $product->promo_preco_venda, 2);
        }

        return null;
    }

    private function legadoAtivo(Product $product, Carbon $data): bool
    {
        if (blank($product->promo_data_inicio) || blank($product->promo_data_fim)) {
            return false;
        }

        if ((float) ($product->promo_preco_venda ?? 0) <= 0) {
            return false;
        }

        return $data->between(
            Carbon::parse($product->promo_data_inicio)->startOfDay(),
            Carbon::parse($product->promo_data_fim)->endOfDay(),
        );
    }
}
