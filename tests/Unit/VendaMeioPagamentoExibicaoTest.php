<?php

namespace Tests\Unit;

use App\Models\ForcaVendasOrder;
use App\Models\OrdemServico;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\Venda;
use App\Support\Erp\VendaMeioPagamentoExibicao;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class VendaMeioPagamentoExibicaoTest extends TestCase
{
    public function test_pagamento_unico_sem_mais(): void
    {
        $venda = $this->vendaComCabecalho('DINHEIRO');

        $exibicao = VendaMeioPagamentoExibicao::exibicao($venda);

        $this->assertSame('Dinheiro', $exibicao['visivel']);
        $this->assertSame('DINHEIRO', $exibicao['tooltip']);
    }

    public function test_misto_legado_mostra_mais_sem_inventar_valores(): void
    {
        $venda = $this->vendaComCabecalho('DINHEIRO / PIX / POS DÉBITO');

        $exibicao = VendaMeioPagamentoExibicao::exibicao($venda);

        $this->assertSame('Dinheiro +', $exibicao['visivel']);
        $this->assertSame("DINHEIRO\nPIX\nPOS DÉBITO", $exibicao['tooltip']);
    }

    public function test_fv_payload_com_valores_no_tooltip(): void
    {
        $venda = new Venda;
        $venda->forceFill(['forma_pagamento' => 'DINHEIRO / PIX']);

        $order = new ForcaVendasOrder;
        $order->forceFill([
            'payload' => [
                'pagamentos' => [
                    ['descricao' => 'DINHEIRO', 'valor' => 100],
                    ['descricao' => 'PIX', 'valor' => 50],
                ],
            ],
        ]);
        $venda->setRelation('forcaVendasOrder', $order);
        $venda->setRelation('pdvVenda', null);
        $venda->setRelation('ordemServico', null);

        $exibicao = VendaMeioPagamentoExibicao::exibicao($venda);

        $this->assertSame('Dinheiro +', $exibicao['visivel']);
        $this->assertSame("DINHEIRO — R$ 100,00\nPIX — R$ 50,00", $exibicao['tooltip']);
    }

    public function test_pdv_pagamentos_com_valores(): void
    {
        $venda = new Venda;
        $venda->forceFill(['forma_pagamento' => 'DINHEIRO / PIX']);
        $venda->setRelation('forcaVendasOrder', null);

        $pdv = new PdvVenda;
        $pag1 = new PdvVendaPagamento;
        $pag1->forceFill(['forma' => 'DINHEIRO', 'valor' => 80]);
        $pag2 = new PdvVendaPagamento;
        $pag2->forceFill(['forma' => 'PIX', 'valor' => 20]);
        $pdv->setRelation('pagamentos', new Collection([$pag1, $pag2]));
        $venda->setRelation('pdvVenda', $pdv);
        $venda->setRelation('ordemServico', null);

        $exibicao = VendaMeioPagamentoExibicao::exibicao($venda);

        $this->assertSame('Dinheiro +', $exibicao['visivel']);
        $this->assertSame("DINHEIRO — R$ 80,00\nPIX — R$ 20,00", $exibicao['tooltip']);
    }

    public function test_os_faturamento_pagamentos_com_valores(): void
    {
        $venda = new Venda;
        $venda->forceFill(['forma_pagamento' => 'DINHEIRO / PIX']);
        $venda->setRelation('forcaVendasOrder', null);
        $venda->setRelation('pdvVenda', null);

        $os = new OrdemServico;
        $os->forceFill([
            'faturamento_pagamentos' => [
                ['forma' => 'DINHEIRO', 'valor' => 70],
                ['forma' => 'PIX', 'valor' => 30],
            ],
        ]);
        $venda->setRelation('ordemServico', $os);

        $exibicao = VendaMeioPagamentoExibicao::exibicao($venda);

        $this->assertSame('Dinheiro +', $exibicao['visivel']);
        $this->assertSame("DINHEIRO — R$ 70,00\nPIX — R$ 30,00", $exibicao['tooltip']);
    }

    public function test_html_celula_inclui_title(): void
    {
        $venda = $this->vendaComCabecalho('DINHEIRO / PIX');

        $html = VendaMeioPagamentoExibicao::htmlCelula($venda);

        $this->assertStringContainsString('Dinheiro +', $html);
        $this->assertStringContainsString('title="', $html);
        $this->assertStringContainsString('DINHEIRO', $html);
        $this->assertStringContainsString('PIX', $html);
    }

    private function vendaComCabecalho(string $forma): Venda
    {
        $venda = new Venda;
        $venda->forceFill(['forma_pagamento' => $forma]);
        $venda->setRelation('forcaVendasOrder', null);
        $venda->setRelation('pdvVenda', null);
        $venda->setRelation('ordemServico', null);

        return $venda;
    }
}
