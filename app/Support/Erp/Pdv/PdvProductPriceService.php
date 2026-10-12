<?php

namespace App\Support\Erp\Pdv;

use App\Models\Product;
use App\Support\Erp\ProductEmpresaPrecoService;
use App\Support\Erp\ProductTabelaPrecoService;
use App\Support\Erp\Promocao\PromocaoPrecoService;
use Carbon\Carbon;

final class PdvProductPriceService
{
    public function __construct(
        private readonly PdvConfig $config,
    ) {}

    public function resolvePrecoVenda(
        Product $product,
        float $quantidade = 1,
        ?string $nivel = null,
        ?int $priceTableId = null,
    ): float {
        $tabSvc = app(ProductTabelaPrecoService::class);

        if ($product->usa_tab_preco ?? false) {
            $nivelResolvido = $tabSvc->normalizarNivel($nivel)
                ?? $tabSvc->nivelDaTabelaId($priceTableId)
                ?? $tabSvc->nivelDaTabelaId($this->config->priceTableId());

            if ($nivelResolvido && $tabSvc->habilitaNivel($product, $nivelResolvido)) {
                return $tabSvc->resolvePrecoNivel($product, $nivelResolvido, $quantidade);
            }
        }

        return $this->resolvePrecoBase($product, $quantidade);
    }

    /**
     * Preço do nível da tabela quando o produto habilita Tab. Preço nesse nível.
     */
    public function resolvePrecoTabela(
        Product $product,
        float $quantidade = 1,
        ?int $priceTableId = null,
        ?string $nivel = null,
    ): ?float {
        if (! ($product->usa_tab_preco ?? false)) {
            return null;
        }

        $tabSvc = app(ProductTabelaPrecoService::class);
        $nivelResolvido = $tabSvc->normalizarNivel($nivel)
            ?? $tabSvc->nivelDaTabelaId($priceTableId)
            ?? $tabSvc->nivelDaTabelaId($this->config->priceTableId());

        if (! $nivelResolvido || ! $tabSvc->habilitaNivel($product, $nivelResolvido)) {
            return null;
        }

        return $tabSvc->resolvePrecoNivel($product, $nivelResolvido, $quantidade);
    }

    public function emPromocao(Product $product, ?Carbon $data = null): bool
    {
        $data ??= Carbon::today();
        $empresaId = (int) (session('erp_empresa_id') ?? 0);

        if ($empresaId > 0 && app(PromocaoPrecoService::class)->emPromocaoCampanha($product, $empresaId, $data)) {
            return true;
        }

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

    public function precoMinimoPermitido(Product $product, float $quantidade = 1): ?float
    {
        return null;
    }

    public function validaPrecoInformado(Product $product, float $preco, float $quantidade = 1): ?string
    {
        if ($product->preco_variavel) {
            return null;
        }

        $precoBase = $this->resolvePrecoVenda($product, $quantidade);

        if ($this->emPromocao($product) && ! $this->config->descontoProdPromocao()) {
            if ($preco < $precoBase) {
                return 'Não é permitido descontos para produtos em promoção!';
            }

            return null;
        }

        $minimo = $this->precoMinimoPermitido($product, $quantidade);

        if ($minimo !== null && $preco < $minimo) {
            return 'Desconto maior que o permitido para este produto.';
        }

        return null;
    }

    private function resolvePrecoBase(Product $product, float $quantidade = 1): float
    {
        $quantidade = max(0, $quantidade);
        $hoje = Carbon::today();
        $empresaId = (int) (session('erp_empresa_id') ?? 0);
        $precosEmpresa = app(ProductEmpresaPrecoService::class)->resolve(
            $product,
            $empresaId > 0 ? $empresaId : null
        );

        if ($this->aplicaAtacado($product, $quantidade, $precosEmpresa['preco_atacado'])) {
            return (float) $precosEmpresa['preco_atacado'];
        }

        $campanha = $empresaId > 0
            ? app(PromocaoPrecoService::class)->precoVarejoAtivo($product, $empresaId, $hoje)
            : null;

        if ($campanha !== null) {
            return $campanha;
        }

        if ($this->emPromocaoLegado($product, $hoje)) {
            return (float) $product->promo_preco_venda;
        }

        return (float) $precosEmpresa['preco_venda'];
    }

    private function emPromocaoLegado(Product $product, Carbon $data): bool
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

    private function aplicaAtacado(Product $product, float $quantidade, ?float $precoAtacadoOverride = null): bool
    {
        $qtdAtacado = (float) ($product->qtd_atacado ?? 0);
        $precoAtacado = $precoAtacadoOverride ?? (float) ($product->preco_atacado ?? 0);

        return $qtdAtacado > 0 && $precoAtacado > 0 && $quantidade >= $qtdAtacado;
    }
}
