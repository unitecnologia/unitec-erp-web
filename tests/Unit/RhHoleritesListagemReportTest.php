<?php

namespace Tests\Unit;

use App\Models\RhFuncionario;
use App\Models\RhHolerite;
use App\Support\Erp\Reports\RhHoleritesListagemReport;
use Tests\TestCase;

class RhHoleritesListagemReportTest extends TestCase
{
    public function test_colunas_padrao(): void
    {
        $cols = RhHoleritesListagemReport::defaultColumns();
        $this->assertSame(
            ['codigo', 'funcionario', 'competencia', 'pago_em', 'situacao'],
            $cols
        );
    }

    public function test_cell_value_formatacao(): void
    {
        $func = new RhFuncionario(['codigo' => '10', 'nome' => 'JOAO']);
        $holerite = new RhHolerite([
            'codigo' => '7',
            'competencia_ano' => 2026,
            'competencia_mes' => 10,
            'pago_em' => '2026-11-05',
            'ativo' => true,
        ]);
        $holerite->setRelation('funcionario', $func);

        $this->assertSame('7', RhHoleritesListagemReport::cellValue($holerite, 'codigo'));
        $this->assertSame('10 JOAO', RhHoleritesListagemReport::cellValue($holerite, 'funcionario'));
        $this->assertSame('10/2026', RhHoleritesListagemReport::cellValue($holerite, 'competencia'));
        $this->assertSame('05/11/2026', RhHoleritesListagemReport::cellValue($holerite, 'pago_em'));
        $this->assertSame('Ativo', RhHoleritesListagemReport::cellValue($holerite, 'situacao'));
    }
}
