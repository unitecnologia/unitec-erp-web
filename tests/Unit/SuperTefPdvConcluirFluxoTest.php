<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Regressão: SuperTEF no PDV precisa estar na view ATIVA (pdvui::modals.finalizar)
 * e abrir o modal antes de enviar a cobrança (wire:init), gravando o pedido após status 4.
 */
class SuperTefPdvConcluirFluxoTest extends TestCase
{
    public function test_view_ativa_pdvui_contem_modal_supertef_e_wire_init(): void
    {
        $blade = file_get_contents(base_path('packages/unitec-pdv-ui/resources/views/modals/finalizar.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('finalizarSuperTefAberta', $blade);
        $this->assertStringContainsString('erp-pdv-supertef-title', $blade);
        $this->assertStringContainsString('wire:init="enviarSuperTefPdvAgora"', $blade);
        $this->assertStringContainsString('wire:poll.4s="pollFinalizarSuperTef"', $blade);
        $this->assertStringContainsString('cancelarPagamentoSuperTef', $blade);
        $this->assertStringContainsString('Cancelar pagamento', $blade);
        $this->assertStringContainsString('confirmarNovaCobrancaSuperTef', $blade);
        $this->assertStringContainsString('Iniciar nova cobrança', $blade);
        $this->assertStringContainsString('verificarPagamentoAnteriorSuperTef', $blade);
        $this->assertStringContainsString('Cancelar cobrança anterior', $blade);
        $this->assertStringContainsString('data-supertef=', $blade);
    }

    public function test_abrir_supertef_nao_envia_na_mesma_request(): void
    {
        $src = file_get_contents(app_path('Filament/Pages/Concerns/ManagesPdvSuperTef.php'));

        $this->assertIsString($src);
        $this->assertStringContainsString('Iniciando cobrança', $src);
        $this->assertStringContainsString('wire:init', file_get_contents(base_path('packages/unitec-pdv-ui/resources/views/modals/finalizar.blade.php')));
        $this->assertDoesNotMatchRegularExpression(
            '/protected function abrirSuperTefPdv\(\): bool\s*\{[^}]*enviarSuperTefPdvAgora\(\)/s',
            $src,
        );
        $this->assertStringContainsString('finalizarPedidoAposSuperTefPago', $src);
        $this->assertStringContainsString('askPrintAfter', $src);
    }

    public function test_pago_grava_pedido_antes_da_impressao(): void
    {
        $src = file_get_contents(app_path('Filament/Pages/Concerns/ManagesPdvSuperTef.php'));

        $this->assertIsString($src);
        $this->assertStringContainsString("SUPERTEF_MSG_APROVADO = 'Pagamento aprovado.'", $src);
        $this->assertStringContainsString("SUPERTEF_MSG_FINALIZANDO = 'Finalizando pedido.'", $src);
        $this->assertStringContainsString('cancelarPagamentoSuperTef', $src);
        $this->assertStringContainsString('Fechar ≠ cancelar', $src);
        $this->assertStringContainsString('finalizarPedidoAposSuperTefPago', $src);
        $this->assertStringContainsString('executarFinalizarVendaPedido(imprimir: ! $askPrintAfter, askPrintAfter: $askPrintAfter)', $src);
        // NFC-e SuperTEF: mesma pergunta Sim/Não após gravar (não reentra em confirmFinalizarComOperacao).
        $this->assertStringContainsString('solicitaConfirmacaoImpressaoApos', $src);
        $this->assertStringContainsString('executarFinalizarVendaNfce($operacao, imprimir: ! $askPrintAfter)', $src);
        // Fora do wire:poll — senão o modal Sim/Não não remonta.
        $this->assertStringContainsString('concluirVendaAposSuperTefPago', $src);
        $this->assertStringContainsString('finalizarSuperTefAguardandoConclusao', $src);
        $blade = (string) file_get_contents(base_path('packages/unitec-pdv-ui/resources/views/modals/finalizar.blade.php'));
        $this->assertStringContainsString('wire:init="concluirVendaAposSuperTefPago"', $blade);
    }

    public function test_js_reseta_submitting_no_supertef_como_no_pix(): void
    {
        $js = file_get_contents(public_path('js/erp-pdv.js'));

        $this->assertIsString($js);
        $this->assertStringContainsString("Livewire.on('erp-pdv-supertef-opened'", $js);
        $this->assertStringContainsString("Livewire.on('erp-pdv-supertef-paid'", $js);
        $this->assertStringContainsString("footer?.dataset?.supertef === '1'", $js);
        $this->assertStringContainsString('#erp-pdv-supertef-title', $js);
    }

    public function test_offline_defaults_expoem_stubs_supertef(): void
    {
        $src = file_get_contents(base_path('packages/unitec-pdv-ui/src/Concerns/ProvidesPdvErpUiDefaults.php'));

        $this->assertIsString($src);
        $this->assertStringContainsString('finalizarSuperTefAberta = false', $src);
        $this->assertStringContainsString('function finalizarTemSuperTefComValor()', $src);
    }
}
