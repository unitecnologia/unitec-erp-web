<?php

namespace Tests\Unit;

use App\Models\Terminal;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Tef\SuperTefApiClient;
use App\Support\Erp\Tef\SuperTefTerminalConfig;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SuperTefCadastroValidacaoTest extends TestCase
{
    public function test_cada_terminal_guarda_pos_e_chave_proprios(): void
    {
        $a = new Terminal(['id' => 1, 'empresa_id' => 10]);
        $b = new Terminal(['id' => 2, 'empresa_id' => 10]);

        $a->tef_extra = SuperTefTerminalConfig::writePayload([
            'ativo' => true,
            'pos_id' => 5665,
            'cliente_chave' => 'cliente-a',
        ]);
        $b->tef_extra = SuperTefTerminalConfig::writePayload([
            'ativo' => true,
            'pos_id' => 9999,
            'cliente_chave' => 'cliente-b',
        ]);

        $cfgA = SuperTefTerminalConfig::read($a, withSecret: true);
        $cfgB = SuperTefTerminalConfig::read($b, withSecret: true);

        $this->assertSame(5665, $cfgA['pos_id']);
        $this->assertSame('cliente-a', $cfgA['cliente_chave']);
        $this->assertSame(9999, $cfgB['pos_id']);
        $this->assertSame('cliente-b', $cfgB['cliente_chave']);
        $this->assertNotSame($cfgA['cliente_chave'], $cfgB['cliente_chave']);
    }

    public function test_leitura_para_ui_nao_devolve_segredo(): void
    {
        $terminal = new Terminal();
        $terminal->tef_extra = SuperTefTerminalConfig::writePayload([
            'ativo' => true,
            'pos_id' => 5665,
            'cliente_chave' => 'nao-vazar',
        ]);

        $ui = SuperTefTerminalConfig::read($terminal, withSecret: false);

        $this->assertTrue($ui['cliente_chave_configured']);
        $this->assertNull($ui['cliente_chave']);
        $this->assertSame(5665, $ui['pos_id']);
        $this->assertStringNotContainsString('nao-vazar', json_encode($ui) ?: '');
    }

    public function test_manter_chave_salva_sem_redigitar(): void
    {
        $terminal = new Terminal();
        $terminal->tef_extra = SuperTefTerminalConfig::writePayload([
            'ativo' => true,
            'pos_id' => 5665,
            'cliente_chave' => 'chave-original',
        ]);

        $terminal->tef_extra = SuperTefTerminalConfig::writePayload([
            'ativo' => true,
            'pos_id' => 5665,
            'cliente_chave' => '',
            'keep_cliente_chave' => true,
        ], $terminal);

        $cfg = SuperTefTerminalConfig::read($terminal, withSecret: true);
        $this->assertSame('chave-original', $cfg['cliente_chave']);
        $this->assertSame('chave-original', Crypt::decryptString($terminal->tef_extra['cliente_chave_cipher']));
    }

    public function test_pdv_continua_com_tef_integrado_desligado(): void
    {
        $this->assertFalse(PdvConfig::make()->usaTef());
    }

    public function test_validate_pos_rejeita_pos_de_outro_cliente(): void
    {
        $client = new SuperTefApiClient('x');
        $result = $client->validatePos([
            ['id' => 100, 'nome' => 'Outra', 'banco' => '', 'marca' => '', 'modelo' => '', 'status' => 1, 'ativada' => true],
        ], 5665);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('não pertence', $result['message']);
    }
}
