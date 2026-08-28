<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\Erp\ProductFormValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductFormValidatorBarcodeCaixaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bloqueia_codigo_barras_caixa_duplicado(): void
    {
        Product::query()->create([
            'codigo' => 'CX1',
            'descricao' => 'PRODUTO CAIXA UM',
            'preco_venda' => 10,
            'unidade' => 'UN',
            'codigo_barras_caixa' => '7891111111111',
            'ativo' => true,
        ]);

        $this->expectException(ValidationException::class);

        try {
            ProductFormValidator::validateBeforeSave([
                'descricao' => 'PRODUTO CAIXA DOIS',
                'preco_venda' => 12,
                'unidade' => 'UN',
                'codigo_barras' => '7892222222222',
                'codigo_barras_caixa' => '7891111111111',
                'cst_icms' => '000',
            ]);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('codigo_barras_caixa', $e->errors());
            throw $e;
        }
    }

    public function test_permite_codigo_barras_caixa_unico(): void
    {
        Product::query()->create([
            'codigo' => 'CX3',
            'descricao' => 'PRODUTO CAIXA TRES',
            'preco_venda' => 10,
            'unidade' => 'UN',
            'codigo_barras_caixa' => '7893333333333',
            'ativo' => true,
        ]);

        ProductFormValidator::validateBeforeSave([
            'descricao' => 'PRODUTO CAIXA QUATRO',
            'preco_venda' => 12,
            'unidade' => 'UN',
            'codigo_barras' => '7894444444444',
            'codigo_barras_caixa' => '7895555555555',
            'cst_icms' => '000',
        ]);

        $this->assertTrue(true);
    }
}
