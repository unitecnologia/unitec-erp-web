<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaModulos;
use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class RhOcorrenciasPermissionCatalogTest extends TestCase
{
    public function test_permissoes_ocorrencias_no_catalogo(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'create', 'update', 'delete', 'print'] as $action) {
            $this->assertContains('rh.ocorrencias.'.$action, $keys);
        }
    }

    public function test_modulo_ocorrencias_usa_gate_rh(): void
    {
        $map = EmpresaModulos::permissionModuleMap();
        $this->assertSame('param_modulo_rh', $map['rh.ocorrencias'] ?? null);
    }

    public function test_modulo_ocorrencias_na_ui_de_permissoes(): void
    {
        $modules = ErpPermissionCatalog::modules();
        $this->assertArrayHasKey('rh.ocorrencias', $modules);
        $this->assertSame('RH', $modules['rh.ocorrencias']['group']);
        $this->assertArrayHasKey('print', $modules['rh.ocorrencias']['actions']);
    }
}
