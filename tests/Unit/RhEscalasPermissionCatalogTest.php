<?php

namespace Tests\Unit;

use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class RhEscalasPermissionCatalogTest extends TestCase
{
    public function test_modulo_escalas_no_catalogo(): void
    {
        $modules = ErpPermissionCatalog::modules();

        $this->assertArrayHasKey('rh.escalas', $modules);
        $this->assertSame('Escalas', $modules['rh.escalas']['label']);

        $keys = ErpPermissionCatalog::allKeys();
        foreach (['access', 'create', 'update', 'delete', 'print'] as $action) {
            $this->assertContains(ErpPermissionCatalog::key('rh.escalas', $action), $keys);
        }
    }
}
