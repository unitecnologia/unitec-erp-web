<?php

namespace Tests\Unit;

use App\Models\Terminal;
use App\Support\Erp\Tef\SuperTefApiClient;
use App\Support\Erp\Tef\SuperTefTerminalConfig;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SuperTefTerminalConfigTest extends TestCase
{
    public function test_write_payload_encrypts_cliente_chave_and_omits_plaintext(): void
    {
        $payload = SuperTefTerminalConfig::writePayload([
            'ativo' => true,
            'provedor' => 'supertef',
            'pos_id' => 5665,
            'cliente_chave' => 'chave-teste-dev',
            'keep_cliente_chave' => false,
        ]);

        $this->assertTrue($payload['ativo']);
        $this->assertSame(5665, $payload['pos_id']);
        $this->assertArrayNotHasKey('cliente_chave', $payload);
        $this->assertArrayHasKey('cliente_chave_cipher', $payload);
        $this->assertSame('chave-teste-dev', Crypt::decryptString($payload['cliente_chave_cipher']));
    }

    public function test_read_does_not_expose_secret_by_default(): void
    {
        $terminal = new Terminal();
        $terminal->tef_extra = SuperTefTerminalConfig::writePayload([
            'ativo' => true,
            'pos_id' => 5665,
            'cliente_chave' => 'secreto',
        ]);

        $safe = SuperTefTerminalConfig::read($terminal, withSecret: false);
        $this->assertTrue($safe['cliente_chave_configured']);
        $this->assertNull($safe['cliente_chave']);

        $full = SuperTefTerminalConfig::read($terminal, withSecret: true);
        $this->assertSame('secreto', $full['cliente_chave']);
    }

    public function test_write_payload_persiste_max_parcelas_credito_e_forca_installment_loja(): void
    {
        $payload = SuperTefTerminalConfig::writePayload([
            'ativo' => true,
            'pos_id' => 5665,
            'cliente_chave' => 'chave',
            'credito_max_parcelas' => 6,
            'credito_installment_type' => SuperTefTerminalConfig::INSTALLMENT_TYPE_ADMINISTRADORA,
        ]);

        $this->assertSame(6, $payload['credito_max_parcelas']);
        $this->assertSame(SuperTefTerminalConfig::INSTALLMENT_TYPE_LOJA, $payload['credito_installment_type']);

        $terminal = new Terminal();
        $terminal->tef_extra = $payload;
        $read = SuperTefTerminalConfig::read($terminal, withSecret: false);
        $this->assertSame(6, $read['credito_max_parcelas']);
        $this->assertSame(1, $read['credito_installment_type']);
    }

    public function test_validate_pos_requires_activation(): void
    {
        $client = new SuperTefApiClient('token-fake');
        $items = [
            [
                'id' => 5665,
                'nome' => 'Emulador',
                'banco' => '',
                'marca' => '',
                'modelo' => '',
                'status' => 1,
                'ativada' => false,
            ],
        ];

        $result = $client->validatePos($items, 5665);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('sem ativação', $result['message']);

        $items[0]['ativada'] = true;
        $ok = $client->validatePos($items, 5665);
        $this->assertTrue($ok['ok']);
    }
}
