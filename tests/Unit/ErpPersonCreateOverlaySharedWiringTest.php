<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Padronização + Cadastrar Cliente: peças compartilhadas e telas aplicáveis.
 */
class ErpPersonCreateOverlaySharedWiringTest extends TestCase
{
    public function test_shared_assets_existem(): void
    {
        $this->assertFileExists(resource_path('views/filament/components/erp/shared/btn-cadastrar-cliente.blade.php'));
        $this->assertFileExists(resource_path('views/filament/components/erp/shared/person-create-overlay.blade.php'));
        $this->assertFileExists(resource_path('views/filament/components/erp/shared/product-create-overlay.blade.php'));
        $this->assertFileExists(app_path('Filament/Concerns/ManagesErpPersonCreateOverlay.php'));
        $this->assertFileExists(app_path('Filament/Concerns/ManagesErpProductCreateOverlay.php'));
        $this->assertFileExists(public_path('css/erp-btn-cadastrar-cliente.css'));
        $this->assertFileExists(public_path('js/erp-form-overlay-fit.js'));
    }

    public function test_nfse_tem_botao_e_overlay_de_produto(): void
    {
        $page = (string) file_get_contents(app_path('Filament/Pages/NfsePage.php'));
        $modal = (string) file_get_contents(resource_path('views/filament/components/erp/nfse/lancamento-modal.blade.php'));

        $this->assertStringContainsString('ManagesErpProductCreateOverlay', $page);
        $this->assertStringContainsString('openProdutosCadastro', $modal);
        $this->assertStringContainsString('F8</kbd> | Produtos', $modal);
        $this->assertStringContainsString('filament.components.erp.shared.product-create-overlay', $modal);
        $this->assertGreaterThan(
            strpos($modal, 'Espelho da NFS-e'),
            strpos($modal, 'F8</kbd> | Produtos'),
            'Botão Produtos deve ficar depois do Espelho'
        );
    }

    public function test_telas_aplicaveis_incluem_botao_compartilhado(): void
    {
        $paths = [
            resource_path('views/filament/components/erp/forca-vendas/tela-venda/venda.blade.php'),
            resource_path('views/filament/components/erp/orcamentos/form/header.blade.php'),
            resource_path('views/filament/components/erp/nfe/lancamento-cliente.blade.php'),
            resource_path('views/filament/components/erp/nfse/lancamento-modal.blade.php'),
            resource_path('views/filament/components/erp/receber/form-modal.blade.php'),
            resource_path('views/filament/components/erp/pdv/modals/finalizar.blade.php'),
            base_path('packages/unitec-pdv-ui/resources/views/modals/finalizar.blade.php'),
        ];

        foreach ($paths as $path) {
            $src = (string) file_get_contents($path);
            $this->assertStringContainsString(
                'filament.components.erp.shared.btn-cadastrar-cliente',
                $src,
                'Botão compartilhado ausente em '.$path
            );
        }
    }

    public function test_botao_compartilhado_ignora_disabled_nao_escalar(): void
    {
        $btn = (string) file_get_contents(resource_path(
            'views/filament/components/erp/shared/btn-cadastrar-cliente.blade.php'
        ));

        $this->assertStringContainsString('$btnDisabled', $btn);
        $this->assertStringContainsString('is_bool($disabled)', $btn);
        $this->assertStringContainsString('@disabled($btnDisabled)', $btn);
    }

    public function test_nfe_tem_cadastro_produto_como_os_nfse(): void
    {
        $modal = (string) file_get_contents(resource_path(
            'views/filament/components/erp/nfe/lancamento-modal.blade.php'
        ));
        $php = (string) file_get_contents(app_path(
            'Filament/Resources/NfeResource/Pages/Concerns/ManagesNfeEmissaoModal.php'
        ));

        $this->assertStringContainsString('openProdutosCadastro', $modal);
        $this->assertStringContainsString('F8', $modal);
        $this->assertStringContainsString('filament.components.erp.shared.product-create-overlay', $modal);
        $this->assertStringContainsString('ManagesErpProductCreateOverlay', $php);
        $this->assertStringContainsString('applyProductCreatedFromOverlay', $php);
    }

    public function test_create_person_url_usa_os_embed(): void
    {
        $files = [
            app_path('Filament/Concerns/ManagesErpPersonCreateOverlay.php'),
            app_path('Filament/Pages/Concerns/ManagesForcaVendasTelaVendaClienteCadastro.php'),
            app_path('Filament/Pages/Concerns/ManagesPdvUi.php'),
        ];

        foreach ($files as $path) {
            $src = (string) file_get_contents($path);
            $this->assertStringContainsString('os_embed=1', $src, basename($path));
            $this->assertStringContainsString('tipo=clientes', $src, basename($path));
        }
    }
}
