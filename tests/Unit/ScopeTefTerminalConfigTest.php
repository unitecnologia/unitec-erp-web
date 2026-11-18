<?php

namespace Tests\Unit;

use App\Models\Terminal;
use App\Support\Erp\Tef\Scope\ScopeTefTerminalConfig;
use App\Support\Erp\Tef\SuperTefTerminalConfig;
use App\Support\Erp\Tef\TefProviderId;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ScopeTefTerminalConfigTest extends TestCase
{
    public function test_credencial_cifra_e_nao_expoe_por_padrao(): void
    {
        $extra = ScopeTefTerminalConfig::mergeIntoExtra(
            SuperTefTerminalConfig::writePayload([
                'ativo' => false,
                'provedor' => TefProviderId::SCOPE,
            ]),
            [
                'ativo' => false,
                'ambiente' => ScopeTefTerminalConfig::AMBIENTE_HOMOLOGACAO,
                'empresa_codigo' => 'EMP-1',
                'loja_codigo' => 'LJ-1',
                'terminal_codigo' => 'PDV-9',
                'servico_host' => '127.0.0.1',
                'servico_porta' => 0,
                'credencial' => 'cred-secreta',
            ]
        );

        $this->assertSame(TefProviderId::SCOPE, $extra['provedor']);
        $this->assertArrayHasKey('scope', $extra);
        $this->assertArrayHasKey('credencial_cipher', $extra['scope']);
        $this->assertArrayNotHasKey('credencial', $extra['scope']);
        $this->assertSame('cred-secreta', Crypt::decryptString($extra['scope']['credencial_cipher']));
        $this->assertNull($extra['scope']['servico_porta']);

        $terminal = new Terminal();
        $terminal->tef_extra = $extra;

        $safe = ScopeTefTerminalConfig::read($terminal, withSecret: false);
        $this->assertTrue($safe['credencial_configured']);
        $this->assertNull($safe['credencial']);
        $this->assertSame('EMP-1', $safe['empresa_codigo']);

        $full = ScopeTefTerminalConfig::read($terminal, withSecret: true);
        $this->assertSame('cred-secreta', $full['credencial']);
    }

    public function test_normalize_provider_aceita_scope_sem_ativar(): void
    {
        $this->assertSame(TefProviderId::SCOPE, SuperTefTerminalConfig::normalizeProvider('scope'));
        $this->assertSame(TefProviderId::SUPERTEF, SuperTefTerminalConfig::normalizeProvider('outro'));
        $this->assertSame(TefProviderId::SUPERTEF, SuperTefTerminalConfig::normalizeProvider('supertef'));
    }
}
