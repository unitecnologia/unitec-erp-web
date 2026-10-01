<?php

namespace Tests\Unit;

use App\Support\Erp\Nfse\NfseRegimeTributario;
use PHPUnit\Framework\TestCase;

class NfseRegimeTributarioTest extends TestCase
{
    public function test_op_simp_nac_reaproveita_o_regime_da_empresa(): void
    {
        $this->assertSame('2', NfseRegimeTributario::opSimpNac('mei'));
        $this->assertSame('3', NfseRegimeTributario::opSimpNac('simples'));
        $this->assertSame('1', NfseRegimeTributario::opSimpNac('normal'));
        $this->assertSame('1', NfseRegimeTributario::opSimpNac('presumido'));
        $this->assertSame('1', NfseRegimeTributario::opSimpNac('real'));
        $this->assertSame('1', NfseRegimeTributario::opSimpNac(null));
    }
}
