<?php

namespace Tests\Unit;

use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaCustoResolver;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaLucroPercentual;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;
use Tests\TestCase;

class LucratividadeVendaLucroPercentualTest extends TestCase
{
    public function test_lucro_sobre_custo_exemplo_100_sobre_50(): void
    {
        // Venda 100, custo 50 → ((100-50)/50)×100 = 100%
        $this->assertSame(100.0, LucratividadeVendaLucroPercentual::sobreCusto(100, 50));
        $this->assertSame('100,00%', LucratividadeVendaLucroPercentual::rotulo(100, 50));
    }

    public function test_diferente_da_margem_sobre_venda_da_tela(): void
    {
        // Tela/Pedido: (50/100)×100 = 50% sobre a venda
        $this->assertSame(50.0, ForcaVendasMargemVendaCalculator::margemPercentual(100, 50));
        // Relatórios: 100% sobre o custo
        $this->assertSame(100.0, LucratividadeVendaLucroPercentual::sobreCusto(100, 50));
    }

    public function test_custo_zero_indisponivel(): void
    {
        $this->assertNull(LucratividadeVendaLucroPercentual::sobreCusto(100, 0));
        $this->assertSame(
            LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL,
            LucratividadeVendaLucroPercentual::rotulo(100, 0),
        );
    }

    public function test_prejuizo_sobre_custo(): void
    {
        // Venda 80, custo 100 → -20%
        $this->assertSame(-20.0, LucratividadeVendaLucroPercentual::sobreCusto(80, 100));
    }
}
