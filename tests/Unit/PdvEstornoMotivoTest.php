<?php

namespace Tests\Unit;

use App\Support\Erp\Pdv\PdvEstornoMotivo;
use PHPUnit\Framework\TestCase;

final class PdvEstornoMotivoTest extends TestCase
{
    public function test_rejeita_motivo_curto(): void
    {
        $this->assertSame(
            'Motivo do cancelamento deve ter no mínimo 15 caracteres.',
            PdvEstornoMotivo::validate('Curto'),
        );
    }

    public function test_aceita_motivo_monitor_com_minimo_5(): void
    {
        $this->assertNull(
            PdvEstornoMotivo::validate('abcde', PdvEstornoMotivo::MIN_LENGTH_MONITOR_FV),
        );
        $this->assertNotNull(
            PdvEstornoMotivo::validate('abcd', PdvEstornoMotivo::MIN_LENGTH_MONITOR_FV),
        );
    }

    public function test_aceita_motivo_valido(): void
    {
        $motivo = 'Cliente desistiu da compra no balcao.';

        $this->assertNull(PdvEstornoMotivo::validate($motivo));
        $this->assertSame($motivo, PdvEstornoMotivo::normalize($motivo));
    }

    public function test_motivo_automatico_e_valido(): void
    {
        $this->assertNull(PdvEstornoMotivo::validate(PdvEstornoMotivo::MOTIVO_AUTOMATICO));
    }
}
