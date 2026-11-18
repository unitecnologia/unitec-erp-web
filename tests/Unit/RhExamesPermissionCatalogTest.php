<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaModulos;
use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class RhExamesPermissionCatalogTest extends TestCase
{
    public function test_permissoes_exames_no_catalogo(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'create', 'update', 'delete', 'print'] as $action) {
            $this->assertContains('rh.exames.'.$action, $keys);
        }
    }

    public function test_modulo_exames_usa_gate_rh(): void
    {
        $map = EmpresaModulos::permissionModuleMap();
        $this->assertSame('param_modulo_rh', $map['rh.exames'] ?? null);
    }

    public function test_modulo_exames_na_ui_de_permissoes(): void
    {
        $modules = ErpPermissionCatalog::modules();
        $this->assertArrayHasKey('rh.exames', $modules);
        $this->assertSame('RH', $modules['rh.exames']['group']);
        $this->assertArrayHasKey('print', $modules['rh.exames']['actions']);
    }
}
