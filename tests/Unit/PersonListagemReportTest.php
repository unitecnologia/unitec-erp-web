<?php

namespace Tests\Unit;

use App\Support\Erp\Reports\PersonListagemReport;
use PHPUnit\Framework\TestCase;

class PersonListagemReportTest extends TestCase
{
    public function test_resolve_columns_usa_padrao_quando_nao_informado(): void
    {
        $columns = PersonListagemReport::resolveColumns(null);

        $this->assertSame(PersonListagemReport::defaultColumns(), $columns);
        $this->assertContains('nome_razao', $columns);
        $this->assertNotContains('limite_credito', $columns);
    }

    public function test_exibe_endereco_formatado(): void
    {
        $person = new \App\Models\Person([
            'endereco' => 'RUA A',
            'numero' => '10',
            'bairro' => 'CENTRO',
            'cidade_nome' => 'JOINVILLE',
            'uf' => 'SC',
        ]);

        $this->assertSame('RUA A, nº 10, CENTRO, JOINVILLE, SC', PersonListagemReport::cellValue($person, 'endereco'));
    }

    public function test_totaliza_quantidade_e_limite_credito(): void
    {
        $people = [
            new \App\Models\Person(['limite_credito' => 100]),
            new \App\Models\Person(['limite_credito' => 50]),
        ];

        $columns = ['codigo', 'nome_razao', 'limite_credito'];
        $totals = PersonListagemReport::columnTotals($people, $columns);

        $this->assertSame('2', $totals['codigo']);
        $this->assertSame('TOTAL', $totals['nome_razao']);
        $this->assertSame('150,00', $totals['limite_credito']);
    }

    public function test_titulo_por_tipo(): void
    {
        $this->assertSame('LISTAGEM DE CLIENTES', PersonListagemReport::reportTitle('clientes'));
        $this->assertSame('LISTAGEM SPC/CCF', PersonListagemReport::reportTitle('ccf_spc'));
    }
}
