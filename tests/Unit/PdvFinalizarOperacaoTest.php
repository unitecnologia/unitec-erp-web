<?php

namespace Tests\Unit;

use App\Models\Terminal;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use Tests\TestCase;

class PdvFinalizarOperacaoTest extends TestCase
{
    public function test_it_returns_pedido_and_nfce_buttons_for_hybrid_terminal(): void
    {
        $terminal = new Terminal([
            'exibe_f3' => false,
            'exibe_f4' => true,
            'exibe_f5' => true,
            'exibe_f6' => false,
        ]);

        $botoes = PdvFinalizarOperacao::botoes($terminal);

        $this->assertCount(2, $botoes);
        $this->assertSame(PdvFinalizarOperacao::NFCE_TRANSMITIR, $botoes[0]['key']);
        $this->assertSame(PdvFinalizarOperacao::PEDIDO, $botoes[1]['key']);
        $this->assertNull(PdvFinalizarOperacao::operacaoUnica($terminal));
    }

    public function test_it_returns_single_concluir_for_pedido_only_terminal(): void
    {
        $terminal = new Terminal([
            'exibe_f3' => false,
            'exibe_f4' => false,
            'exibe_f5' => true,
            'exibe_f6' => false,
        ]);

        $botoes = PdvFinalizarOperacao::botoes($terminal);

        $this->assertCount(1, $botoes);
        $this->assertSame(PdvFinalizarOperacao::PEDIDO, $botoes[0]['key']);
        $this->assertSame('F10', $botoes[0]['atalho']);
        $this->assertSame('Concluir', $botoes[0]['label']);
        $this->assertTrue($botoes[0]['primary']);
        $this->assertSame(PdvFinalizarOperacao::PEDIDO, PdvFinalizarOperacao::operacaoUnica($terminal));
    }

    public function test_it_marks_fiscal_operations_as_stub_only(): void
    {
        $this->assertTrue(PdvFinalizarOperacao::isFiscal(PdvFinalizarOperacao::NFCE_TRANSMITIR));
        $this->assertFalse(PdvFinalizarOperacao::isFiscal(PdvFinalizarOperacao::PEDIDO));
    }

    public function test_it_requests_print_confirmation_for_pedido_and_nfce_transmitir(): void
    {
        $this->assertTrue(PdvFinalizarOperacao::solicitaConfirmacaoImpressao(PdvFinalizarOperacao::PEDIDO));
        $this->assertTrue(PdvFinalizarOperacao::solicitaConfirmacaoImpressao(PdvFinalizarOperacao::NFCE_TRANSMITIR));
        $this->assertFalse(PdvFinalizarOperacao::solicitaConfirmacaoImpressao(PdvFinalizarOperacao::NFCE_CONTINGENCIA));
        $this->assertFalse(PdvFinalizarOperacao::solicitaConfirmacaoImpressao(PdvFinalizarOperacao::FINALIZAR));
    }
}
