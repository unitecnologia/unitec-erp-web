<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\Financeiro\FaturamentoPendenciaFinanceiraGuard;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class EmpresaFinanceiroAguardeTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_parametro_no_catalogo_default_false_grupo_financeiro(): void
    {
        $fields = EmpresaParametros::permissionFields();

        $this->assertArrayHasKey(EmpresaParametros::COLUMN_FINANCEIRO_AGUARDE, $fields);
        $this->assertFalse($fields[EmpresaParametros::COLUMN_FINANCEIRO_AGUARDE]['default']);
        $this->assertSame('Ignorar financeiro', $fields[EmpresaParametros::COLUMN_FINANCEIRO_AGUARDE]['label']);
        $this->assertSame(
            'financeiro',
            EmpresaParametros::permissionGroupForField(EmpresaParametros::COLUMN_FINANCEIRO_AGUARDE),
        );
        $this->assertArrayHasKey('financeiro', EmpresaParametros::permissionGroups());
        $this->assertSame('Financeiro', EmpresaParametros::permissionGroups()['financeiro']);
    }

    public function test_empresa_nova_default_false_e_alterna_por_empresa(): void
    {
        $this->assertTrue(Schema::hasColumn('empresas', EmpresaParametros::COLUMN_FINANCEIRO_AGUARDE));

        $empresaA = Empresa::query()->create([
            'nome' => 'EMP A AGUARDE FIN',
            'ativo' => true,
        ]);
        $empresaB = Empresa::query()->create([
            'nome' => 'EMP B AGUARDE FIN',
            'ativo' => true,
        ]);

        $this->assertFalse(EmpresaParametros::aguardeFinanceiro((int) $empresaA->id));
        $this->assertFalse(EmpresaParametros::aguardeFinanceiro((int) $empresaB->id));
        $this->assertTrue(FaturamentoPendenciaFinanceiraGuard::deveReterPorPendenciaCliente((int) $empresaA->id));
        $this->assertFalse(FaturamentoPendenciaFinanceiraGuard::podeFaturarSemLiberacaoFinanceira((int) $empresaA->id));

        $empresaA->update([EmpresaParametros::COLUMN_FINANCEIRO_AGUARDE => true]);

        $this->assertTrue(EmpresaParametros::aguardeFinanceiro((int) $empresaA->id));
        $this->assertFalse(EmpresaParametros::aguardeFinanceiro((int) $empresaB->id));
        $this->assertFalse(FaturamentoPendenciaFinanceiraGuard::deveReterPorPendenciaCliente((int) $empresaA->id));
        $this->assertTrue(FaturamentoPendenciaFinanceiraGuard::podeFaturarSemLiberacaoFinanceira((int) $empresaA->id));
        $this->assertTrue(FaturamentoPendenciaFinanceiraGuard::deveReterPorPendenciaCliente((int) $empresaB->id));
    }
}
