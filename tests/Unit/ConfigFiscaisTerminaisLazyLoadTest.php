<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Lazy load da grade de caixas em Config. Fiscais — sem DB / sem SEFAZ.
 */
class ConfigFiscaisTerminaisLazyLoadTest extends TestCase
{
    public function test_mount_nao_chama_load_terminais_e_aba_usa_ensure(): void
    {
        $page = file_get_contents(app_path('Filament/Pages/ConfigFiscaisPage.php'));

        $this->assertIsString($page);

        $mountPos = strpos($page, 'public function mount(): void');
        $this->assertNotFalse($mountPos);
        $mountSnippet = substr($page, $mountPos, 900);

        $this->assertStringNotContainsString('$this->loadTerminais();', $mountSnippet);
        $this->assertStringContainsString('$this->terminaisCarregados = false;', $mountSnippet);

        $this->assertStringContainsString('function ensureTerminaisLoaded(bool $force = false): void', $page);
        $this->assertStringContainsString('function invalidateTerminaisCache(): void', $page);

        $setTabPos = strpos($page, 'public function setActiveTab(string $tab): void');
        $this->assertNotFalse($setTabPos);
        $setTabSnippet = substr($page, $setTabPos, 700);
        $this->assertStringContainsString("\$this->activeTab === 'pdv_offline'", $setTabSnippet);
        $this->assertStringContainsString('$this->ensureTerminaisLoaded();', $setTabSnippet);
        $this->assertStringNotContainsString('$this->loadTerminais();', $setTabSnippet);
    }
}
