<?php

namespace Tests\Unit;

use App\Support\Erp\Financeiro\ComissaoBasePagamento;
use Tests\TestCase;

class ComissaoBasePagamentoTest extends TestCase
{
    public function test_base_exclui_meio_marcado_e_preserva_demais(): void
    {
        $excluidas = ['VALE' => true];

        $this->assertSame(0.0, ComissaoBasePagamento::baseComissionavel(
            100.0,
            'VALE',
            null,
            $excluidas,
        ));

        $this->assertSame(100.0, ComissaoBasePagamento::baseComissionavel(
            100.0,
            'DINHEIRO',
            null,
            $excluidas,
        ));

        $this->assertSame(70.0, ComissaoBasePagamento::baseComissionavel(
            100.0,
            'DINHEIRO / VALE',
            [
                ['forma' => 'DINHEIRO', 'valor' => 70.0],
                ['forma' => 'VALE', 'valor' => 30.0],
            ],
            $excluidas,
        ));
    }

    public function test_misto_sem_parcelas_nao_exclui_por_chute(): void
    {
        $this->assertSame(100.0, ComissaoBasePagamento::baseComissionavel(
            100.0,
            'DINHEIRO / VALE',
            null,
            ['VALE' => true],
        ));
    }

    public function test_forma_com_canhoto_ainda_bate_no_cadastro(): void
    {
        $this->assertTrue(ComissaoBasePagamento::formaExcluiComissao(
            'VALE (NSU 123)',
            ['VALE' => true],
        ));
        $this->assertFalse(ComissaoBasePagamento::formaExcluiComissao(
            'PIX',
            ['VALE' => true],
        ));
    }

    public function test_match_por_descricao_e_exato_nao_por_substring(): void
    {
        $excluidas = ['BOLETO 7' => true];

        $this->assertTrue(ComissaoBasePagamento::formaExcluiComissao('BOLETO 7', $excluidas));
        $this->assertFalse(ComissaoBasePagamento::formaExcluiComissao('BOLETO 7/14', $excluidas));
        $this->assertFalse(ComissaoBasePagamento::formaExcluiComissao('BOLETO', $excluidas));
        $this->assertFalse(ComissaoBasePagamento::formaExcluiComissao('DINHEIRO / BOLETO 7', $excluidas));
    }

    public function test_adicional_proporcional_a_base_comissionavel(): void
    {
        $this->assertSame(0.0, ComissaoBasePagamento::adicionalProporcional(10.0, 0.0, 100.0));
        $this->assertSame(10.0, ComissaoBasePagamento::adicionalProporcional(10.0, 100.0, 100.0));
        $this->assertSame(6.0, ComissaoBasePagamento::adicionalProporcional(10.0, 60.0, 100.0));
    }

    public function test_sem_formas_excluidas_preserva_total(): void
    {
        $this->assertSame(150.0, ComissaoBasePagamento::baseComissionavel(
            150.0,
            'PIX',
            [['forma' => 'PIX', 'valor' => 150.0]],
            [],
        ));
    }
}
