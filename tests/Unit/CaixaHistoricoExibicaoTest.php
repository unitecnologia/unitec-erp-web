<?php

namespace Tests\Unit;

use App\Support\Erp\CaixaHistoricoExibicao;
use Tests\TestCase;

class CaixaHistoricoExibicaoTest extends TestCase
{
    public function test_tela_venda_usa_numero_do_pedido_nao_id_fv(): void
    {
        $mapa = [12 => '25'];

        $this->assertSame(
            'TV 25 (DINHEIRO)',
            CaixaHistoricoExibicao::paraExibicao('VENDA ERP FV (DINHEIRO) [FV-12] #P1', $mapa),
        );
        $this->assertSame(
            'TV 25 (PIX)',
            CaixaHistoricoExibicao::paraExibicao('VENDA ERP FV (PIX) [FV-12] #P2', $mapa),
        );
        $this->assertSame(
            'TV 25 (POS DÉBITO)',
            CaixaHistoricoExibicao::paraExibicao('VENDA ERP FV (POS DEBITO) [FV-12] #P3', $mapa),
        );
    }

    public function test_sem_mapa_cai_no_id_fv(): void
    {
        $this->assertSame(
            'TV 12 (DINHEIRO)',
            CaixaHistoricoExibicao::paraExibicao('VENDA ERP FV (DINHEIRO) [FV-12] #P1'),
        );
    }

    public function test_app_forca_vendas_usa_fv_com_numero_pedido(): void
    {
        $this->assertSame(
            'FV 90 (PIX)',
            CaixaHistoricoExibicao::paraExibicao('VENDA APP FV (PIX) [FV-83]', [83 => '90']),
        );
        $this->assertSame(
            'FV 83 (PIX)',
            CaixaHistoricoExibicao::paraExibicao('VENDA APP FV (PIX) [FV-83]'),
        );
    }

    public function test_estorno_preserva_prefixo(): void
    {
        $this->assertSame(
            'ESTORNO TV 25 (PIX)',
            CaixaHistoricoExibicao::paraExibicao('ESTORNO VENDA ERP FV (PIX) [FV-12] #P2', [12 => '25']),
        );
    }

    public function test_os_e_pdv_inalterados(): void
    {
        $this->assertSame(
            'OS 74 (PIX)',
            CaixaHistoricoExibicao::paraExibicao('OS 74 (PIX)'),
        );
        $this->assertSame(
            'VENDA PDV #000012 - DINHEIRO',
            CaixaHistoricoExibicao::paraExibicao('VENDA PDV #000012 - DINHEIRO'),
        );
    }

    public function test_preserva_sufixo_sessao_pdv(): void
    {
        $base = 'VENDA ERP FV (DINHEIRO) [FV-12] #P1 · CX 5 · OPERADOR';
        $this->assertSame(
            'TV 25 (DINHEIRO) · CX 5 · OPERADOR',
            CaixaHistoricoExibicao::paraExibicao($base, [12 => '25']),
        );
    }
}
