<?php

namespace Tests\Unit;

use App\Support\Erp\License\LicencaLiberacao;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class LicencaLiberacaoTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_libera_ate_quinto_dia_apos_vencimento(): void
    {
        // Vencimento 10/10 → liberado até 15/10; bloqueia em 16/10.
        $vencimento = '2026-10-10';

        $this->assertSame('2026-10-15', LicencaLiberacao::liberadoAte($vencimento, null, 5)?->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-10-15')->startOfDay());
        $this->assertFalse(LicencaLiberacao::bloqueadoPorData($vencimento, null, null, 5));

        Carbon::setTestNow(Carbon::parse('2026-10-16')->startOfDay());
        $this->assertTrue(LicencaLiberacao::bloqueadoPorData($vencimento, null, null, 5));
    }

    public function test_valido_ate_pode_estender_a_janela(): void
    {
        $vencimento = '2026-10-10'; // +5 = 15/10
        $validoAte = '2026-10-20';

        $this->assertSame('2026-10-20', LicencaLiberacao::liberadoAte($vencimento, $validoAte, 5)?->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-10-18')->startOfDay());
        $this->assertFalse(LicencaLiberacao::bloqueadoPorData($vencimento, $validoAte, null, 5));

        Carbon::setTestNow(Carbon::parse('2026-10-21')->startOfDay());
        $this->assertTrue(LicencaLiberacao::bloqueadoPorData($vencimento, $validoAte, null, 5));
    }

    public function test_vencimento_no_passado_dentro_da_tolerancia_nao_bloqueia(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12')->startOfDay());

        $this->assertFalse(LicencaLiberacao::bloqueadoPorData('2026-10-10', null, null, 5));
    }

    public function test_sem_datas_nao_bloqueia_por_este_criterio(): void
    {
        $this->assertNull(LicencaLiberacao::liberadoAte(null, null, 5));
        $this->assertFalse(LicencaLiberacao::bloqueadoPorData(null, null, null, 5));
    }

    public function test_aviso_startup_mensagens_por_proximidade(): void
    {
        $vencimento = '2026-10-10';

        // 3+ dias antes: sem aviso.
        Carbon::setTestNow(Carbon::parse('2026-10-07')->startOfDay());
        $this->assertNull(LicencaLiberacao::avisoStartup($vencimento, null, null, 5));

        Carbon::setTestNow(Carbon::parse('2026-10-08')->startOfDay());
        $this->assertSame('Seu sistema vence em 2 dias.', LicencaLiberacao::avisoStartup($vencimento, null, null, 5)['title']);

        Carbon::setTestNow(Carbon::parse('2026-10-09')->startOfDay());
        $this->assertSame('Seu sistema vence amanhã.', LicencaLiberacao::avisoStartup($vencimento, null, null, 5)['title']);

        Carbon::setTestNow(Carbon::parse('2026-10-10')->startOfDay());
        $this->assertSame('Seu sistema vence hoje.', LicencaLiberacao::avisoStartup($vencimento, null, null, 5)['title']);

        Carbon::setTestNow(Carbon::parse('2026-10-11')->startOfDay());
        $aviso = LicencaLiberacao::avisoStartup($vencimento, '2026-10-15', null, 5);
        $this->assertSame('Seu sistema venceu há 1 dia.', $aviso['title']);
        $this->assertNotNull($aviso['body']);
        $this->assertStringContainsString('15/10/2026', (string) $aviso['body']);

        Carbon::setTestNow(Carbon::parse('2026-10-12')->startOfDay());
        $this->assertSame('Seu sistema venceu há 2 dias.', LicencaLiberacao::avisoStartup($vencimento, null, null, 5)['title']);
    }
}
