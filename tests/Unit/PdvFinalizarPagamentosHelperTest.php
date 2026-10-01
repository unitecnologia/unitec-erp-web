<?php

namespace Tests\Unit;

use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use PHPUnit\Framework\TestCase;

class PdvFinalizarPagamentosHelperTest extends TestCase
{
    public function test_aplica_crediario_exclusivo_zerando_dinheiro_padrao(): void
    {
        $pagamentos = [
            ['forma' => 'DINHEIRO', 'atalho' => 'A', 'valor' => '2.621,00'],
            ['forma' => 'PIX', 'atalho' => 'P', 'valor' => '0,00'],
            ['forma' => 'POS DEBITO', 'atalho' => 'D', 'valor' => '0,00'],
            ['forma' => 'POS CREDITO', 'atalho' => 'C', 'valor' => '0,00'],
            ['forma' => 'CREDIÁRIO', 'atalho' => 'R', 'valor' => '0,00'],
            ['forma' => 'CHEQUE', 'atalho' => 'H', 'valor' => '0,00'],
        ];

        $resultado = PdvFinalizarPagamentosHelper::aplicarFormaPrazoExclusiva($pagamentos, 4, 2621.00);

        $this->assertSame('0,00', $resultado[0]['valor']);
        $this->assertSame('2.621,00', $resultado[4]['valor']);
    }

    public function test_pix_gerar_qrcode_pdv_so_quando_flag_e_pix(): void
    {
        $this->assertTrue(PdvFinalizarPagamentosHelper::isFormaPixGerarQrcodePdv([
            'forma' => 'PIX',
            'tipo' => 'pix',
            'gerar_qrcode_pdv' => true,
        ]));

        $this->assertFalse(PdvFinalizarPagamentosHelper::isFormaPixGerarQrcodePdv([
            'forma' => 'PIX',
            'tipo' => 'pix',
            'gerar_qrcode_pdv' => false,
        ]));

        $this->assertFalse(PdvFinalizarPagamentosHelper::isFormaPixGerarQrcodePdv([
            'forma' => 'DINHEIRO',
            'tipo' => 'dinheiro',
            'gerar_qrcode_pdv' => true,
        ]));
    }

    public function test_pos_credito_nao_e_forma_a_prazo(): void
    {
        $this->assertFalse(PdvFinalizarPagamentosHelper::isFormaAPrazo('POS CREDITO'));
        $this->assertTrue(PdvFinalizarPagamentosHelper::isFormaAPrazo('CREDIÁRIO'));
    }

    public function test_pos_credito_com_aparece_contas_receber_e_cartao_cr(): void
    {
        $this->assertTrue(PdvFinalizarPagamentosHelper::isFormaCartaoContasReceber([
            'forma' => 'POS CREDITO',
            'tipo' => 'cartao_credito',
            'aparece_contas_receber' => true,
        ]));

        $this->assertFalse(PdvFinalizarPagamentosHelper::isFormaCartaoContasReceber([
            'forma' => 'POS CREDITO',
            'tipo' => 'cartao_credito',
            'aparece_contas_receber' => false,
        ]));
    }

    public function test_prazo_financeiro_valido_exclui_default_1x30(): void
    {
        $this->assertFalse(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(1, 30));
        $this->assertFalse(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(1, 0));
        $this->assertFalse(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(0, 14));
        $this->assertTrue(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(1, 14));
        $this->assertTrue(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(3, 30));
        $this->assertTrue(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(4, 15));
    }

    public function test_prazo_financeiro_respeita_modo_prazo(): void
    {
        // modo financeiro: 1×30 passa a ser válido (intencional).
        $this->assertTrue(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(1, 30, 'financeiro'));
        $this->assertTrue(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(1, 7, 'financeiro'));
        $this->assertTrue(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(3, 30, 'financeiro'));

        // modo tabela: ignora max/intervalo mesmo "válidos" na heurística.
        $this->assertFalse(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(1, 7, 'tabela'));
        $this->assertFalse(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(3, 30, 'tabela'));
        $this->assertFalse(PdvFinalizarPagamentosHelper::isPrazoFinanceiroValido(1, 30, 'tabela'));
    }

    public function test_dias_de_prazo_financeiro(): void
    {
        $this->assertSame([14], PdvFinalizarPagamentosHelper::diasDePrazoFinanceiro(1, 14));
        $this->assertSame([30, 60, 90], PdvFinalizarPagamentosHelper::diasDePrazoFinanceiro(3, 30));
        $this->assertSame([15, 30, 45, 60], PdvFinalizarPagamentosHelper::diasDePrazoFinanceiro(4, 15));
    }

    public function test_resolver_dias_carne_prioridade_casos(): void
    {
        // Caso 1: cliente com 30,60,90 + forma BOLETO 14 → tabela do cliente.
        $this->assertSame(
            [30, 60, 90],
            PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade([30, 60, 90], 1, 14),
        );

        // Caso 2: sem tabela + max=1 intervalo=14 → [14].
        $this->assertSame(
            [14],
            PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade(null, 1, 14),
        );

        // Caso 3: sem tabela + max=3 intervalo=30 → [30,60,90].
        $this->assertSame(
            [30, 60, 90],
            PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade([], 3, 30),
        );

        // Caso 4: cliente com 14 + forma 3x30 → tabela do cliente.
        $this->assertSame(
            [14],
            PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade([14], 3, 30),
        );

        // Caso 5: sem tabela + default 1x30 → null (fallback atual).
        $this->assertNull(
            PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade(null, 1, 30),
        );

        // Caso 5b: sem tabela + intervalo 0 → null.
        $this->assertNull(
            PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade(null, 1, 0),
        );

        // Caso 6: modo tabela + 1×7 antigo → null (não aplica financeiro).
        $this->assertNull(
            PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade(null, 1, 7, 'tabela'),
        );

        // Caso 7: modo financeiro + 1×30 → [30].
        $this->assertSame(
            [30],
            PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade(null, 1, 30, 'financeiro'),
        );

        // Caso 8: cliente ainda ganha mesmo com modo financeiro.
        $this->assertSame(
            [14, 28],
            PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade([14, 28], 3, 30, 'financeiro'),
        );
    }
}
