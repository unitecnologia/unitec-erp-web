<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\Nfe\NfeClienteCadastro;
use App\Support\Erp\Nfe\NfeVendaMercadoriaService;
use Tests\TestCase;

class NfePermitirIniciarConsumidorFinalTest extends TestCase
{
    public function test_parametro_no_catalogo_fiscal_default_false(): void
    {
        $fields = EmpresaParametros::permissionFields();
        $coluna = 'param_fiscal_permitir_iniciar_nfe_consumidor_final';

        $this->assertArrayHasKey($coluna, $fields);
        $this->assertFalse($fields[$coluna]['default']);
        $this->assertSame(
            'Permitir iniciar NF-e com Consumidor Final',
            $fields[$coluna]['label'],
        );
        $this->assertSame('fiscal', EmpresaParametros::permissionGroupForField($coluna));
    }

    public function test_helper_detecta_consumidor_final_e_assert_continua_bloqueando(): void
    {
        $cf = new Person([
            'codigo' => Person::CODIGO_CONSUMIDOR_FINAL,
            'nome_razao' => 'CONSUMIDOR FINAL',
        ]);

        $this->assertTrue(NfeClienteCadastro::ehConsumidorFinalParaBloqueioNfe($cf));
        $this->assertNotSame([], NfeClienteCadastro::camposFaltantesDoCliente($cf));

        $serv = new NfeVendaMercadoriaService();

        try {
            $serv->assertClienteAptoParaNfe($cf);
            $this->fail('Esperava RuntimeException para Consumidor Final.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Consumidor final', $e->getMessage());
        }
    }
}
