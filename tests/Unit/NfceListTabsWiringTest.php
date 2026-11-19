<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Wiring da troca de abas da listagem NFC-e — sem remount do Filament pai.
 */
class NfceListTabsWiringTest extends TestCase
{
    public function test_grade_troca_aba_sem_chamar_remount_do_pai(): void
    {
        $child = file_get_contents(app_path('Livewire/Erp/NfceListTable.php'));
        $tabs = file_get_contents(resource_path('views/filament/components/erp/nfce/tabs.blade.php'));
        $js = file_get_contents(public_path('js/erp-nfe-tab-counts.js'));
        $screen = file_get_contents(resource_path('views/filament/components/erp/nfce/screen.blade.php'));
        $parent = file_get_contents(app_path('Filament/Resources/NfceResource/Pages/ListNfces.php'));

        $this->assertIsString($child);
        $this->assertIsString($tabs);
        $this->assertIsString($js);
        $this->assertIsString($screen);
        $this->assertIsString($parent);

        // Caminho das abas: só a grade; sem parent.call que remountava e orphanava o Livewire.
        $this->assertStringContainsString("erp.nfce-list-table", $tabs);
        $this->assertStringContainsString("setStatusFilter", $tabs);
        $this->assertDoesNotMatchRegularExpression(
            '/parent\.call\s*\(\s*[\'"]onNfceListStatusChanged/',
            $child,
        );
        $this->assertStringContainsString('ErpNfceAvisoRodape', $child);
        $this->assertStringContainsString('ErpNfceAvisoRodape', $js);
        $this->assertStringContainsString("erp-nfe-tab-counts.js') }}?v=3", $screen);

        // Destaque de linha nas abas de aviso também evita remount.
        $highlightPos = strpos($parent, 'function highlightRecord(int | string $recordId): void');
        $this->assertNotFalse($highlightPos);
        $snippet = substr($parent, $highlightPos, 900);
        $this->assertStringContainsString('ErpNfceAvisoRodape', $snippet);
        $this->assertStringContainsString('$this->skipRender();', $snippet);
    }
}
