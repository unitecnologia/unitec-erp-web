<?php

namespace Tests\Unit;

use App\Models\RhBeneficio;
use App\Models\RhBeneficioVinculo;
use App\Models\RhFuncionario;
use App\Support\Erp\Reports\RhBeneficiosListagemReport;
use Carbon\Carbon;
use Tests\TestCase;

class RhBeneficiosListagemReportTest extends TestCase
{
    public function test_colunas_padrao(): void
    {
        $cols = RhBeneficiosListagemReport::defaultColumns();
        $this->assertContains('funcionario', $cols);
        $this->assertContains('valor', $cols);
        $this->assertArrayHasKey('beneficio', RhBeneficiosListagemReport::columnDefinitions());
    }

    public function test_cell_value_e_totalizacao_mensal(): void
    {
        $func = new RhFuncionario(['codigo' => '10', 'nome' => 'MARIA']);
        $func->setAttribute('id', 1);

        $beneficio = new RhBeneficio([
            'codigo' => '1',
            'descricao' => 'VR ALIMENTACAO',
            'tipo' => RhBeneficio::TIPO_ALIMENTACAO,
        ]);
        $beneficio->setAttribute('id', 2);

        $vinculo = new RhBeneficioVinculo([
            'codigo' => '7',
            'valor' => 550.00,
            'participacao_funcionario' => 50.00,
            'situacao' => RhBeneficioVinculo::SIT_ATIVO,
            'ativo' => true,
        ]);
        $vinculo->setRelation('funcionario', $func);
        $vinculo->setRelation('beneficio', $beneficio);
        $vinculo->inicio_em = Carbon::parse('2026-01-01');
        $vinculo->termino_em = null;

        $this->assertSame('10 MARIA', RhBeneficiosListagemReport::cellValue($vinculo, 'funcionario'));
        $this->assertSame('VR ALIMENTACAO', RhBeneficiosListagemReport::cellValue($vinculo, 'beneficio'));
        $this->assertSame('Alimentação', RhBeneficiosListagemReport::cellValue($vinculo, 'tipo'));
        $this->assertSame('550,00', RhBeneficiosListagemReport::cellValue($vinculo, 'valor'));
        $this->assertSame('50,00', RhBeneficiosListagemReport::cellValue($vinculo, 'participacao'));

        $total = RhBeneficiosListagemReport::monthlyAdministrativeTotal(collect([$vinculo]), Carbon::parse('2026-01-15'));
        $this->assertSame(550.0, $total);
    }
}
