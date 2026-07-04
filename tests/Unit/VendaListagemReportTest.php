<?php

namespace Tests\Unit;

use App\Models\Venda;
use App\Support\Erp\Reports\VendaListagemReport;
use PHPUnit\Framework\TestCase;

class VendaListagemReportTest extends TestCase
{
    public function test_resolve_columns_usa_padrao_quando_nao_informado(): void
    {
        $columns = VendaListagemReport::resolveColumns(null);

        $this->assertSame(VendaListagemReport::defaultColumns(), $columns);
        $this->assertContains('numero', $columns);
        $this->assertContains('total', $columns);
    }

    public function test_formata_numero_sem_zeros_a_esquerda(): void
    {
        $this->assertSame('42', VendaListagemReport::formatNumero('000042'));
    }

    public function test_totaliza_quantidade_e_valor(): void
    {
        $vendas = [
            new Venda(['total' => 100]),
            new Venda(['total' => 50.5]),
        ];

        $columns = ['numero', 'cliente', 'total'];
        $totals = VendaListagemReport::columnTotals($vendas, $columns);

        $this->assertSame('2', $totals['numero']);
        $this->assertSame('TOTAL', $totals['cliente']);
        $this->assertSame('150,50', $totals['total']);
    }

    public function test_rotulos_status_incluem_todos(): void
    {
        $this->assertArrayHasKey('todos', VendaListagemReport::statusLabels());
        $this->assertSame('Fechado', VendaListagemReport::statusLabels()[Venda::STATUS_FECHADO]);
    }
}
