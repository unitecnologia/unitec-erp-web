<?php

namespace Tests\Unit;

use App\Models\RhAnexo;
use Carbon\Carbon;
use Tests\TestCase;

class RhAnexoSituacaoTest extends TestCase
{
    public function test_situacao_sem_validade(): void
    {
        $anexo = new RhAnexo(['valido_ate' => null]);

        $this->assertSame(RhAnexo::SIT_SEM, $anexo->situacaoValidade());
        $this->assertSame('—', $anexo->situacaoValidadeLabel());
    }

    public function test_situacao_ok_vencendo_e_vencido(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12'));

        $ok = new RhAnexo(['valido_ate' => '2027-01-15']);
        $vencendo = new RhAnexo(['valido_ate' => '2026-11-20']);
        $vencido = new RhAnexo(['valido_ate' => '2026-11-01']);

        $this->assertSame(RhAnexo::SIT_OK, $ok->situacaoValidade());
        $this->assertSame(RhAnexo::SIT_VENCENDO, $vencendo->situacaoValidade());
        $this->assertSame(RhAnexo::SIT_VENCIDO, $vencido->situacaoValidade());
        $this->assertSame('Vencido', $vencido->situacaoValidadeLabel());

        Carbon::setTestNow();
    }
}
