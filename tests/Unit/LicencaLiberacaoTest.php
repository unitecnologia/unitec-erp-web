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

    public function test_libera_ate_grace_days_apos_vencimento(): void
    {
        // Vencimento 10/10 + 2 → liberado até 12/10; bloqueia em 13/10.
        $vencimento = '2026-10-10';

        $this->assertSame('2026-10-12', LicencaLiberacao::liberadoAte($vencimento, null, 2)?->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-10-12')->startOfDay());
        $this->assertFalse(LicencaLiberacao::bloqueadoPorData($vencimento, null, null, 2));

        Carbon::setTestNow(Carbon::parse('2026-10-13')->startOfDay());
        $this->assertTrue(LicencaLiberacao::bloqueadoPorData($vencimento, null, null, 2));
    }

    public function test_valido_ate_nao_estende_quando_ha_vencimento(): void
    {
        // valido_ate do portal/cache (ex.: tolerância antiga de 5 dias) não pode
        // empurrar a liberação além de vencimento + grace_days atual.
        $vencimento = '2026-10-10'; // +2 = 12/10
        $validoAteAntigo = '2026-10-15';

        $this->assertSame('2026-10-12', LicencaLiberacao::liberadoAte($vencimento, $validoAteAntigo, 2)?->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-10-13')->startOfDay());
        $this->assertTrue(LicencaLiberacao::bloqueadoPorData($vencimento, $validoAteAntigo, null, 2));
    }

    public function test_valido_ate_e_fallback_sem_vencimento(): void
    {
        $this->assertSame('2026-10-20', LicencaLiberacao::liberadoAte(null, '2026-10-20', 2)?->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-10-20')->startOfDay());
        $this->assertFalse(LicencaLiberacao::bloqueadoPorData(null, '2026-10-20', null, 2));

        Carbon::setTestNow(Carbon::parse('2026-10-21')->startOfDay());
        $this->assertTrue(LicencaLiberacao::bloqueadoPorData(null, '2026-10-20', null, 2));
    }

    public function test_vencimento_no_passado_dentro_da_tolerancia_nao_bloqueia(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12')->startOfDay());

        $this->assertFalse(LicencaLiberacao::bloqueadoPorData('2026-10-10', null, null, 2));
    }

    public function test_sem_datas_nao_bloqueia_por_este_criterio(): void
    {
        $this->assertNull(LicencaLiberacao::liberadoAte(null, null, 2));
        $this->assertFalse(LicencaLiberacao::bloqueadoPorData(null, null, null, 2));
    }

    public function test_aviso_startup_mensagens_por_proximidade(): void
    {
        $vencimento = '2026-10-10';

        // 3+ dias antes: sem aviso.
        Carbon::setTestNow(Carbon::parse('2026-10-07')->startOfDay());
        $this->assertNull(LicencaLiberacao::avisoStartup($vencimento, null, null, 2));

        Carbon::setTestNow(Carbon::parse('2026-10-08')->startOfDay());
        $this->assertSame('Seu sistema vence em 2 dias.', LicencaLiberacao::avisoStartup($vencimento, null, null, 2)['title']);

        Carbon::setTestNow(Carbon::parse('2026-10-09')->startOfDay());
        $this->assertSame('Seu sistema vence amanhã.', LicencaLiberacao::avisoStartup($vencimento, null, null, 2)['title']);

        Carbon::setTestNow(Carbon::parse('2026-10-10')->startOfDay());
        $this->assertSame('Seu sistema vence hoje.', LicencaLiberacao::avisoStartup($vencimento, null, null, 2)['title']);

        Carbon::setTestNow(Carbon::parse('2026-10-11')->startOfDay());
        $aviso = LicencaLiberacao::avisoStartup($vencimento, '2026-10-15', null, 2);
        $this->assertSame('Seu sistema venceu há 1 dia.', $aviso['title']);
        $this->assertNotNull($aviso['body']);
        $this->assertStringContainsString('12/10/2026', (string) $aviso['body']);
        $this->assertStringNotContainsString('15/10/2026', (string) $aviso['body']);

        Carbon::setTestNow(Carbon::parse('2026-10-12')->startOfDay());
        $this->assertSame('Seu sistema venceu há 2 dias.', LicencaLiberacao::avisoStartup($vencimento, null, null, 2)['title']);

        // Fora da tolerância local: sem aviso (tela de bloqueio).
        Carbon::setTestNow(Carbon::parse('2026-10-13')->startOfDay());
        $this->assertNull(LicencaLiberacao::avisoStartup($vencimento, '2026-10-15', null, 2));
    }
}
