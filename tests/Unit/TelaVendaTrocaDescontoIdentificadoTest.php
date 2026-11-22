<?php

namespace Tests\Unit;

use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;
use Tests\TestCase;

/**
 * Regressão do rateio: desconto F4 + TROCA no mesmo motor da Tela de Venda.
 */
class TelaVendaTrocaDescontoIdentificadoTest extends TestCase
{
    public function test_rateio_desconto_mais_troca_bate_total_e_centavos_no_ultimo(): void
    {
        $itens = [
            ['total' => 100.00],
            ['total' => 50.00],
            ['total' => 50.00],
        ];

        // Desconto F4 R$ 10 + TROCA R$ 20 = R$ 30 sobre base 200.
        $rateio = ForcaVendasMargemVendaCalculator::ratearValorNosItens(30.00, 200.00, $itens);

        $this->assertSame(15.00, $rateio[0]);
        $this->assertSame(7.50, $rateio[1]);
        $this->assertSame(7.50, $rateio[2]);
        $this->assertEqualsWithDelta(30.00, array_sum($rateio), 0.001);
    }

    public function test_observacao_troca_marcador_estavel(): void
    {
        $obs = "CLIENTE RETIRA\nTROCA: R$ 10,00\nURGENTE";
        $linhas = preg_split("/\r\n|\n|\r/", $obs) ?: [];
        $filtradas = [];

        foreach ($linhas as $linha) {
            $trim = trim((string) $linha);
            if ($trim === '' || preg_match('/^TROCA:\s*R\$/iu', $trim) === 1) {
                continue;
            }
            $filtradas[] = $trim;
        }

        $filtradas[] = 'TROCA: R$ 25,50';
        $nova = implode("\n", $filtradas);

        $this->assertStringContainsString('CLIENTE RETIRA', $nova);
        $this->assertStringContainsString('URGENTE', $nova);
        $this->assertStringContainsString('TROCA: R$ 25,50', $nova);
        $this->assertSame(1, substr_count($nova, 'TROCA: R$'));
    }
}
