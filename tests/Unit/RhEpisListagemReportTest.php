<?php

namespace Tests\Unit;

use App\Models\RhEpi;
use App\Models\RhEpiEntrega;
use App\Models\RhFuncionario;
use App\Support\Erp\Reports\RhEpisListagemReport;
use Tests\TestCase;

class RhEpisListagemReportTest extends TestCase
{
    public function test_colunas_padrao(): void
    {
        $cols = RhEpisListagemReport::defaultColumns();

        $this->assertSame(
            ['codigo', 'funcionario', 'epi', 'ca', 'entregue_em', 'previsao_troca', 'situacao'],
            $cols
        );
        $this->assertArrayHasKey('epi', RhEpisListagemReport::columnDefinitions());
    }

    public function test_cell_value_formatacao(): void
    {
        $func = new RhFuncionario(['codigo' => '10', 'nome' => 'JOAO']);
        $epi = new RhEpi(['descricao' => 'LUVA', 'ca_numero' => '999']);
        $entrega = new RhEpiEntrega([
            'codigo' => '7',
            'entregue_em' => '2026-01-15',
            'previsao_troca' => '2026-02-15',
            'situacao' => RhEpiEntrega::SIT_ENTREGUE,
        ]);
        $entrega->setRelation('funcionario', $func);
        $entrega->setRelation('epi', $epi);

        $this->assertSame('7', RhEpisListagemReport::cellValue($entrega, 'codigo'));
        $this->assertSame('10 JOAO', RhEpisListagemReport::cellValue($entrega, 'funcionario'));
        $this->assertSame('LUVA', RhEpisListagemReport::cellValue($entrega, 'epi'));
        $this->assertSame('999', RhEpisListagemReport::cellValue($entrega, 'ca'));
        $this->assertSame('15/01/2026', RhEpisListagemReport::cellValue($entrega, 'entregue_em'));
        $this->assertSame('15/02/2026', RhEpisListagemReport::cellValue($entrega, 'previsao_troca'));
        $this->assertSame('Entregue', RhEpisListagemReport::cellValue($entrega, 'situacao'));
    }
}
