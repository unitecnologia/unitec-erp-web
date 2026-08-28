<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductEmpresaPreco;
use App\Support\Erp\ProductEmpresaPrecoService;
use Tests\TestCase;

class ProductEmpresaPrecoServiceTest extends TestCase
{
    public function test_overlay_zerado_puxa_preco_do_cadastro(): void
    {
        $product = new Product([
            'preco_venda' => 15,
            'preco_custo' => 8.05,
            'preco_compra' => 8.05,
            'preco_atacado' => 0,
            'preco_especial' => 0,
            'pct_custos' => 0,
            'pct_lucro' => 0,
        ]);
        $product->setRelation('empresaPrecos', collect([
            new ProductEmpresaPreco([
                'empresa_id' => 9,
                'preco_venda' => 0,
                'preco_custo' => 0,
                'preco_compra' => 0,
                'preco_atacado' => 0,
                'preco_especial' => 0,
                'pct_custos' => 0,
                'pct_lucro' => 0,
            ]),
        ]));

        $prices = app(ProductEmpresaPrecoService::class)->resolve($product, 9);

        $this->assertSame(15.0, $prices['preco_venda']);
        $this->assertSame(8.05, $prices['preco_custo']);
    }

    public function test_overlay_com_preco_da_loja_prevalece(): void
    {
        $product = new Product([
            'preco_venda' => 15,
            'preco_custo' => 0,
            'preco_compra' => 0,
            'preco_atacado' => 0,
            'preco_especial' => 0,
            'pct_custos' => 0,
            'pct_lucro' => 0,
        ]);
        $product->setRelation('empresaPrecos', collect([
            new ProductEmpresaPreco([
                'empresa_id' => 9,
                'preco_venda' => 12,
            ]),
        ]));

        $prices = app(ProductEmpresaPrecoService::class)->resolve($product, 9);

        $this->assertSame(12.0, $prices['preco_venda']);
    }

    public function test_cadastro_e_overlay_zerados_ficam_zero(): void
    {
        $product = new Product([
            'preco_venda' => 0,
            'preco_custo' => 0,
            'preco_compra' => 0,
            'preco_atacado' => 0,
            'preco_especial' => 0,
            'pct_custos' => 0,
            'pct_lucro' => 0,
        ]);
        $product->setRelation('empresaPrecos', collect([
            new ProductEmpresaPreco([
                'empresa_id' => 9,
                'preco_venda' => 0,
            ]),
        ]));

        $prices = app(ProductEmpresaPrecoService::class)->resolve($product, 9);

        $this->assertSame(0.0, $prices['preco_venda']);
    }
}
