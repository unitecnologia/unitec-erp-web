<?php

namespace Tests\Unit;

use App\Models\RhExame;
use App\Models\RhFuncionario;
use App\Support\Erp\Reports\RhExamesListagemReport;
use Tests\TestCase;

class RhExamesListagemReportTest extends TestCase
{
    public function test_colunas_padrao(): void
    {
        $cols = RhExamesListagemReport::defaultColumns();
        $this->assertSame(
            ['codigo', 'funcionario', 'tipo', 'realizado_em', 'proximo_em', 'situacao'],
            $cols
        );
    }

    public function test_cell_value_formatacao(): void
    {
        $func = new RhFuncionario(['codigo' => '10', 'nome' => 'JOAO']);
        $exame = new RhExame([
            'codigo' => '7',
            'tipo' => RhExame::TIPO_PERIODICO,
            'realizado_em' => '2026-01-15',
            'proximo_em' => '2026-12-31',
            'conclusao' => RhExame::CONCL_APTO,
        ]);
        $exame->setRelation('funcionario', $func);

        $this->assertSame('7', RhExamesListagemReport::cellValue($exame, 'codigo'));
        $this->assertSame('10 JOAO', RhExamesListagemReport::cellValue($exame, 'funcionario'));
        $this->assertSame('Periódico', RhExamesListagemReport::cellValue($exame, 'tipo'));
        $this->assertSame('15/01/2026', RhExamesListagemReport::cellValue($exame, 'realizado_em'));
        $this->assertSame('31/12/2026', RhExamesListagemReport::cellValue($exame, 'proximo_em'));
    }
}
