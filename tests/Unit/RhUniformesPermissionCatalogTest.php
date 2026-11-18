<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaModulos;
use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class RhUniformesPermissionCatalogTest extends TestCase
{
    public function test_permissoes_uniformes_no_catalogo(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'create', 'update', 'delete', 'print'] as $action) {
            $this->assertContains('rh.uniformes.'.$action, $keys);
        }
    }

    public function test_modulo_uniformes_usa_gate_rh(): void
    {
        $map = EmpresaModulos::permissionModuleMap();
        $this->assertSame('param_modulo_rh', $map['rh.uniformes'] ?? null);
    }

    public function test_modulo_uniformes_na_ui_de_permissoes(): void
    {
        $modules = ErpPermissionCatalog::modules();
        $this->assertArrayHasKey('rh.uniformes', $modules);
        $this->assertSame('RH', $modules['rh.uniformes']['group']);
        $this->assertArrayHasKey('access', $modules['rh.uniformes']['actions']);
        $this->assertArrayHasKey('print', $modules['rh.uniformes']['actions']);
    }
}
