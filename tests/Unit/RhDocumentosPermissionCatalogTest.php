<?php

namespace Tests\Unit;

use App\Support\Erp\EmpresaModulos;
use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class RhDocumentosPermissionCatalogTest extends TestCase
{
    public function test_permissoes_crud_documentos_existem(): void
    {
        $keys = ErpPermissionCatalog::allKeys();

        foreach (['access', 'create', 'update', 'delete', 'print'] as $action) {
            $this->assertContains('rh.documentos.'.$action, $keys);
        }
    }

    public function test_modulo_documentos_ligado_ao_param_rh(): void
    {
        $map = EmpresaModulos::permissionModuleMap();

        $this->assertSame('param_modulo_rh', $map['rh.documentos'] ?? null);
    }

    public function test_catalogo_modules_inclui_documentos(): void
    {
        $modules = ErpPermissionCatalog::modules();

        $this->assertArrayHasKey('rh.documentos', $modules);
        $this->assertSame('RH', $modules['rh.documentos']['group']);
        $this->assertArrayHasKey('access', $modules['rh.documentos']['actions']);
        $this->assertArrayHasKey('print', $modules['rh.documentos']['actions']);
    }
}
