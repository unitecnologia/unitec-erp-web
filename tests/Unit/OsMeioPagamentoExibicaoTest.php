<?php

namespace Tests\Unit;

use App\Models\OrdemServico;
use App\Support\Erp\Os\OsMeioPagamentoExibicao;
use Tests\TestCase;

class OsMeioPagamentoExibicaoTest extends TestCase
{
    public function test_pagamento_unico_sem_mais(): void
    {
        $ordem = $this->ordemComPagamentos([
            ['forma' => 'DINHEIRO', 'valor' => 150.5],
        ]);

        $exibicao = OsMeioPagamentoExibicao::exibicao($ordem);

        $this->assertSame('Dinheiro', $exibicao['visivel']);
        $this->assertSame('DINHEIRO — R$ 150,50', $exibicao['tooltip']);
    }

    public function test_misto_mostra_mais_com_valores_no_tooltip(): void
    {
        $ordem = $this->ordemComPagamentos([
            ['forma' => 'DINHEIRO', 'valor' => 100],
            ['forma' => 'PIX', 'valor' => 50],
        ]);

        $exibicao = OsMeioPagamentoExibicao::exibicao($ordem);

        $this->assertSame('Dinheiro +', $exibicao['visivel']);
        $this->assertSame("DINHEIRO — R$ 100,00\nPIX — R$ 50,00", $exibicao['tooltip']);
    }

    public function test_legado_sem_valor_nao_inventa_valores(): void
    {
        $ordem = $this->ordemComPagamentos([
            ['forma' => 'DINHEIRO'],
            ['forma' => 'PIX'],
            ['forma' => 'POS DÉBITO'],
        ]);

        $exibicao = OsMeioPagamentoExibicao::exibicao($ordem);

        $this->assertSame('Dinheiro +', $exibicao['visivel']);
        $this->assertSame("DINHEIRO\nPIX\nPOS DÉBITO", $exibicao['tooltip']);
    }

    public function test_sem_pagamentos_retorna_traco(): void
    {
        $ordem = $this->ordemComPagamentos(null);

        $exibicao = OsMeioPagamentoExibicao::exibicao($ordem);

        $this->assertSame('—', $exibicao['visivel']);
        $this->assertSame('', $exibicao['tooltip']);
    }

    public function test_html_celula_inclui_title(): void
    {
        $ordem = $this->ordemComPagamentos([
            ['forma' => 'DINHEIRO', 'valor' => 10],
            ['forma' => 'PIX', 'valor' => 5],
        ]);

        $html = OsMeioPagamentoExibicao::htmlCelula($ordem);

        $this->assertStringContainsString('Dinheiro +', $html);
        $this->assertStringContainsString('title="', $html);
        $this->assertStringContainsString('DINHEIRO — R$ 10,00', $html);
    }

    /**
     * @param  list<array{forma: string, valor?: float|int}>|null  $pagamentos
     */
    private function ordemComPagamentos(?array $pagamentos): OrdemServico
    {
        $ordem = new OrdemServico;
        $ordem->forceFill(['faturamento_pagamentos' => $pagamentos]);

        return $ordem;
    }
}
