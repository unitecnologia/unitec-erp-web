<?php

namespace Tests\Unit;

use App\Support\ForcaVendas\ForcaVendasItemAgrupamento;
use PHPUnit\Framework\TestCase;

class ForcaVendasItemAgrupamentoTest extends TestCase
{
    public function test_desconto_unitario_reescala_ao_passar_de_1_para_40(): void
    {
        // Qtd 1 | Preço 10 | Desconto linha 1,00 → adicionar 39
        $r = ForcaVendasItemAgrupamento::consolidar(
            quantidadeAtual: 1.0,
            descontoLinhaAtual: 1.0,
            acrescimoLinhaAtual: 0.0,
            quantidadeAdicionar: 39.0,
            descontoAdicionar: 0.0,
            acrescimoAdicionar: 0.0,
        );

        $this->assertSame(40.0, $r['quantidade']);
        $this->assertSame(40.0, $r['desconto']);
        $this->assertSame(0.0, $r['acrescimo']);

        $preco = 10.0;
        $total = round(($r['quantidade'] * $preco) + $r['acrescimo'] - $r['desconto'], 2);
        $this->assertSame(360.0, $total);
    }

    public function test_desconto_1_50_por_unidade_ao_dobrar_quantidade(): void
    {
        // Qtd 5 | desconto R$ 1,50/un (linha 7,50) → adicionar mais 5
        $r = ForcaVendasItemAgrupamento::consolidar(
            quantidadeAtual: 5.0,
            descontoLinhaAtual: 7.50,
            acrescimoLinhaAtual: 0.0,
            quantidadeAdicionar: 5.0,
            descontoAdicionar: 0.0,
            acrescimoAdicionar: 0.0,
        );

        $this->assertSame(10.0, $r['quantidade']);
        $this->assertSame(15.0, $r['desconto']);
        $this->assertSame(0.0, $r['acrescimo']);
    }

    public function test_acrescimo_unitario_reescala(): void
    {
        $r = ForcaVendasItemAgrupamento::consolidar(
            quantidadeAtual: 2.0,
            descontoLinhaAtual: 0.0,
            acrescimoLinhaAtual: 3.0, // 1,50/un
            quantidadeAdicionar: 2.0,
            descontoAdicionar: 0.0,
            acrescimoAdicionar: 0.0,
        );

        $this->assertSame(4.0, $r['quantidade']);
        $this->assertSame(0.0, $r['desconto']);
        $this->assertSame(6.0, $r['acrescimo']);
    }

    public function test_sem_ajuste_soma_desconto_da_nova_entrada_como_antes(): void
    {
        $r = ForcaVendasItemAgrupamento::consolidar(
            quantidadeAtual: 3.0,
            descontoLinhaAtual: 0.0,
            acrescimoLinhaAtual: 0.0,
            quantidadeAdicionar: 2.0,
            descontoAdicionar: 4.0,
            acrescimoAdicionar: 1.0,
        );

        $this->assertSame(5.0, $r['quantidade']);
        $this->assertSame(4.0, $r['desconto']);
        $this->assertSame(1.0, $r['acrescimo']);
    }

    public function test_percentual_ja_convertido_em_linha_permanece_proporcional(): void
    {
        // 10% de R$ 10 em 1 un → desconto linha 1,00; ao ir para 40 un → 40,00
        $r = ForcaVendasItemAgrupamento::consolidar(
            quantidadeAtual: 1.0,
            descontoLinhaAtual: 1.0,
            acrescimoLinhaAtual: 0.0,
            quantidadeAdicionar: 39.0,
            descontoAdicionar: 0.0,
            acrescimoAdicionar: 0.0,
        );

        $this->assertSame(40.0, $r['desconto']);
        $this->assertEqualsWithDelta(10.0, ($r['desconto'] / ($r['quantidade'] * 10.0)) * 100, 0.01);
    }
}
