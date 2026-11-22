<?php

namespace Tests\Unit;

use App\Support\Erp\Nfe\NfeVendaMercadoriaService;
use App\Support\Fiscal\NfeEmissionService;
use App\Support\Fiscal\VendaFiscalLock;
use Tests\TestCase;

/**
 * Monitor: botão Emitir NF-e respeita NFC-e autorizada/contingência (UI + backend).
 * Sem DB / SEFAZ — wiring e contrato de bloqueio.
 */
class MonitorNfeEmitirNfceWiringTest extends TestCase
{
    public function test_action_bar_liga_emitir_nfe_ao_estado(): void
    {
        $blade = file_get_contents(resource_path(
            'views/filament/components/erp/forca-vendas/monitor-action-bar.blade.php'
        ));

        $this->assertIsString($blade);
        $this->assertStringContainsString('$this->nfeEmitirEstado', $blade);
        $this->assertStringContainsString('emitirNfeDoBotao', $blade);
        $this->assertStringContainsString("! (\$nfeBtn['enabled'] ?? false)", $blade);
    }

    public function test_selecao_ui_bloqueia_nfe_via_venda_fiscal_lock_sem_usar_numero_nc(): void
    {
        $trait = file_get_contents(app_path(
            'Filament/Resources/ForcaVendasMonitorResource/Pages/Concerns/ProvidesForcaVendasMonitorSelecaoUi.php'
        ));

        $this->assertIsString($trait);
        $this->assertStringContainsString('function nfeEmitirEstado(): array', $trait);
        $this->assertStringContainsString('VendaFiscalLock::motivoBloqueioNfePorVenda', $trait);
        $this->assertStringContainsString(
            "'venda.pdvVenda.nfce:id,pdv_venda_id,numero,status,simulada'",
            $trait,
        );
        $this->assertStringContainsString('Status fiscal real da NFC-e', $trait);

        $pos = strpos($trait, 'public function nfeEmitirEstado(): array');
        $this->assertNotFalse($pos);
        $end = strpos($trait, 'public function nfeAbrirEstado(): array', $pos);
        $snippet = $end !== false
            ? substr($trait, $pos, $end - $pos)
            : substr($trait, $pos, 8000);

        // Bloqueio por status eager-loaded ocorre antes de validar cadastro/itens.
        $posNfce = strpos($snippet, 'motivoBloqueioNfePorVenda');
        $posFaltando = strpos($snippet, 'camposFaltantesDoCliente');
        $this->assertNotFalse($posNfce);
        $this->assertNotFalse($posFaltando);
        $this->assertLessThan($posFaltando, $posNfce);

        // Qualquer motivo (incl. NFC-e) desliga o botão — inclusive em seleção múltipla.
        $this->assertStringContainsString('if ($motivos !== [])', $snippet);
        $this->assertStringContainsString("'venda_ids' => []", $snippet);
    }

    public function test_detalhe_panel_invalida_nfe_emitir_ao_trocar_selecao(): void
    {
        $panel = file_get_contents(app_path('Livewire/Erp/ForcaVendasMonitorDetalhePanel.php'));

        $this->assertIsString($panel);
        $this->assertStringContainsString("#[On('fv-monitor-selecao')]", $panel);
        $this->assertStringContainsString('$this->nfeEmitirEstado', $panel);
        $this->assertStringContainsString('$this->nfceEmitirEstado', $panel);
    }

    public function test_list_page_skip_render_na_selecao_e_invalida_estado_apos_nfce(): void
    {
        $page = file_get_contents(app_path(
            'Filament/Resources/ForcaVendasMonitorResource/Pages/ListForcaVendasMonitor.php'
        ));

        $this->assertIsString($page);

        $pos = strpos($page, 'public function alternarSelecionado');
        $this->assertNotFalse($pos);
        $snippet = substr($page, $pos, 900);
        $this->assertStringContainsString('$this->skipRender()', $snippet);
        $this->assertStringContainsString('pushSelecaoToDetalhePanel', $snippet);

        $this->assertStringContainsString('unset($this->nfceEmitirEstado, $this->nfeEmitirEstado, $this->nfeAbrirEstado)', $page);
    }

    public function test_grade_ja_eager_load_nfce_com_status_e_simulada(): void
    {
        $page = file_get_contents(app_path(
            'Filament/Resources/ForcaVendasMonitorResource/Pages/ListForcaVendasMonitor.php'
        ));

        $this->assertIsString($page);
        $pos = strpos($page, 'protected function getTableQuery(): Builder');
        $this->assertNotFalse($pos);
        $snippet = substr($page, $pos, 1200);

        $this->assertStringContainsString("'pdvVenda'", $snippet);
        $this->assertStringContainsString("'nfce'", $snippet);
        $this->assertStringContainsString('status', $snippet);
        $this->assertStringContainsString('simulada', $snippet);
    }

    public function test_backend_validar_e_transmissao_usam_mesmo_bloqueio(): void
    {
        $serv = file_get_contents((new \ReflectionClass(NfeVendaMercadoriaService::class))->getFileName());
        $emissao = file_get_contents((new \ReflectionClass(NfeEmissionService::class))->getFileName());

        $this->assertIsString($serv);
        $this->assertStringContainsString('loadMissing([\'pdvVenda.nfce\'])', $serv);
        $this->assertStringContainsString('VendaFiscalLock::motivoBloqueioNfePorVenda', $serv);

        $this->assertIsString($emissao);
        $this->assertStringContainsString('VendaFiscalLock::vendaTemNfceValida', $emissao);
        $this->assertStringContainsString('VendaFiscalLock::MENSAGEM_NFCE_BLOQUEIA_NFE', $emissao);

        $this->assertSame(
            'A venda vinculada já possui NFC-e autorizada/contingência. Cancele a NFC-e antes de emitir NF-e.',
            VendaFiscalLock::MENSAGEM_NFCE_BLOQUEIA_NFE,
        );
    }
}
