<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Garante que Opções → Reimprimir NFC-e e Ctrl+I disparam reimpressão,
 * não o stub de emissão fiscal.
 */
class PdvReimprimirNfceWiringTest extends TestCase
{
    public function test_botao_opcoes_chama_open_reimprimir_nfce_modal(): void
    {
        $blade = (string) file_get_contents(base_path('packages/unitec-pdv-ui/resources/views/modals/options.blade.php'));

        $this->assertStringContainsString('wire:click="openReimprimirNfceModal"', $blade);
        $this->assertStringContainsString('Reimprimir NFCe', $blade);
        $this->assertStringContainsString('Ctrl+I', $blade);
        $this->assertStringNotContainsString('moduleStubNfce', $blade);
        $this->assertStringNotContainsString('Use o fechamento fiscal (F4/F6)', $blade);
    }

    public function test_atalho_ctrl_i_aponta_para_mesma_rotina(): void
    {
        $js = (string) file_get_contents(public_path('js/erp-pdv.js'));

        $this->assertStringContainsString("i: ['openReimprimirNfceModal']", $js);
        $this->assertStringNotContainsString("i: ['moduleStubNfce']", $js);
        $this->assertStringContainsString('erp-pdv-focus-reimprimir-nfce', $js);
        $this->assertStringContainsString('confirmReimprimirNfce', $js);
        $this->assertStringContainsString('moveReimprimirNfceSelection', $js);
    }

    public function test_rotina_so_reimprime_via_impressor_existente_sem_emitir(): void
    {
        $trait = (string) file_get_contents(app_path('Filament/Pages/Concerns/ManagesPdvReimprimirNfce.php'));
        $service = (string) file_get_contents(app_path('Support/Erp/Pdv/PdvNfceReimpressao.php'));
        $ui = (string) file_get_contents(app_path('Filament/Pages/Concerns/ManagesPdvUi.php'));

        $this->assertStringContainsString('function openReimprimirNfceModal', $trait);
        $this->assertStringContainsString('function confirmReimprimirNfce', $trait);
        $this->assertStringContainsString('imprimirNfceCupomPosVenda', $trait);
        $this->assertStringContainsString('PdvNfceReimpressao::podeReimprimir', $trait);
        $this->assertStringContainsString('Não há NFC-e disponível para reimpressão.', $trait);

        $this->assertStringNotContainsString('transmitir(', $trait);
        $this->assertStringNotContainsString('PdvVendaNfceService', $trait);
        $this->assertStringNotContainsString('PdvNfceTransmissaoService', $trait);
        $this->assertStringNotContainsString('SuperTef', $trait);

        $this->assertStringContainsString('Não emite, não retransmite', $service);
        $this->assertStringContainsString('STATUS_AUTORIZADA', $service);
        $this->assertStringContainsString('STATUS_CONTINGENCIA', $service);

        $this->assertStringContainsString('use ManagesPdvReimprimirNfce;', $ui);
        $this->assertStringNotContainsString('function moduleStubNfce', $ui);
        $this->assertStringNotContainsString('Use o fechamento fiscal (F4/F6) no PDV para emitir NFC-e.', $ui);
    }

    public function test_modal_reimprimir_nfce_esta_no_screen_ativo(): void
    {
        $screen = (string) file_get_contents(base_path('packages/unitec-pdv-ui/resources/views/screen.blade.php'));
        $modal = (string) file_get_contents(base_path('packages/unitec-pdv-ui/resources/views/modals/reimprimir-nfce.blade.php'));

        $this->assertStringContainsString("reimprimir_nfce', 'pdvui::modals.reimprimir-nfce')", $screen);
        $this->assertStringContainsString('confirmReimprimirNfce', $modal);
        $this->assertStringContainsString('erp-pdv-reimprimir-nfce-search', $modal);
        $this->assertStringContainsString('erp-pdv__grid-row--selected', $modal);
    }

    public function test_css_modal_grid_tem_destaque_azul_na_linha_selecionada(): void
    {
        $css = (string) file_get_contents(public_path('css/erp-pdv.css'));

        $this->assertStringContainsString('.erp-pdv-modal__grid tbody tr.erp-pdv__grid-row--selected td', $css);
        $this->assertStringContainsString('background: #93c5fd', $css);
    }
}
