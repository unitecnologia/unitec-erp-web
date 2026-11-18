<?php

namespace Tests\Unit;

use App\Support\Erp\Financeiro\ContaPagarCadastroService;
use PHPUnit\Framework\TestCase;

class ContaPagarCadastroParcelasTest extends TestCase
{
    public function test_distribuir_valor_soma_exata_com_centavos(): void
    {
        $service = new ContaPagarCadastroService();
        $valores = $service->distribuirValor(100.00, 3);

        $this->assertSame([33.34, 33.33, 33.33], $valores);
        $this->assertSame(10000, (int) round(array_sum($valores) * 100));
    }

    public function test_distribuir_valor_uma_parcela(): void
    {
        $service = new ContaPagarCadastroService();
        $valores = $service->distribuirValor(55.55, 1);

        $this->assertSame([55.55], $valores);
    }

    public function test_montar_parcelas_mensais_padrao_compativel(): void
    {
        $service = new ContaPagarCadastroService();
        $preview = $service->montarParcelas(100.00, 3, '2026-01-31', 1, ContaPagarCadastroService::INTERVALO_MESES);

        $this->assertCount(3, $preview);
        $this->assertSame('2026-01-31', $preview[0]['vencimento']);
        $this->assertSame('2026-02-28', $preview[1]['vencimento']);
        $this->assertSame('2026-03-31', $preview[2]['vencimento']);
        $this->assertSame(10000, (int) round(array_sum(array_column($preview, 'valor')) * 100));
    }

    public function test_montar_parcelas_intervalo_dias(): void
    {
        $service = new ContaPagarCadastroService();
        $preview = $service->montarParcelas(90.00, 3, '2026-01-10', 15, ContaPagarCadastroService::INTERVALO_DIAS);

        $this->assertSame('2026-01-10', $preview[0]['vencimento']);
        $this->assertSame('2026-01-25', $preview[1]['vencimento']);
        $this->assertSame('2026-02-09', $preview[2]['vencimento']);
        $this->assertSame([30.0, 30.0, 30.0], array_column($preview, 'valor'));
    }

    public function test_intervalo_unidade_invalida_vira_meses(): void
    {
        $service = new ContaPagarCadastroService();

        $this->assertSame(
            ContaPagarCadastroService::INTERVALO_MESES,
            $service->normalizarIntervaloUnidade('semanas'),
        );
    }
}
