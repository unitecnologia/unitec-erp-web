<?php

namespace Tests\Unit\Pix;

use App\Models\CaixaLancamento;
use App\Support\Erp\CaixaListRowFormatter;
use App\Support\Pix\PixPdvLivroCaixaService;
use Tests\TestCase;

class PixPdvPedidoExibicaoTest extends TestCase
{
    public function test_coluna_pedido_mostra_numero_da_venda_e_mantem_documento(): void
    {
        $this->assertSame(
            '8',
            PixPdvLivroCaixaService::numeroPedidoExibicao(
                'PIX-PDV-1065',
                'VENDA PDV #8 PIX AILOSPDV501M75EF9ADEA666C5D1EE13B',
            ),
        );
        $this->assertSame(
            '1',
            PixPdvLivroCaixaService::numeroPedidoExibicao('PIX-PDV-9', 'VENDA PDV #000001 PIX'),
        );
        $this->assertNull(PixPdvLivroCaixaService::numeroPedidoExibicao('PDV-000008', 'VENDA PDV #8 PIX'));
        $this->assertNull(PixPdvLivroCaixaService::numeroPedidoExibicao('PIX-PDV-1065', 'CREDITO PIX'));

        $lancamento = new CaixaLancamento([
            'codigo' => 44,
            'emissao' => '2026-11-16',
            'documento' => 'PIX-PDV-1065',
            'historico' => 'VENDA PDV #8 PIX AILOSPDV501M75EF9ADEA666C5D1EE13B',
            'plano_contas' => 'VENDAS',
            'entrada' => 10.5,
            'saida' => 0,
        ]);

        $row = (new CaixaListRowFormatter)->format($lancamento);

        $this->assertSame('8', $row['documento']);
        $this->assertStringContainsString('VENDA PDV #8 PIX', $row['historico']);
        $this->assertStringContainsString('10,50', $row['entrada']);
        $this->assertSame('PIX-PDV-1065', $lancamento->documento);
    }
}
