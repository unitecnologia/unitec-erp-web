<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Tela de Venda: botão Cadastrar Cliente reutiliza o overlay de Pessoas do Orçamento.
 */
class TelaVendaClienteCadastroWiringTest extends TestCase
{
    public function test_page_usa_trait_de_cadastro_rapido_de_cliente(): void
    {
        $page = (string) file_get_contents(app_path('Filament/Pages/ForcaVendasTelaVendaPage.php'));
        $trait = (string) file_get_contents(app_path(
            'Filament/Pages/Concerns/ManagesForcaVendasTelaVendaClienteCadastro.php'
        ));

        $this->assertStringContainsString('ManagesForcaVendasTelaVendaClienteCadastro', $page);
        $this->assertStringContainsString("PersonResource::getUrl('create').'?tipo=clientes&orcamento=1&os_embed=1'", $trait);
        $this->assertStringContainsString('openPessoasCadastro', $trait);
        $this->assertStringContainsString('applyOverlayPersonSaved', $trait);
        $this->assertStringContainsString('selecionarCliente($clienteId)', $trait);
    }

    public function test_ui_tem_botao_e_overlay_sem_duplicar_pesquisa(): void
    {
        $venda = (string) file_get_contents(resource_path(
            'views/filament/components/erp/forca-vendas/tela-venda/venda.blade.php'
        ));
        $screen = (string) file_get_contents(resource_path(
            'views/filament/components/erp/forca-vendas/tela-venda/screen.blade.php'
        ));
        $guard = (string) file_get_contents(public_path('js/erp-fv-tela-venda-guard.js'));

        $btn = (string) file_get_contents(resource_path(
            'views/filament/components/erp/shared/btn-cadastrar-cliente.blade.php'
        ));
        $this->assertStringContainsString('btn-cadastrar-cliente', $venda);
        $this->assertStringContainsString('Cadastrar Cliente', $btn);
        $this->assertStringContainsString('wire:model.live.debounce.250ms="clienteBusca"', $venda);

        $this->assertStringContainsString('Cadastro de Clientes', $screen);
        $this->assertStringContainsString('personOverlayUrl', $screen);
        $this->assertStringContainsString('closePersonOverlay', $screen);

        $this->assertStringContainsString("event.data.clienteId", $guard);
        $this->assertStringContainsString("applyOverlayPersonSaved", $guard);
    }
}
