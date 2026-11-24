<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Flags de Parâmetros no cadastro de Pessoas: UI simplificada, colunas preservadas.
 */
class PessoasFormParametrosFlagsTest extends TestCase
{
    public function test_shell_oculta_atendente_tecnico_fabricante_na_ui(): void
    {
        $shell = (string) file_get_contents(resource_path(
            'views/filament/components/erp/pessoas/form/shell.blade.php'
        ));

        $this->assertStringNotContainsString("'is_atendente'", $shell);
        $this->assertStringNotContainsString("'is_tecnico'", $shell);
        $this->assertStringNotContainsString("'is_fabricante'", $shell);
        $this->assertStringContainsString("'is_funcionario' => 'Funcionários'", $shell);
        $this->assertStringContainsString("'is_cliente' => 'Clientes'", $shell);
    }

    public function test_shell_oculta_funcionario_no_cadastro_rapido_embed(): void
    {
        $shell = (string) file_get_contents(resource_path(
            'views/filament/components/erp/pessoas/form/shell.blade.php'
        ));

        $this->assertStringContainsString('embedsInOrcamento', $shell);
        $this->assertStringContainsString("unset(\$parametros['is_funcionario'])", $shell);
    }

    public function test_model_e_form_filamento_ainda_preservam_flags_ocultas(): void
    {
        $model = (string) file_get_contents(app_path('Models/Person.php'));
        $resource = (string) file_get_contents(app_path('Filament/Resources/PersonResource.php'));
        $create = (string) file_get_contents(app_path(
            'Filament/Resources/PersonResource/Pages/CreatePerson.php'
        ));

        foreach (['is_atendente', 'is_tecnico', 'is_fabricante', 'is_funcionario'] as $flag) {
            $this->assertStringContainsString("'{$flag}'", $model);
            $this->assertStringContainsString("'{$flag}'", $resource);
        }

        $this->assertStringContainsString("'funcionarios' => ['is_funcionario' => true]", $create);
        $this->assertStringContainsString("default => ['is_cliente' => true]", $create);
    }
}
