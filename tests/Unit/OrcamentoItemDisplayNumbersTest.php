<?php

namespace Tests\Unit;

use App\Support\Erp\Orcamento\OrcamentoItemDisplayNumbers;
use PHPUnit\Framework\TestCase;

class OrcamentoItemDisplayNumbersTest extends TestCase
{
    public function test_coluna_hash_numera_somente_produtos(): void
    {
        // Grade (topo = mais recente): Prod C, Sep, Prod B, Sep, Prod A
        $nums = OrcamentoItemDisplayNumbers::fromFlags([
            false,
            true,
            false,
            true,
            false,
        ]);

        $this->assertSame([3, null, 2, null, 1], $nums);
    }

    public function test_from_rows_ignora_separador(): void
    {
        $nums = OrcamentoItemDisplayNumbers::fromRows([
            ['is_separador' => false],
            ['is_separador' => true],
            ['is_separador' => false],
        ]);

        $this->assertSame([2, null, 1], $nums);
    }
}
