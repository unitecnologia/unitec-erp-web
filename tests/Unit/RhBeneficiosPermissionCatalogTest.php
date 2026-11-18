<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaModulos;
use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class RhBeneficiosPermissionCatalogTest extends TestCase
{
    public function test_permissoes_beneficios_no_catalogo(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'create', 'update', 'delete', 'print'] as $action) {
            $this->assertContains('rh.beneficios.'.$action, $keys);
        }
    }

    public function test_modulo_beneficios_usa_gate_rh(): void
    {
        $map = EmpresaModulos::permissionModuleMap();
        $this->assertSame('param_modulo_rh', $map['rh.beneficios'] ?? null);
    }

    public function test_modulo_beneficios_na_ui_de_permissoes(): void
    {
        $modules = ErpPermissionCatalog::modules();
        $this->assertArrayHasKey('rh.beneficios', $modules);
        $this->assertSame('RH', $modules['rh.beneficios']['group']);
        $this->assertArrayHasKey('access', $modules['rh.beneficios']['actions']);
        $this->assertArrayHasKey('print', $modules['rh.beneficios']['actions']);
    }
}
