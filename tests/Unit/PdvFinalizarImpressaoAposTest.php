<?php

namespace Tests\Unit;

use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use Tests\TestCase;

class PdvFinalizarImpressaoAposTest extends TestCase
{
    public function test_pedido_e_nfc_e_perguntam_impressao_apos_quando_terminal_pede(): void
    {
        $this->assertTrue(PdvFinalizarOperacao::solicitaConfirmacaoImpressaoApos(
            PdvFinalizarOperacao::PEDIDO,
            true,
        ));
        $this->assertTrue(PdvFinalizarOperacao::solicitaConfirmacaoImpressaoApos(
            PdvFinalizarOperacao::NFCE_TRANSMITIR,
            true,
        ));
        $this->assertTrue(PdvFinalizarOperacao::solicitaConfirmacaoImpressaoApos(
            PdvFinalizarOperacao::NFCE_CONTINGENCIA,
            true,
        ));
        $this->assertTrue(PdvFinalizarOperacao::solicitaConfirmacaoImpressaoApos(
            PdvFinalizarOperacao::FINALIZAR,
            true,
        ));
        $this->assertFalse(PdvFinalizarOperacao::solicitaConfirmacaoImpressaoApos(
            PdvFinalizarOperacao::NFCE_TRANSMITIR,
            false,
        ));
    }
}
