<?php

namespace Tests\Unit;

use App\Support\Erp\DescontoReaisItemCalculator;
use App\Support\Erp\EmpresaParametros;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DescontoReaisItemCalculatorTest extends TestCase
{
    #[DataProvider('cenariosObrigatoriosProvider')]
    public function test_cenarios_obrigatorios_da_solicitacao(
        float $quantidade,
        float $preco,
        float $descontoDigitado,
        string $modo,
        float $descontoTotalEsperado,
        float $totalLiquidoEsperado,
    ): void {
        $calc = DescontoReaisItemCalculator::calcular(
            $preco,
            $quantidade,
            $descontoDigitado,
            'desconto',
            'valor',
            $modo,
        );

        $this->assertEqualsWithDelta($descontoTotalEsperado, $calc['ajusteLinha'], 0.001);
        $this->assertEqualsWithDelta($totalLiquidoEsperado, $calc['total'], 0.001);
    }

    /**
     * @return array<string, array{float, float, float, string, float, float}>
     */
    public static function cenariosObrigatoriosProvider(): array
    {
        return [
            '1 un × R$1 unidade' => [1.0, 10.0, 1.0, 'unitario', 1.0, 9.0],
            '6 un × R$1 unidade' => [6.0, 10.0, 1.0, 'unitario', 6.0, 54.0],
            '6 un × R$1 linha' => [6.0, 10.0, 1.0, 'linha', 1.0, 59.0],
            '10 un × R$0,50 unidade' => [10.0, 5.0, 0.5, 'unitario', 5.0, 45.0],
            '10 un × R$0,50 linha' => [10.0, 5.0, 0.5, 'linha', 0.5, 49.5],
        ];
    }

    public function test_quantidade_fracionaria_por_unidade(): void
    {
        $calc = DescontoReaisItemCalculator::calcular(
            10.0,
            1.5,
            1.0,
            'desconto',
            'valor',
            EmpresaParametros::DESCONTO_REAIS_ITEM_UNITARIO,
        );

        $this->assertEqualsWithDelta(1.5, $calc['ajusteLinha'], 0.001);
        $this->assertEqualsWithDelta(13.5, $calc['total'], 0.001);
    }

    public function test_quantidade_fracionaria_total_linha(): void
    {
        $calc = DescontoReaisItemCalculator::calcular(
            10.0,
            1.5,
            1.0,
            'desconto',
            'valor',
            EmpresaParametros::DESCONTO_REAIS_ITEM_LINHA,
        );

        $this->assertEqualsWithDelta(1.0, $calc['ajusteLinha'], 0.001);
        $this->assertEqualsWithDelta(14.0, $calc['total'], 0.001);
    }

    public function test_reescala_por_unidade_ao_mudar_quantidade(): void
    {
        $novo = DescontoReaisItemCalculator::reescalarAjusteLinha(
            6.0,
            6.0,
            8.0,
            EmpresaParametros::DESCONTO_REAIS_ITEM_UNITARIO,
        );

        $this->assertEqualsWithDelta(8.0, $novo, 0.001);
    }

    public function test_reescala_total_linha_mantem_valor(): void
    {
        $novo = DescontoReaisItemCalculator::reescalarAjusteLinha(
            1.0,
            6.0,
            8.0,
            EmpresaParametros::DESCONTO_REAIS_ITEM_LINHA,
        );

        $this->assertEqualsWithDelta(1.0, $novo, 0.001);
    }

    public function test_percentual_independe_do_modo_reais(): void
    {
        $unitario = DescontoReaisItemCalculator::calcular(10.0, 6.0, 10.0, 'desconto', 'percentual', 'unitario');
        $linha = DescontoReaisItemCalculator::calcular(10.0, 6.0, 10.0, 'desconto', 'percentual', 'linha');

        $this->assertEqualsWithDelta(6.0, $unitario['ajusteLinha'], 0.001);
        $this->assertEqualsWithDelta(6.0, $linha['ajusteLinha'], 0.001);
        $this->assertEqualsWithDelta(54.0, $unitario['total'], 0.001);
        $this->assertEqualsWithDelta(54.0, $linha['total'], 0.001);
    }

    public function test_agrupamento_unitario_reescala_desconto_existente(): void
    {
        $r = DescontoReaisItemCalculator::consolidarAgrupamento(
            1.0,
            1.0,
            0.0,
            39.0,
            0.0,
            0.0,
            EmpresaParametros::DESCONTO_REAIS_ITEM_UNITARIO,
        );

        $this->assertSame(40.0, $r['quantidade']);
        $this->assertSame(40.0, $r['desconto']);
    }

    public function test_agrupamento_linha_nao_reescala_desconto_existente(): void
    {
        $r = DescontoReaisItemCalculator::consolidarAgrupamento(
            6.0,
            1.0,
            0.0,
            2.0,
            0.0,
            0.0,
            EmpresaParametros::DESCONTO_REAIS_ITEM_LINHA,
        );

        $this->assertSame(8.0, $r['quantidade']);
        $this->assertSame(1.0, $r['desconto']);
    }

    public function test_agrupamento_linha_soma_descontos_das_entradas(): void
    {
        $r = DescontoReaisItemCalculator::consolidarAgrupamento(
            6.0,
            1.0,
            0.0,
            6.0,
            1.0,
            0.0,
            EmpresaParametros::DESCONTO_REAIS_ITEM_LINHA,
        );

        $this->assertSame(12.0, $r['quantidade']);
        $this->assertSame(2.0, $r['desconto']);
    }
}
