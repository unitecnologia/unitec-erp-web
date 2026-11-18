<?php

namespace Tests\Unit;

use App\Models\RhFuncionario;
use App\Models\RhUniforme;
use App\Models\RhUniformeEntrega;
use App\Support\Erp\Reports\RhUniformesListagemReport;
use Tests\TestCase;

class RhUniformesListagemReportTest extends TestCase
{
    public function test_colunas_padrao(): void
    {
        $cols = RhUniformesListagemReport::defaultColumns();

        $this->assertSame(
            ['codigo', 'funcionario', 'uniforme', 'tamanho', 'quantidade', 'entregue_em', 'situacao'],
            $cols
        );
        $this->assertArrayHasKey('uniforme', RhUniformesListagemReport::columnDefinitions());
        $this->assertArrayHasKey('tamanho', RhUniformesListagemReport::columnDefinitions());
    }

    public function test_cell_value_formatacao(): void
    {
        $func = new RhFuncionario(['codigo' => '10', 'nome' => 'JOAO']);
        $uniforme = new RhUniforme(['descricao' => 'CAMISA POLO', 'tamanho' => 'M']);
        $entrega = new RhUniformeEntrega([
            'codigo' => '7',
            'quantidade' => 2,
            'entregue_em' => '2026-01-15',
            'situacao' => RhUniformeEntrega::SIT_ENTREGUE,
        ]);
        $entrega->setRelation('funcionario', $func);
        $entrega->setRelation('uniforme', $uniforme);

        $this->assertSame('7', RhUniformesListagemReport::cellValue($entrega, 'codigo'));
        $this->assertSame('10 JOAO', RhUniformesListagemReport::cellValue($entrega, 'funcionario'));
        $this->assertSame('CAMISA POLO', RhUniformesListagemReport::cellValue($entrega, 'uniforme'));
        $this->assertSame('M', RhUniformesListagemReport::cellValue($entrega, 'tamanho'));
        $this->assertSame('2', RhUniformesListagemReport::cellValue($entrega, 'quantidade'));
        $this->assertSame('15/01/2026', RhUniformesListagemReport::cellValue($entrega, 'entregue_em'));
        $this->assertSame('Entregue', RhUniformesListagemReport::cellValue($entrega, 'situacao'));
    }
}
