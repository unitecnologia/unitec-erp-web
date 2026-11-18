<?php

namespace Tests\Unit;

use App\Support\Erp\Financeiro\ContaPagarCompetencia;
use InvalidArgumentException;
use Tests\TestCase;

class ContaPagarCompetenciaTest extends TestCase
{
    public function test_mm_aaaa_vira_primeiro_dia_do_mes(): void
    {
        $this->assertSame('2026-11-01', ContaPagarCompetencia::toDateString('11/2026'));
        $this->assertSame('2026-03-01', ContaPagarCompetencia::toDateString('03/2026'));
        $this->assertSame('2026-11-01', ContaPagarCompetencia::toDateString('112026'));
        $this->assertTrue(ContaPagarCompetencia::isValidMesAno('112026'));
    }

    public function test_data_completa_preserva_mes_ano_no_dia_1(): void
    {
        $this->assertSame('2026-11-01', ContaPagarCompetencia::toDateString('2026-11-03'));
        $this->assertSame('2026-11-01', ContaPagarCompetencia::toDateString('03/11/2026'));
        $this->assertSame('11/2026', ContaPagarCompetencia::formatMesAno('2026-11-03'));
        $this->assertSame('11/2026', ContaPagarCompetencia::formatMesAno('03/11/2026'));
    }

    public function test_vazio_e_invalido(): void
    {
        $this->assertNull(ContaPagarCompetencia::toDateString(''));
        $this->assertTrue(ContaPagarCompetencia::isValidMesAno(''));
        $this->assertFalse(ContaPagarCompetencia::isValidMesAno('13/2026'));
        $this->assertFalse(ContaPagarCompetencia::isValidMesAno('00/2026'));
        $this->assertFalse(ContaPagarCompetencia::isValidMesAno('11/26'));
        $this->assertFalse(ContaPagarCompetencia::isValidMesAno('2026-13'));

        $this->expectException(InvalidArgumentException::class);
        ContaPagarCompetencia::toDateString('13/2026', strict: true);
    }

    public function test_nao_presume_vencimento(): void
    {
        // Sem competência informada permanece null — caller não deve preencher com vencimento.
        $this->assertNull(ContaPagarCompetencia::toDateString(null));
        $this->assertSame('', ContaPagarCompetencia::formatMesAno(null));
    }
}
