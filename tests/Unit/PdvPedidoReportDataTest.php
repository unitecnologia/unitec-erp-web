<?php

namespace Tests\Unit;

use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\Terminal;
use App\Support\Erp\Pdv\PdvPedidoReportData;
use Tests\TestCase;

class PdvPedidoReportDataTest extends TestCase
{

    public function test_it_detects_pedido_a4_from_terminal(): void
    {
        $terminalA4 = new Terminal(['tipo_impressora' => '0']);
        $terminalEscPos = new Terminal(['tipo_impressora' => '1']);

        $this->assertTrue(PdvPedidoReportData::shouldUsePedidoA4($terminalA4));
        $this->assertFalse(PdvPedidoReportData::shouldUsePedidoA4($terminalEscPos));
        $this->assertFalse(PdvPedidoReportData::shouldUsePedidoA4(null));
    }

    public function test_it_formats_meio_pagamento_from_pagamentos(): void
    {
        $venda = new PdvVenda([
            'forma_pagamento' => 'DINHEIRO',
        ]);

        $venda->setRelation('pagamentos', collect([
            new PdvVendaPagamento(['forma' => 'PIX', 'valor' => 50]),
            new PdvVendaPagamento(['forma' => 'DINHEIRO', 'valor' => 81.20]),
        ]));

        $this->assertSame('PIX / DINHEIRO', PdvPedidoReportData::formatMeioPagamento($venda));
    }

    public function test_it_falls_back_to_forma_pagamento_when_sem_pagamentos(): void
    {
        $venda = new PdvVenda([
            'forma_pagamento' => 'CARTAO DEBITO',
        ]);
        $venda->setRelation('pagamentos', collect());

        $this->assertSame('CARTAO DEBITO', PdvPedidoReportData::formatMeioPagamento($venda));
    }

    public function test_it_formats_declaracao_cidade_uf(): void
    {
        $empresa = new \App\Models\Empresa([
            'cidade' => 'Balneário Camboriú',
            'uf' => 'SC',
        ]);

        $this->assertSame('BALNEÁRIO CAMBORIÚ-SC', PdvPedidoReportData::formatDeclaracaoCidadeUf($empresa));
    }

    public function test_it_formats_declaracao_texto(): void
    {
        $empresa = new \App\Models\Empresa([
            'cidade' => 'Balneário Camboriú',
            'uf' => 'SC',
        ]);

        $texto = PdvPedidoReportData::formatDeclaracaoTexto(
            $empresa,
            new \DateTimeImmutable('2026-07-02'),
        );

        $this->assertSame(
            'Declaro que recebi os itens descritos acima, BALNEÁRIO CAMBORIÚ-SC, 02/07/2026',
            $texto,
        );
    }
}
