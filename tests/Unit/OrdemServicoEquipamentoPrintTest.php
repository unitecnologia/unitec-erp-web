<?php

namespace Tests\Unit;

use App\Models\OrdemServico;
use App\Support\Erp\Os\OrdemServicoReportData;
use Tests\TestCase;

class OrdemServicoEquipamentoPrintTest extends TestCase
{
    public function test_monta_linhas_compactas_e_omite_campos_vazios(): void
    {
        $ordem = new OrdemServico([
            'descricao' => 'toyota',
            'modelo' => 'corolla',
            'ano' => '2022',
            'placa' => 'qtl6g28',
            'km' => 121000,
        ]);

        $rows = OrdemServicoReportData::equipamentoLinhas($ordem);

        $this->assertCount(2, $rows);
        $this->assertSame(['Equipamento / Marca', 'Modelo', 'Ano'], array_column($rows[0], 'label'));
        $this->assertSame(['TOYOTA', 'COROLLA', '2022'], array_column($rows[0], 'value'));
        $this->assertSame(['Placa', 'KM'], array_column($rows[1], 'label'));
        $this->assertSame(['QTL6G28', '121000'], array_column($rows[1], 'value'));
    }

    public function test_complemento_so_entra_quando_preenchido(): void
    {
        $sem = OrdemServicoReportData::equipamentoLinhas(new OrdemServico([
            'descricao' => 'DELL',
        ]));
        $this->assertCount(1, $sem);

        $com = OrdemServicoReportData::equipamentoLinhas(new OrdemServico([
            'descricao' => 'DELL',
            'descricao2' => 'tela trincada',
        ]));
        $this->assertCount(2, $com);
        $this->assertSame('Descrição / Complemento', $com[1][0]['label']);
        $this->assertSame('TELA TRINCADA', $com[1][0]['value']);
    }

    public function test_usa_fallback_simples_dos_campos_antigos(): void
    {
        $rows = OrdemServicoReportData::equipamentoLinhas(new OrdemServico([
            'marca_veiculo' => 'epson',
            'modelo_veiculo' => 'l3150',
            'placa_veiculo' => 'aaa1a11',
        ]));

        $this->assertSame('EPSON', $rows[0][0]['value']);
        $this->assertSame('L3150', $rows[0][1]['value']);
        $this->assertSame('AAA1A11', $rows[1][0]['value']);
    }
}
