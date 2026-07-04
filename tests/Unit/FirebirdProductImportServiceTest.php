<?php

namespace Tests\Unit;

use App\Support\Erp\Import\FirebirdProductImportService;
use PHPUnit\Framework\TestCase;

class FirebirdProductImportServiceTest extends TestCase
{
    public function test_maps_firebird_row_with_brazilian_fields(): void
    {
        $mapped = (new FirebirdProductImportService())->mapFirebirdRow([
            'CODIGO' => 123,
            'DESCRICAO' => 'Produto teste',
            'PR_VENDA' => 10.5,
            'PR_VENDA_PRAZO' => '12,50',
            'ATIVO' => 'S',
            'GRADE' => 'N',
            'RESTAUTANTE' => 'S',
            'VALOR_PEQUENA' => '25,0000',
            'QTD_ATUAL' => '1,500000',
        ]);

        $this->assertSame('123', $mapped['codigo']);
        $this->assertSame('PRODUTO TESTE', $mapped['descricao']);
        $this->assertSame(10.5, $mapped['preco_venda']);
        $this->assertSame(12.5, $mapped['preco_venda_prazo']);
        $this->assertTrue($mapped['ativo']);
        $this->assertFalse($mapped['is_grade']);
        $this->assertTrue($mapped['is_restaurante']);
        $this->assertSame(25.0, $mapped['valor_pequena']);
        $this->assertSame(1.5, $mapped['estoque']);
    }
}
