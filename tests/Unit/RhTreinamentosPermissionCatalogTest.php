<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaModulos;
use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class RhTreinamentosPermissionCatalogTest extends TestCase
{
    public function test_permissoes_treinamentos_no_catalogo(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'create', 'update', 'delete', 'print'] as $action) {
            $this->assertContains('rh.treinamentos.'.$action, $keys);
        }
    }

    public function test_modulo_treinamentos_usa_gate_rh(): void
    {
        $map = EmpresaModulos::permissionModuleMap();
        $this->assertSame('param_modulo_rh', $map['rh.treinamentos'] ?? null);
    }

    public function test_modulo_treinamentos_na_ui_de_permissoes(): void
    {
        $modules = ErpPermissionCatalog::modules();
        $this->assertArrayHasKey('rh.treinamentos', $modules);
        $this->assertSame('RH', $modules['rh.treinamentos']['group']);
        $this->assertArrayHasKey('access', $modules['rh.treinamentos']['actions']);
        $this->assertArrayHasKey('print', $modules['rh.treinamentos']['actions']);
    }
}
