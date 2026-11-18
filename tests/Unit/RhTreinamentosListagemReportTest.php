<?php

namespace Tests\Unit;

use App\Models\RhFuncionario;
use App\Models\RhTreinamento;
use App\Models\RhTreinamentoParticipante;
use App\Models\RhTreinamentoRealizacao;
use App\Support\Erp\Reports\RhTreinamentosListagemReport;
use Tests\TestCase;

class RhTreinamentosListagemReportTest extends TestCase
{
    public function test_colunas_padrao(): void
    {
        $cols = RhTreinamentosListagemReport::defaultColumns();

        $this->assertSame(
            ['codigo', 'funcionario', 'treinamento', 'realizado_em', 'carga_horaria', 'vencimento_em', 'situacao'],
            $cols
        );
        $this->assertArrayHasKey('treinamento', RhTreinamentosListagemReport::columnDefinitions());
    }

    public function test_cell_value_formatacao(): void
    {
        $func = new RhFuncionario(['codigo' => '10', 'nome' => 'JOAO']);
        $trein = new RhTreinamento(['nome' => 'NR-12', 'carga_horaria' => 8.5]);
        $realizacao = new RhTreinamentoRealizacao(['realizado_em' => '2026-01-15']);
        $realizacao->setRelation('treinamento', $trein);

        $participante = new RhTreinamentoParticipante([
            'codigo' => '7',
            'vencimento_em' => '2026-02-15',
            'situacao' => RhTreinamentoParticipante::SIT_ATIVO,
        ]);
        $participante->setRelation('funcionario', $func);
        $participante->setRelation('realizacao', $realizacao);

        $this->assertSame('7', RhTreinamentosListagemReport::cellValue($participante, 'codigo'));
        $this->assertSame('10 JOAO', RhTreinamentosListagemReport::cellValue($participante, 'funcionario'));
        $this->assertSame('NR-12', RhTreinamentosListagemReport::cellValue($participante, 'treinamento'));
        $this->assertSame('15/01/2026', RhTreinamentosListagemReport::cellValue($participante, 'realizado_em'));
        $this->assertSame('8,50', RhTreinamentosListagemReport::cellValue($participante, 'carga_horaria'));
        $this->assertSame('15/02/2026', RhTreinamentosListagemReport::cellValue($participante, 'vencimento_em'));
        $this->assertSame('Ativo', RhTreinamentosListagemReport::cellValue($participante, 'situacao'));
    }
}
