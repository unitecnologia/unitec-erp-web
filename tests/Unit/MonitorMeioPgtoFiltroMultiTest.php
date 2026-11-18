<?php

namespace Tests\Unit;

use App\Filament\Resources\ForcaVendasMonitorResource\Pages\ListForcaVendasMonitor;
use Tests\TestCase;

/**
 * Filtro Meio Pgto multi (checkboxes) no Monitor — sem DB.
 */
class MonitorMeioPgtoFiltroMultiTest extends TestCase
{
    public function test_parse_e_encode_preservam_multi_e_todos_vazio(): void
    {
        $this->assertSame([], ListForcaVendasMonitor::parseFiltroMeioPgtoValor(''));
        $this->assertSame([], ListForcaVendasMonitor::parseFiltroMeioPgtoValor('todos'));
        $this->assertSame(['PIX'], ListForcaVendasMonitor::parseFiltroMeioPgtoValor('PIX'));
        $this->assertSame(
            ['PIX', 'DINHEIRO'],
            ListForcaVendasMonitor::parseFiltroMeioPgtoValor('PIX|DINHEIRO'),
        );
        $this->assertSame(
            ['PIX', 'DINHEIRO'],
            ListForcaVendasMonitor::parseFiltroMeioPgtoValor('PIX|DINHEIRO|PIX'),
        );

        $this->assertSame('', ListForcaVendasMonitor::encodeFiltroMeioPgtoValor([]));
        $this->assertSame(
            'PIX|DINHEIRO',
            ListForcaVendasMonitor::encodeFiltroMeioPgtoValor(['PIX', 'DINHEIRO', 'PIX', '']),
        );
    }

    public function test_variantes_busca_cobrem_acento_e_ascii(): void
    {
        $vars = ListForcaVendasMonitor::variantesBuscaMeioPagamento('POS Crédito');

        $this->assertContains('pos crédito', $vars);
        $this->assertContains('pos credito', $vars);
    }

    public function test_rotulo_e_chave_normalizam_sem_duplicar_conceito(): void
    {
        $this->assertSame('PIX', ListForcaVendasMonitor::rotuloMeioPagamento('pix'));
        $this->assertSame('Dinheiro', ListForcaVendasMonitor::rotuloMeioPagamento('DINHEIRO'));
        $this->assertSame('POS CREDITO', ListForcaVendasMonitor::normalizarChaveMeioPagamento('POS Crédito'));
        $this->assertSame(
            ListForcaVendasMonitor::normalizarChaveMeioPagamento('POS Crédito'),
            ListForcaVendasMonitor::normalizarChaveMeioPagamento('pos credito'),
        );
    }

    public function test_wiring_monitor_usa_multi_checkboxes_e_filtro_or(): void
    {
        $page = file_get_contents(app_path(
            'Filament/Resources/ForcaVendasMonitorResource/Pages/ListForcaVendasMonitor.php'
        ));
        $blade = file_get_contents(resource_path(
            'views/filament/components/erp/forca-vendas/monitor-screen.blade.php'
        ));
        $css = file_get_contents(public_path('css/erp-fv-monitor.css'));

        $this->assertIsString($page);
        $this->assertStringContainsString("'meio_pgto' => 'multi'", $page);
        $this->assertStringContainsString('function filtroMeioPgtoCombo(): array', $page);
        $this->assertStringContainsString('function aplicarFiltroMeioPgto(', $page);
        $this->assertStringContainsString('parseFiltroMeioPgtoValor', $page);
        // Sem DISTINCT pesado no payload da grade.
        $this->assertStringNotContainsString(
            "DISTINCT JSON_UNQUOTE(JSON_EXTRACT(payload, '$.forma_pagamento'))",
            $page
        );

        $this->assertIsString($blade);
        $this->assertStringContainsString("filtroCampo === 'meio_pgto'", $blade);
        $this->assertStringContainsString('erp-fv-mon__multi-check', $blade);
        $this->assertStringContainsString('erp-fv-mon__multi-count', $blade);
        $this->assertStringContainsString("selecionados.join('|')", $blade);

        $this->assertIsString($css);
        $this->assertStringContainsString('.erp-fv-mon__multi-count', $css);
        $this->assertStringContainsString('.erp-fv-mon__multi-option', $css);
    }
}
