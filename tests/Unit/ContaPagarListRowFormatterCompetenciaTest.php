<?php

namespace Tests\Unit;

use App\Models\ContaPagar;
use App\Support\Erp\ContaPagarListRowFormatter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContaPagarListRowFormatterCompetenciaTest extends TestCase
{
    public function test_coluna_competencia_exibe_mm_aaaa(): void
    {
        $conta = new ContaPagar();
        $conta->numero = 1;
        $conta->emissao = Carbon::parse('2026-11-10');
        $conta->vencimento = Carbon::parse('2026-12-15');
        $conta->competencia = Carbon::parse('2026-11-03');
        $conta->valor = 100;
        $conta->desconto = 0;
        $conta->juros = 0;
        $conta->valor_pago = 0;
        $conta->saldo = 100;

        $row = (new ContaPagarListRowFormatter())->format($conta);

        $this->assertSame('11/2026', $row['competencia']);
        $this->assertSame('10/11/2026', $row['emissao']);
        $this->assertSame('15/12/2026', $row['vencimento']);
        $this->assertStringContainsString('erp-pagar-money__currency', $row['valor']);
        $this->assertStringContainsString('>100,00</span>', $row['valor']);
        $this->assertStringContainsString('erp-pagar-money__amount', $row['desconto']);
        $this->assertStringContainsString('erp-pagar-money__amount', $row['juros']);
        $this->assertStringContainsString('erp-pagar-money__amount', $row['valor_pago']);
        $this->assertStringContainsString('erp-pagar-money__amount', $row['saldo']);
        $this->assertStringContainsString('>100,00</span>', $row['saldo']);
    }
}
