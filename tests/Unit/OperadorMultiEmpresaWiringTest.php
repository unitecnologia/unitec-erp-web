<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Operador RH multi-empresa — wiring sem DB.
 */
class OperadorMultiEmpresaWiringTest extends TestCase
{
    public function test_sync_escopa_conflito_por_empresa_e_nao_reusa_vendedor_de_outra(): void
    {
        $src = file_get_contents(app_path('Support/Erp/Rh/OperadorFromFuncionarioSync.php'));

        $this->assertIsString($src);
        $this->assertStringContainsString('nesta empresa', $src);
        $this->assertStringContainsString('usadoEmOutraEmpresa', $src);
        $this->assertStringContainsString('empresasSyncParaFuncionario', $src);
        $this->assertStringContainsString('syncWithoutDetaching', $src);
        $this->assertStringContainsString('apontaOutraEmpresa', $src);
        $this->assertStringNotContainsString(
            'Este usuário já está vinculado a outro operador. Escolha outro usuário ou desative o vínculo anterior.',
            $src,
        );
    }

    public function test_pdv_e_tela_venda_resolvem_operador_pela_empresa_logada(): void
    {
        $pdv = file_get_contents(app_path('Filament/Pages/Concerns/ManagesPdvVendedor.php'));
        $fv = file_get_contents(app_path('Filament/Pages/ForcaVendasTelaVendaPage.php'));
        $resolver = file_get_contents(app_path('Support/Erp/Rh/OperadorDoUsuario.php'));
        $vendedor = file_get_contents(app_path('Models/Vendedor.php'));
        $mig = file_get_contents(database_path(
            'migrations/2026_11_18_160000_add_rh_funcionarios_empresa_user_unique.php'
        ));

        $this->assertIsString($pdv);
        $this->assertStringContainsString('OperadorDoUsuario::vendedorAtivo', $pdv);

        $this->assertIsString($fv);
        $this->assertStringContainsString('OperadorDoUsuario::vendedorAtivo', $fv);

        $this->assertIsString($resolver);
        $this->assertStringContainsString("where('empresa_id', \$empresaId)", $resolver);
        $this->assertStringContainsString("where('user_id', \$user->getKey())", $resolver);
        // Fallback users.vendedor_id exige empresa e pivot empresas.id.
        $this->assertStringContainsString('Fallback users.vendedor_id', $resolver);
        $this->assertStringContainsString('if ($empresaId <= 0)', $resolver);
        $this->assertStringContainsString("empresas()->where('empresas.id', \$empresaId)", $resolver);

        $this->assertIsString($vendedor);
        $this->assertStringContainsString('ErpContext::currentEmpresaId()', $vendedor);
        $this->assertStringContainsString('function rhFuncionarios()', $vendedor);

        $this->assertIsString($mig);
        $this->assertStringContainsString('rh_funcionarios_empresa_user_unique', $mig);
        $this->assertStringContainsString("unique(['empresa_id', 'user_id']", $mig);
    }
}
