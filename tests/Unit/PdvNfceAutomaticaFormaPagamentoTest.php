<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Wiring: NFC-e (PDV) na forma força transmissão automática no PDV online.
 */
class PdvNfceAutomaticaFormaPagamentoTest extends TestCase
{
    public function test_checkbox_cadastro_renomeado_para_nfce_pdv(): void
    {
        $blade = (string) file_get_contents(resource_path(
            'views/filament/components/erp/formas-pagamento/modal.blade.php'
        ));

        $this->assertStringContainsString('NFC-e (PDV)', $blade);
        $this->assertStringContainsString('wire:model="form.nfce"', $blade);
    }

    public function test_pdv_online_carrega_flag_e_resolve_operacao_automatica(): void
    {
        $venda = (string) file_get_contents(app_path('Filament/Pages/Concerns/ManagesPdvVenda.php'));
        $config = (string) file_get_contents(app_path('Filament/Pages/Concerns/ManagesPdvConfig.php'));
        $superTef = (string) file_get_contents(app_path('Filament/Pages/Concerns/ManagesPdvSuperTef.php'));
        $telaVenda = (string) file_get_contents(app_path('Filament/Pages/ForcaVendasTelaVendaPage.php'));

        $this->assertStringContainsString("'nfce'", $venda);
        $this->assertStringContainsString("'nfce' => (bool) (\$forma->nfce ?? false)", $venda);
        $this->assertStringContainsString('exigeNfceAutomaticaPdv', $venda);
        $this->assertStringContainsString('resolverOperacaoFinalizarPdv', $venda);
        $this->assertStringContainsString('operacaoFinalizarPermitidaPdv', $venda);
        $this->assertStringContainsString('PdvFinalizarOperacao::NFCE_TRANSMITIR', $venda);

        $this->assertStringContainsString('resolverPdvFinalizarOperacaoBotoes', $config);
        $this->assertStringContainsString('resolverPdvFinalizarOperacaoUnica', $config);

        $ui = (string) file_get_contents(app_path('Filament/Pages/Concerns/ManagesPdvUi.php'));
        $this->assertStringContainsString(
            'ManagesPdvVenda::resolverPdvFinalizarOperacaoBotoes insteadof ManagesPdvConfig',
            $ui,
        );

        $this->assertStringContainsString('resolverOperacaoFinalizarPdv', $superTef);
        $this->assertStringContainsString('operacaoFinalizarPermitidaPdv', $superTef);

        // Tela de Venda permanece sem o flag/regra.
        $this->assertStringNotContainsString("'nfce' => (bool)", $telaVenda);
        $this->assertStringNotContainsString('exigeNfceAutomatica', $telaVenda);
    }
}
