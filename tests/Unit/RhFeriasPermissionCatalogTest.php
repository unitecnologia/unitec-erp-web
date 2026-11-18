<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaModulos;
use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class RhFeriasPermissionCatalogTest extends TestCase
{
    public function test_modulo_ferias_usa_gate_rh(): void
    {
        $map = EmpresaModulos::permissionModuleMap();
        $this->assertSame('param_modulo_rh', $map['rh.ferias'] ?? null);
    }

    public function test_modulo_ferias_na_ui_de_permissoes(): void
    {
        $modules = ErpPermissionCatalog::modules();
        $this->assertArrayHasKey('rh.ferias', $modules);
        $this->assertSame('RH', $modules['rh.ferias']['group']);
        $this->assertArrayHasKey('print', $modules['rh.ferias']['actions']);
    }
}
