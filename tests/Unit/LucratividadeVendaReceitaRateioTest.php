<?php

namespace Tests\Unit;

use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaReceitaRateio;
use PHPUnit\Framework\TestCase;

class LucratividadeVendaReceitaRateioTest extends TestCase
{
    public function test_sem_diferenca_mantem_totais(): void
    {
        $out = LucratividadeVendaReceitaRateio::totaisRateados(100.0, [40, 60]);

        $this->assertSame(['40.00', '60.00'], $out);
    }

    public function test_desconto_cabecalho_rateia_e_fecha_no_alvo(): void
    {
        // Itens 50+50=100, venda.total=90 → desconto 10
        $out = LucratividadeVendaReceitaRateio::totaisRateados(90.0, [50, 50]);

        $this->assertSame(90.0, round((float) $out[0] + (float) $out[1], 2));
        $this->assertSame('45.00', $out[0]);
        $this->assertSame('45.00', $out[1]);
    }

    public function test_acrescimo_cabecalho_rateia_e_fecha_no_alvo(): void
    {
        $out = LucratividadeVendaReceitaRateio::totaisRateados(110.0, [50, 50]);

        $this->assertSame(110.0, round((float) $out[0] + (float) $out[1], 2));
        $this->assertSame('55.00', $out[0]);
        $this->assertSame('55.00', $out[1]);
    }

    public function test_desconto_com_centavos_fica_no_ultimo(): void
    {
        $out = LucratividadeVendaReceitaRateio::totaisRateados(100.0, [30, 30, 40.01]);
        $soma = round(array_sum(array_map('floatval', $out)), 2);

        $this->assertSame(100.0, $soma);
    }
}
