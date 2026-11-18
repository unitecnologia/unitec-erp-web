<?php

namespace Tests\Unit;

use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class TefLiquidacaoDestinoPermissionCatalogTest extends TestCase
{
    public function test_permissoes_destino_existem_no_catalogo(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'create', 'update', 'delete'] as $action) {
            $this->assertContains('tef_liquidacao_destino.'.$action, $keys);
        }
    }

    public function test_modulo_no_grupo_financeiro(): void
    {
        $modules = ErpPermissionCatalog::modules();

        $this->assertArrayHasKey('tef_liquidacao_destino', $modules);
        $this->assertSame('Financeiro', $modules['tef_liquidacao_destino']['group']);
        $this->assertArrayHasKey('access', $modules['tef_liquidacao_destino']['actions']);
        $this->assertArrayHasKey('create', $modules['tef_liquidacao_destino']['actions']);
        $this->assertArrayHasKey('update', $modules['tef_liquidacao_destino']['actions']);
        $this->assertArrayHasKey('delete', $modules['tef_liquidacao_destino']['actions']);
    }
}
