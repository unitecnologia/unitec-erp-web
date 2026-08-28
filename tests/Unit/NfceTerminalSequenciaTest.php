<?php

namespace Tests\Unit;

use App\Models\Terminal;
use App\Models\VendasParametro;
use App\Support\Fiscal\NfceTerminalSequencia;
use Tests\TestCase;

class NfceTerminalSequenciaTest extends TestCase
{
    public function test_proximo_piso_nao_volta_abaixo_do_contador_da_empresa(): void
    {
        $terminal = new Terminal([
            'empresa_id' => 0,
            'serie' => '1',
            'numeracao_inicial' => 1,
        ]);

        $params = new VendasParametro([
            'empresa_id' => 0,
            'serie' => '1',
            'numero' => 41,
        ]);

        $this->assertSame(41, NfceTerminalSequencia::proximoPiso($terminal, $params));
    }

    public function test_serie_efetiva_cai_para_empresa_quando_caixa_nao_tem(): void
    {
        $terminal = new Terminal(['serie' => null]);
        $params = new VendasParametro(['serie' => '3']);

        $this->assertSame('3', NfceTerminalSequencia::serieEfetiva($terminal, $params));
        $this->assertSame(3, NfceTerminalSequencia::serieEfetivaInt($terminal, $params));
    }

    public function test_mesma_serie_ignora_zeros_a_esquerda(): void
    {
        $this->assertTrue(NfceTerminalSequencia::mesmaSerie('1', '001'));
        $this->assertFalse(NfceTerminalSequencia::mesmaSerie('1', '2'));
    }
}
