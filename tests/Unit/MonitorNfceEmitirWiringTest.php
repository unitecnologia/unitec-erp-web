<?php

namespace Tests\Unit;

use App\Support\Erp\Nfce\NfceRegularizacaoService;
use Tests\TestCase;

/**
 * Wiring do botão Emitir NFC-e no Monitor — sem DB e sem SEFAZ.
 */
class MonitorNfceEmitirWiringTest extends TestCase
{
    public function test_action_bar_liga_emitir_nfce_ao_estado_e_acao(): void
    {
        $blade = file_get_contents(resource_path('views/filament/components/erp/forca-vendas/monitor-action-bar.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('$temMarcados = count($this->selecionados) > 0', $blade);
        $this->assertStringContainsString('$this->nfceEmitirEstado', $blade);
        $this->assertStringContainsString('emitirNfceDoBotao', $blade);
        $this->assertStringContainsString("! (\$nfceBtn['enabled'] ?? false)", $blade);
        $this->assertStringContainsString('nfceTransmitindo', $blade);

        if (! preg_match('/Emitir NFC-e.*?<\/button>/s', $blade, $match)) {
            $this->fail('Botão Emitir NFC-e não encontrado na action bar.');
        }

        $this->assertStringNotContainsString('Em breve', $match[0]);
        $this->assertStringNotContainsString('disabled title=', $match[0]);
    }

    public function test_list_page_reusa_regularizacao_em_duas_fases_sem_duplicar_emissao(): void
    {
        $page = file_get_contents(app_path('Filament/Resources/ForcaVendasMonitorResource/Pages/ListForcaVendasMonitor.php'));

        $this->assertIsString($page);
        $this->assertStringContainsString('function emitirNfceDoBotao(): void', $page);
        $this->assertStringContainsString('function transmitirNfceDoBotao(): void', $page);
        $this->assertStringContainsString('function aposTransmitirNfceMonitor(): void', $page);
        $this->assertStringContainsString('NfceRegularizacaoService', $page);
        $this->assertStringContainsString('motivosBloqueioConfiguracao', $page);
        $this->assertStringContainsString('->emitir($vendaId, $empresa, null)', $page);
        $this->assertStringContainsString("ErpAccess::authorizeOrNotify(Auth::user(), 'nfce.access')", $page);
        $this->assertStringContainsString('vendaIdsPermitidosDoMonitor', $page);
        $this->assertStringContainsString("dispatch('erp-sefaz-resultado'", $page);
        $this->assertStringContainsString('function confirmImprimirNfceMonitor(bool $imprimir): void', $page);
        $this->assertStringContainsString('PdvNfceCupomPrinter::livewireOpenJs', $page);
        $this->assertStringContainsString('nfceImprimirConfirmOpen', $page);
        $this->assertStringContainsString('nfceTransmitindo', $page);
        $this->assertStringContainsString('nfceTransmitirVendaId', $page);
        // Fase 1 trava o poll antes da SEFAZ; fase 2 transmite; refresh da grade é adiado.
        $this->assertStringContainsString('$wire.transmitirNfceDoBotao();', $page);
        $this->assertStringContainsString('$wire.aposTransmitirNfceMonitor();', $page);
        $this->assertStringContainsString('atualizarTabelaMantendoPagina()', $page);
        // Pós-autorização: limpa seleção na grade (sem F5); falha não chama aposTransmitir.
        $this->assertStringContainsString("STATUS_AUTORIZADA", $page);
        $aposPos = strpos($page, 'function aposTransmitirNfceMonitor(): void');
        $this->assertNotFalse($aposPos);
        $aposSnippet = substr($page, $aposPos, 1200);
        $this->assertStringContainsString('$this->selecionados = [];', $aposSnippet);
        $this->assertStringContainsString('$this->highlightedRecordId = null;', $aposSnippet);
        $this->assertStringContainsString('erp-row-selected', $aposSnippet);
    }

    public function test_monitor_reusa_indicador_sefaz_e_pergunta_impressao_do_pdv(): void
    {
        $overlay = file_get_contents(resource_path(
            'views/filament/components/erp/forca-vendas/monitor-nfce-fiscal-overlays.blade.php'
        ));
        $page = file_get_contents(app_path(
            'Filament/Resources/ForcaVendasMonitorResource/Pages/ListForcaVendasMonitor.php'
        ));
        $assets = file_get_contents(app_path('Support/Erp/ErpPageAssets.php'));
        $screen = file_get_contents(resource_path(
            'views/filament/components/erp/forca-vendas/monitor-screen.blade.php'
        ));
        $sefazJs = file_get_contents(public_path('js/erp-sefaz-progress.js'));

        $this->assertIsString($overlay);
        $this->assertStringContainsString('pdvui::fiscal-progress', $overlay);
        $this->assertStringContainsString("'fiscalProgressOperacao' => 'transmitirNfceDoBotao'", $overlay);
        $this->assertStringContainsString('js/erp-sefaz-progress.js', $overlay);
        $this->assertStringContainsString('Deseja imprimir a NFC-e?', $overlay);
        $this->assertStringContainsString('confirmImprimirNfceMonitor(true)', $overlay);
        $this->assertStringContainsString('confirmImprimirNfceMonitor(false)', $overlay);

        $this->assertIsString($page);
        $this->assertStringContainsString('monitor-nfce-fiscal-overlays', $page);

        $this->assertIsString($assets);
        $this->assertStringContainsString("'forca-vendas-monitor'", $assets);
        $this->assertMatchesRegularExpression(
            "/'forca-vendas-monitor'\\s*=>\\s*\\[[^\\]]*erp-sefaz-progress\\.css/s",
            $assets
        );

        $this->assertIsString($screen);
        $this->assertStringContainsString('__erpSefazProgressAtivo', $screen);
        $this->assertStringContainsString('[data-erp-sefaz-progress].is-visible', $screen);

        $this->assertIsString($sefazJs);
        $this->assertStringContainsString('window.__erpSefazProgressAtivo', $sefazJs);
    }

    public function test_selecao_ui_expoe_estado_com_validacao_fiscal_existente(): void
    {
        $trait = file_get_contents(app_path(
            'Filament/Resources/ForcaVendasMonitorResource/Pages/Concerns/ProvidesForcaVendasMonitorSelecaoUi.php'
        ));

        $this->assertIsString($trait);
        $this->assertStringContainsString('function nfceEmitirEstado(): array', $trait);
        $this->assertStringContainsString('NfceRegularizacaoService', $trait);
        $this->assertStringContainsString("ErpAccess::currentCan('nfce.access')", $trait);
        $this->assertStringContainsString('SITUACAO_FATURADO', $trait);
        $this->assertStringContainsString('apenas um pedido para emitir NFC-e', $trait);
        $this->assertStringContainsString('->validar(', $trait);
    }

    public function test_detalhe_panel_invalida_cache_do_estado_nfce_e_recebe_trava(): void
    {
        $panel = file_get_contents(app_path('Livewire/Erp/ForcaVendasMonitorDetalhePanel.php'));

        $this->assertIsString($panel);
        $this->assertStringContainsString('$this->nfceEmitirEstado', $panel);
        $this->assertStringContainsString('bool $nfceTransmitindo = false', $panel);
        $this->assertStringContainsString('$this->nfceTransmitindo = $nfceTransmitindo', $panel);
    }

    public function test_regularizacao_service_permanece_somente_fiscal(): void
    {
        $src = file_get_contents((new \ReflectionClass(NfceRegularizacaoService::class))->getFileName());

        $this->assertIsString($src);
        $this->assertStringContainsString('não toca estoque, caixa, contas a receber', $src);
        $this->assertStringContainsString('ORIGEM_REGULARIZACAO_FISCAL', $src);
        $this->assertStringContainsString('Venda já possui NFC-e/NF-e autorizada', $src);
    }
}
