<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaModulos;
use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class RhEpisPermissionCatalogTest extends TestCase
{
    public function test_permissoes_epis_no_catalogo(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'create', 'update', 'delete', 'print'] as $action) {
            $this->assertContains('rh.epis.'.$action, $keys);
        }
    }

    public function test_modulo_epis_usa_gate_rh(): void
    {
        $map = EmpresaModulos::permissionModuleMap();
        $this->assertSame('param_modulo_rh', $map['rh.epis'] ?? null);
    }

    public function test_modulo_epis_na_ui_de_permissoes(): void
    {
        $modules = ErpPermissionCatalog::modules();
        $this->assertArrayHasKey('rh.epis', $modules);
        $this->assertSame('RH', $modules['rh.epis']['group']);
        $this->assertArrayHasKey('access', $modules['rh.epis']['actions']);
        $this->assertArrayHasKey('print', $modules['rh.epis']['actions']);
    }
}
