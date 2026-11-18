<?php

namespace Tests\Unit;

use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class TefLiquidacaoLotePermissionCatalogTest extends TestCase
{
    public function test_permissoes_lote_existem_no_catalogo(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'create', 'update', 'cancel'] as $action) {
            $this->assertContains('tef_liquidacao_lote.'.$action, $keys);
        }
    }

    public function test_modulo_no_grupo_financeiro(): void
    {
        $modules = ErpPermissionCatalog::modules();

        $this->assertArrayHasKey('tef_liquidacao_lote', $modules);
        $this->assertSame('Financeiro', $modules['tef_liquidacao_lote']['group']);
    }
}
