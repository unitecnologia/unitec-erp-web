<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaModulos;
use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class RhHoleritesPermissionCatalogTest extends TestCase
{
    public function test_permissoes_holerites_no_catalogo(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'create', 'update', 'delete', 'print'] as $action) {
            $this->assertContains('rh.holerites.'.$action, $keys);
        }
    }

    public function test_modulo_holerites_usa_gate_rh(): void
    {
        $map = EmpresaModulos::permissionModuleMap();
        $this->assertSame('param_modulo_rh', $map['rh.holerites'] ?? null);
    }

    public function test_modulo_holerites_na_ui_de_permissoes(): void
    {
        $modules = ErpPermissionCatalog::modules();
        $this->assertArrayHasKey('rh.holerites', $modules);
        $this->assertSame('RH', $modules['rh.holerites']['group']);
        $this->assertArrayHasKey('print', $modules['rh.holerites']['actions']);
    }
}
