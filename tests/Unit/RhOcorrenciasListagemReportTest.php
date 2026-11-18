<?php

namespace Tests\Unit;

use App\Models\RhFuncionario;
use App\Models\RhOcorrencia;
use App\Support\Erp\Reports\RhOcorrenciasListagemReport;
use Tests\TestCase;

class RhOcorrenciasListagemReportTest extends TestCase
{
    public function test_colunas_padrao(): void
    {
        $cols = RhOcorrenciasListagemReport::defaultColumns();
        $this->assertSame(
            ['codigo', 'funcionario', 'tipo', 'ocorrido_em', 'descricao', 'situacao'],
            $cols
        );
    }

    public function test_cell_value_mascara_restrito(): void
    {
        $func = new RhFuncionario(['codigo' => '3', 'nome' => 'ANA']);
        $oc = new RhOcorrencia([
            'codigo' => '12',
            'tipo' => RhOcorrencia::TIPO_ADVERTENCIA,
            'ocorrido_em' => '2026-11-05',
            'descricao' => 'Texto confidencial do registro',
            'acesso_restrito' => true,
            'situacao' => RhOcorrencia::SIT_REGISTRADA,
        ]);
        $oc->setRelation('funcionario', $func);

        $this->assertSame('▪ Conteúdo restrito', RhOcorrenciasListagemReport::cellValue($oc, 'descricao', false));
        $this->assertStringContainsString('confidencial', RhOcorrenciasListagemReport::cellValue($oc, 'descricao', true));
        $this->assertSame('Advertência', RhOcorrenciasListagemReport::cellValue($oc, 'tipo', true));
    }
}
