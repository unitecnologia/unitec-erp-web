<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Tela de Venda: Canhoto POS com "Considerar à vista" (1x, dias 0) e overlay leve (skipRender).
 */
class TelaVendaCartaoAVistaCanhotoWiringTest extends TestCase
{
    public function test_meios_pagamento_carregam_considerar_a_vista_e_modo_prazo(): void
    {
        $page = (string) file_get_contents(app_path('Filament/Pages/ForcaVendasTelaVendaPage.php'));

        $this->assertStringContainsString("'considerar_a_vista' => (bool) (\$f->considerar_a_vista ?? false)", $page);
        $this->assertStringContainsString("'usa_pos' => (bool) (\$f->usa_pos ?? false)", $page);
        $this->assertStringContainsString("'modo_prazo' => FormaPagamento::normalizeModoPrazo(\$f->modo_prazo ?? null)", $page);
        $this->assertStringContainsString('gerarParcelasCartaoPorDias([0])', $page);
        $this->assertStringContainsString('isFormaConsiderarAVista', $page);
        $this->assertStringContainsString('isFormaUsaPos', $page);
        $this->assertStringContainsString('syncFvCanhotoModalUi', $page);
        $this->assertStringContainsString('ensureFvCanhotoOpcoesCached', $page);
        $this->assertStringContainsString('$this->skipRender()', $page);

        // Canhoto POS na TV: abertura/resolução exige usa_pos (isFormaUsaPos).
        $this->assertGreaterThanOrEqual(4, substr_count($page, 'PdvFinalizarPagamentosHelper::isFormaUsaPos'));
        $this->assertStringContainsString(
            'PdvFinalizarPagamentosHelper::isFormaUsaPos($meio)',
            $page,
        );
        $this->assertStringContainsString(
            'PdvFinalizarPagamentosHelper::isFormaUsaPos($meioPreview)',
            $page,
        );
        $this->assertStringContainsString('fvTemCartaoConsiderarAVista', $page);
        $this->assertStringContainsString("'dias' => [0]", $page);
    }

    public function test_modal_canhoto_usa_alpine_xshow_e_oculta_parcelas_a_vista(): void
    {
        $blade = (string) file_get_contents(resource_path(
            'views/filament/components/erp/forca-vendas/tela-venda/finalizacao-canhoto.blade.php'
        ));
        $fin = (string) file_get_contents(resource_path(
            'views/filament/components/erp/forca-vendas/tela-venda/finalizacao.blade.php'
        ));

        $this->assertStringContainsString('finalizacao-canhoto', $fin);
        $this->assertStringContainsString('x-show="$wire.finalizarCartaoCanhotoAberta"', $blade);
        $this->assertStringContainsString('fvCanhotoOcultarParcelas', $blade);
        $this->assertStringContainsString('À vista (1x) — informe NSU/autorização e conclua.', $blade);
        $this->assertStringContainsString('Qtd. Parcelas', $blade);
        $this->assertStringContainsString('fvCanhotoMaquininhas', $blade);
        $this->assertStringContainsString('fvCanhotoBandeiras', $blade);
        $this->assertStringNotContainsString('CartaoMaquininha::optionsAtivas()', $blade);
        $this->assertStringNotContainsString('CartaoBandeira::optionsAtivas()', $blade);
    }
}
