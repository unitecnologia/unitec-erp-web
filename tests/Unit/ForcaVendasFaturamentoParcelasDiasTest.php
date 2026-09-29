<?php

namespace Tests\Unit;

use App\Models\FormaPagamento;
use App\Support\ForcaVendas\ForcaVendasFaturamentoService;
use PHPUnit\Framework\TestCase;

class ForcaVendasFaturamentoParcelasDiasTest extends TestCase
{
    private function service(): ForcaVendasFaturamentoService
    {
        return new ForcaVendasFaturamentoService();
    }

    private function forma(int $max, int $intervalo, ?string $modoPrazo = null): FormaPagamento
    {
        $forma = new FormaPagamento();
        $forma->max_parcelas = $max;
        $forma->intervalo_parcelas = $intervalo;
        $forma->modo_prazo = $modoPrazo;
        $forma->descricao = 'BOLETO TESTE';

        return $forma;
    }

    public function test_payload_negociado_ganha_sobre_boleto_7(): void
    {
        $dias = $this->service()->resolverParcelasDias(
            ['tabela_prazo_dias' => '21'],
            $this->forma(1, 7),
            null,
        );

        $this->assertSame([21], $dias);
    }

    public function test_condicao_pagamento_negociada_ganha(): void
    {
        $dias = $this->service()->resolverParcelasDias(
            ['condicao_pagamento' => '14', 'tabela_prazo_dias' => '30'],
            $this->forma(1, 7),
            [30, 60],
        );

        $this->assertSame([14], $dias);
    }

    public function test_payload_vazio_usa_tabela_cliente(): void
    {
        $dias = $this->service()->resolverParcelasDias(
            ['tabela_prazo_dias' => null, 'condicao_pagamento' => null],
            $this->forma(1, 7),
            [30, 60],
        );

        $this->assertSame([30, 60], $dias);
    }

    public function test_payload_vazio_sem_tabela_usa_boleto_7(): void
    {
        $dias = $this->service()->resolverParcelasDias(
            [],
            $this->forma(1, 7),
            null,
        );

        $this->assertSame([7], $dias);
    }

    public function test_payload_vazio_sem_tabela_forma_3x30(): void
    {
        $dias = $this->service()->resolverParcelasDias(
            [],
            $this->forma(3, 30),
            null,
        );

        $this->assertSame([30, 60, 90], $dias);
    }

    public function test_payload_vazio_default_1x30_cai_em_zero(): void
    {
        $dias = $this->service()->resolverParcelasDias(
            [],
            $this->forma(1, 30),
            null,
        );

        $this->assertSame([0], $dias);
    }

    public function test_sem_prazo_e_sem_forma_cai_em_zero(): void
    {
        $dias = $this->service()->resolverParcelasDias([], null, null);

        $this->assertSame([0], $dias);
    }

    public function test_simulacao_pedido_48_boleto_7(): void
    {
        // Espelho do pedido real #48: payload sem prazo + BOLETO 7 (1,7).
        $dias = $this->service()->resolverParcelasDias(
            [
                'forma_pagamento_id' => 4,
                'forma_pagamento' => 'BOLETO 7',
                'tabela_prazo_dias' => null,
                'condicao_pagamento' => null,
                'cartao_canhoto' => null,
            ],
            $this->forma(1, 7),
            null,
        );

        $this->assertSame([7], $dias);
    }

    public function test_modo_tabela_nao_usa_max_intervalo_antigo(): void
    {
        $dias = $this->service()->resolverParcelasDias(
            [
                'forma_pagamento' => 'BOLETO 7',
                'tabela_prazo_dias' => null,
                'condicao_pagamento' => null,
            ],
            $this->forma(1, 7, 'tabela'),
            null,
        );

        // Sem prazo negociado e sem tabela do cliente → fallback [0].
        $this->assertSame([0], $dias);
    }

    public function test_modo_financeiro_aceita_1x30(): void
    {
        $dias = $this->service()->resolverParcelasDias(
            [],
            $this->forma(1, 30, 'financeiro'),
            null,
        );

        $this->assertSame([30], $dias);
    }
}
