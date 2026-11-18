<?php

namespace Tests\Unit;

use App\Models\RhTreinamentoParticipante;
use Carbon\Carbon;
use Tests\TestCase;

class RhTreinamentoParticipanteValidadeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_validade_situacao_em_dia_proximo_vencido_sem(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12'));

        $sem = new RhTreinamentoParticipante(['vencimento_em' => null]);
        $this->assertSame('sem', $sem->validadeSituacao());

        $vencido = new RhTreinamentoParticipante(['vencimento_em' => '2026-11-11']);
        $this->assertSame('vencido', $vencido->validadeSituacao());

        $proximo = new RhTreinamentoParticipante(['vencimento_em' => '2026-11-20']);
        $this->assertSame('proximo', $proximo->validadeSituacao(30));

        $emDia = new RhTreinamentoParticipante(['vencimento_em' => '2027-01-15']);
        $this->assertSame('em_dia', $emDia->validadeSituacao(30));
    }
}
