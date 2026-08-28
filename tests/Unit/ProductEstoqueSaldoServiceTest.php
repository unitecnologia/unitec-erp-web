<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Support\Erp\ProductEstoqueSaldoService;
use Tests\TestCase;

class ProductEstoqueSaldoServiceTest extends TestCase
{
    public function test_sql_estoque_empresa_usa_tabelas_com_prefixo(): void
    {
        $service = app(ProductEstoqueSaldoService::class);
        $connection = Product::query()->getConnection();
        $prefix = $connection->getTablePrefix();
        $productsTable = $prefix.(new Product)->getTable();
        $saldosTable = $prefix.(new ProductEstoqueSaldo)->getTable();

        $expr = $service->sqlEstoqueEmpresaExpression(1);

        $this->assertStringContainsString($saldosTable, $expr);
        $this->assertStringContainsString("{$productsTable}.id", $expr);
        $this->assertStringContainsString("{$productsTable}.estoque", $expr);

        if ($prefix !== '') {
            $this->assertStringNotContainsString('FROM product_estoque_saldos', $expr);
            $this->assertStringNotContainsString('products.id', $expr);
        }
    }

    public function test_sql_estoque_empresa_nao_herda_global_quando_outros_depositos_tem_saldo(): void
    {
        $service = app(ProductEstoqueSaldoService::class);
        $productsTable = $service->tabelaProductsSql();
        $expr = $service->sqlEstoqueEmpresaExpression(1);

        $this->assertStringContainsString('THEN 0', $expr);
        $this->assertStringContainsString("ELSE {$productsTable}.estoque", $expr);
    }
}
