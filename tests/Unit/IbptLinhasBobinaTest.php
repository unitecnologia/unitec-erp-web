<?php

namespace Tests\Unit;

use App\Support\Erp\Fiscal\IbptLookupService;
use PHPUnit\Framework\TestCase;

class IbptLinhasBobinaTest extends TestCase
{
    public function test_quebra_tributos_aproximados_na_largura_da_bobina(): void
    {
        $linhas = (new IbptLookupService)->linhasBobina([
            'trib_fed' => 10.5,
            'trib_est' => 8.25,
            'trib_mun' => 1.1,
            'v_tot_trib' => 19.85,
            'fonte' => 'IBPT',
        ], 48);

        $texto = implode(' ', $linhas);

        $this->assertNotSame([], $linhas);
        $this->assertStringContainsString('Fed. R$ 10,50', $texto);
        $this->assertStringContainsString('Est. R$ 8,25', $texto);
        $this->assertStringContainsString('Mun. R$ 1,10', $texto);
        $this->assertStringContainsString('Total R$ 19,85', $texto);
        $this->assertStringContainsString('Lei 12.741/2012', $texto);

        foreach ($linhas as $linha) {
            $this->assertLessThanOrEqual(48, mb_strlen($linha));
        }
    }

    public function test_sem_valor_ainda_cita_a_lei_em_qualquer_uf(): void
    {
        $linhas = (new IbptLookupService)->linhasBobina([
            'trib_fed' => 0,
            'trib_est' => 0,
            'trib_mun' => 0,
            'v_tot_trib' => 0,
            'fonte' => 'IBPT',
        ], 48);

        $texto = implode(' ', $linhas);

        $this->assertStringContainsString('Lei 12.741/2012', $texto);
        $this->assertStringContainsString('IBPT', $texto);

        foreach ($linhas as $linha) {
            $this->assertLessThanOrEqual(48, mb_strlen($linha));
        }
    }
}
