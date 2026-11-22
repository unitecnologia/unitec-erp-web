<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Troca da data no Monitor: um apply por período, sem perder alteração válida.
 */
class MonitorPeriodoAutoApplyOnceTest extends TestCase
{
    public function test_datepicker_nao_marca_fingerprint_antes_do_sucesso_livewire(): void
    {
        $js = file_get_contents(public_path('js/erp-datepicker.js'));
        $this->assertIsString($js);

        $this->assertStringContainsString('periodGroupFingerprint(group)', $js);
        $this->assertStringContainsString('erpDateAppliedFp', $js);
        $this->assertStringContainsString('erpDatePendingFp', $js);
        $this->assertStringContainsString('erpDateApplySeq', $js);

        // Dedup: aplicado OU em voo (mesmo fingerprint).
        $this->assertStringContainsString('erpDatePendingFp === fingerprint', $js);

        // Datas explícitas no call (evita corrida set/call).
        $this->assertStringContainsString("component.call(applyMethod, de ?? '', ate ?? '', Number(seq))", $js);

        // Fingerprint só após Promise resolve / sucesso.
        $this->assertStringContainsString('req.then(() => finish(true))', $js);
        $this->assertStringContainsString('group.dataset.erpDateAppliedFp = fingerprint', $js);
        $this->assertStringContainsString("group.dataset.erpDateApplySeq !== seq", $js);

        // Auto-apply: onChange só pinta a data; Livewire só no onClose (1º clique no dia).
        $this->assertStringContainsString(
            'Auto-apply no onClose: apply no onChange faz o 1º clique no dia falhar',
            $js,
        );
        $this->assertStringContainsString('hasOpenCalendar', $js);
        // 1º clique no dia (allowInput): mousedown preventDefault no painel.
        $this->assertStringContainsString("cal.addEventListener('mousedown'", $js);
        $this->assertStringContainsString('__erpDatepickerOpenCount', $js);
        $this->assertMatchesRegularExpression(
            '/onClose\([\s\S]*?shouldAutoApplyPeriodGroup\(instance\.input\)[\s\S]*?maybeAutoApplyPeriodGroup\(instance\.input\)/',
            $js,
        );
    }

    public function test_monitor_blade_usa_auto_apply_no_periodo(): void
    {
        $blade = file_get_contents(resource_path(
            'views/filament/components/erp/forca-vendas/monitor-screen.blade.php'
        ));
        $this->assertIsString($blade);
        $this->assertStringContainsString('data-erp-date-auto-apply="1"', $blade);
        $this->assertStringContainsString('data-erp-date-apply-method="applyPeriodFilterAuto"', $blade);
        $this->assertStringContainsString('data-wire-field="periodoAte"', $blade);
    }

    public function test_apply_period_filter_auto_aceita_de_ate_seq_e_descarta_seq_antiga(): void
    {
        $page = file_get_contents(app_path(
            'Filament/Resources/ForcaVendasMonitorResource/Pages/ListForcaVendasMonitor.php'
        ));
        $this->assertIsString($page);
        $this->assertStringContainsString(
            'function applyPeriodFilterAuto(?string $de = null, ?string $ate = null, ?int $seq = null): void',
            $page,
        );
        $this->assertStringContainsString('public int $periodoApplySeq = 0;', $page);
        $this->assertStringContainsString('if ($seq < $this->periodoApplySeq)', $page);
        $this->assertStringContainsString('$this->periodoDe = trim($de);', $page);
        $this->assertStringContainsString('$this->periodoAte = trim($ate);', $page);
        $this->assertStringContainsString(
            'if ($this->periodoDeApplied === $de && $this->periodoAteApplied === $ate)',
            $page,
        );
    }

    public function test_demais_telas_com_auto_apply_aceitam_assinatura_com_args(): void
    {
        $files = [
            'Filament/Resources/CargaResource/Pages/ListCargas.php',
            'Filament/Resources/NfceResource/Pages/ListNfces.php',
            'Filament/Resources/OrcamentoResource/Pages/ListOrcamentos.php',
            'Filament/Resources/DevolucaoVendaResource/Pages/ListDevolucoesVenda.php',
            'Filament/Resources/DevolucaoCompraResource/Pages/ListDevolucoesCompra.php',
            'Filament/Resources/OrdemServicoResource/Pages/ListOrdensServico.php',
            'Filament/Resources/CaixaResource/Pages/ListCaixa.php',
        ];

        foreach ($files as $rel) {
            $src = file_get_contents(app_path($rel));
            $this->assertIsString($src, $rel);
            $this->assertStringContainsString(
                'function applyPeriodFilterAuto(?string $de = null, ?string $ate = null, ?int $seq = null): void',
                $src,
                $rel,
            );
        }
    }
}
