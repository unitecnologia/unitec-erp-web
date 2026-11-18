<?php

namespace Tests\Unit;

use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Support\Erp\Reports\RhDocumentosListagemReport;
use Tests\TestCase;

class RhDocumentosListagemReportTest extends TestCase
{
    public function test_colunas_padrao(): void
    {
        $cols = RhDocumentosListagemReport::defaultColumns();

        $this->assertSame(['id', 'funcionario', 'titulo', 'emitido_em', 'valido_ate', 'situacao'], $cols);
        $this->assertArrayHasKey('titulo', RhDocumentosListagemReport::columnDefinitions());
    }

    public function test_cell_value_formatacao(): void
    {
        $func = new RhFuncionario(['codigo' => '10', 'nome' => 'JOAO']);
        $anexo = new RhAnexo([
            'titulo' => 'CNH',
            'emitido_em' => '2026-01-15',
            'valido_ate' => '2026-12-31',
        ]);
        $anexo->id = 7;
        $anexo->setRelation('anexavel', $func);

        $this->assertSame('7', RhDocumentosListagemReport::cellValue($anexo, 'id'));
        $this->assertSame('10 JOAO', RhDocumentosListagemReport::cellValue($anexo, 'funcionario'));
        $this->assertSame('CNH', RhDocumentosListagemReport::cellValue($anexo, 'titulo'));
        $this->assertSame('15/01/2026', RhDocumentosListagemReport::cellValue($anexo, 'emitido_em'));
        $this->assertSame('31/12/2026', RhDocumentosListagemReport::cellValue($anexo, 'valido_ate'));
    }
}
