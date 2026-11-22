<?php

namespace Tests\Unit;

use App\Filament\Resources\ForcaVendasMonitorResource\Pages\Concerns\ProvidesForcaVendasMonitorSelecaoUi;
use Tests\TestCase;

/**
 * Regressão: desmarcar todas as flags não pode deixar "Tela de Venda" desabilitado
 * só porque o destaque (highlightedRecordId) permanece no detalhe.
 *
 * Seleção continua em skipRender + evento fv-monitor-selecao (sem remorph da grade).
 */
class MonitorTelaVendaBotaoSelecaoTest extends TestCase
{
    public function test_sem_flags_estado_habilitado_mesmo_com_destaque(): void
    {
        $ui = $this->novoEstadoUi([], 4242);
        $estado = $ui->telaVendaEstado();

        $this->assertTrue($estado['enabled']);
        $this->assertStringContainsString('em branco', $estado['title']);
    }

    public function test_multiplas_flags_estado_desabilitado(): void
    {
        $ui = $this->novoEstadoUi(['10', '20'], 10);
        $estado = $ui->telaVendaEstado();

        $this->assertFalse($estado['enabled']);
        $this->assertStringContainsString('apenas um pedido', mb_strtolower($estado['title']));
    }

    public function test_destaque_sem_flag_nao_habilita_acoes_do_pedido(): void
    {
        $ui = $this->novoEstadoUi([], 4242);

        $this->assertFalse($ui->enviarEstado()['enabled']);
        $this->assertFalse($ui->imprimirEstado()['enabled']);
        $this->assertFalse($ui->cancelarEstado()['enabled']);
        $this->assertFalse($ui->reabrirEstado()['enabled']);
        $this->assertFalse($ui->faturarEstado()['enabled']);
        $this->assertTrue($ui->telaVendaEstado()['enabled']);
    }

    public function test_acao_tela_venda_ignora_destaque_quando_nao_ha_flag(): void
    {
        $page = file_get_contents(app_path(
            'Filament/Resources/ForcaVendasMonitorResource/Pages/ListForcaVendasMonitor.php'
        ));

        $this->assertIsString($page);

        $pos = strpos($page, 'public function telaVenda(): void');
        $this->assertNotFalse($pos);
        $snippet = substr($page, $pos, 1200);

        $this->assertStringContainsString('if ($this->selecionados === [])', $snippet);
        $this->assertStringContainsString('ForcaVendasTelaVendaPage::getUrl()', $snippet);
        $this->assertStringNotContainsString(
            'if (! $this->highlightedRecordId && $this->selecionados === [])',
            $snippet
        );
        $this->assertStringContainsString('$this->selecionados[0]', $snippet);
        $this->assertStringNotContainsString(
            '$this->highlightedRecordId'."\n".'            ?: (int) ($this->selecionados',
            $snippet
        );
    }

    public function test_estado_ui_nao_usa_destaque_sem_flag(): void
    {
        $trait = file_get_contents(app_path(
            'Filament/Resources/ForcaVendasMonitorResource/Pages/Concerns/ProvidesForcaVendasMonitorSelecaoUi.php'
        ));

        $this->assertIsString($trait);

        $pos = strpos($trait, 'public function telaVendaEstado(): array');
        $this->assertNotFalse($pos);
        $snippet = substr($trait, $pos, 1600);

        $this->assertStringContainsString('$nMarcados === 1', $snippet);
        $this->assertStringNotContainsString(
            'count($this->selecionados) === 1 || $this->highlightedRecordId',
            $snippet
        );
        $this->assertStringContainsString('SITUACAO_FATURADO', $snippet);
        $this->assertStringContainsString('em branco', $snippet);
        $this->assertStringContainsString('Usa só as flags (selecionados)', $trait);
    }

    public function test_selecao_continua_com_skip_render_e_evento_do_painel(): void
    {
        $page = file_get_contents(app_path(
            'Filament/Resources/ForcaVendasMonitorResource/Pages/ListForcaVendasMonitor.php'
        ));
        $blade = file_get_contents(resource_path(
            'views/filament/components/erp/forca-vendas/monitor-action-bar.blade.php'
        ));
        $panel = file_get_contents(app_path('Livewire/Erp/ForcaVendasMonitorDetalhePanel.php'));

        $this->assertIsString($page);
        $this->assertIsString($blade);
        $this->assertIsString($panel);

        $pos = strpos($page, 'public function alternarSelecionado');
        $this->assertNotFalse($pos);
        $snippet = substr($page, $pos, 900);

        $this->assertStringContainsString('pushSelecaoToDetalhePanel()', $snippet);
        $this->assertStringContainsString('skipRender()', $snippet);
        $this->assertStringContainsString('Mantém highlightedRecordId', $snippet);
        $this->assertStringContainsString("'fv-monitor-selecao'", $page);

        $this->assertStringContainsString('$this->telaVendaEstado', $panel);
        $this->assertStringContainsString('$telaVendaBtn = $this->telaVendaEstado', $blade);
        $this->assertStringContainsString('wire:click="{{ $actionWire }}telaVenda"', $blade);
        $this->assertStringContainsString("@disabled(! (\$telaVendaBtn['enabled'] ?? false))", $blade);
        $this->assertStringContainsString('$temMarcados = count($this->selecionados) > 0', $blade);
        $this->assertStringContainsString('@if ($temMarcados)', $blade);

        $trait = file_get_contents(app_path(
            'Filament/Resources/ForcaVendasMonitorResource/Pages/Concerns/ProvidesForcaVendasMonitorSelecaoUi.php'
        ));
        $this->assertIsString($trait);
        $this->assertStringNotContainsString(
            'if ($ids->isEmpty() && $this->highlightedRecordId)',
            $trait
        );
        $this->assertStringContainsString('não a barra', $trait);
    }

    /**
     * Harness leve do trait (sem Livewire/DB): caminhos sem consulta a situação.
     *
     * @param  list<string>  $selecionados
     */
    private function novoEstadoUi(array $selecionados, ?int $highlightedRecordId): object
    {
        return new class($selecionados, $highlightedRecordId)
        {
            use ProvidesForcaVendasMonitorSelecaoUi;

            /** @var list<string> */
            public array $selecionados;

            public ?int $highlightedRecordId;

            public bool $clonarProgressOpen = false;

            /**
             * @param  list<string>  $selecionados
             */
            public function __construct(array $selecionados, ?int $highlightedRecordId)
            {
                $this->selecionados = $selecionados;
                $this->highlightedRecordId = $highlightedRecordId;
            }
        };
    }
}
