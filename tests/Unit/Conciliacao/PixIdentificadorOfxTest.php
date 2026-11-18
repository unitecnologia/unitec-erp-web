<?php

namespace Tests\Unit\Conciliacao;

use App\Support\Erp\Financeiro\Conciliacao\PixIdentificadorOfx;
use Tests\TestCase;

class PixIdentificadorOfxTest extends TestCase
{
    public function test_fitid_hex32_nao_equivale_txid_ailos(): void
    {
        $fitid = '42B2E682774B47DAE19DBE6D3B0DB10A';
        $txid = 'AILOSREF00001'.str_repeat('A', 20);

        $this->assertTrue(PixIdentificadorOfx::looksLikeOfxFitidHex32($fitid));
        $this->assertTrue(PixIdentificadorOfx::looksLikeAilosTxid($txid));
        $this->assertFalse(PixIdentificadorOfx::fitidEquivaleTxid($fitid, $txid));
    }

    public function test_extrai_txid_ailos_do_historico(): void
    {
        $txid = 'AILOSABC12345'.str_repeat('B', 20);
        $lista = PixIdentificadorOfx::extrairTxidsDoTexto('CREDITO PIX '.$txid.' OK');

        $this->assertSame([$txid], $lista);
    }

    public function test_historico_parece_pix(): void
    {
        $this->assertTrue(PixIdentificadorOfx::historicoParecePix('CREDITO PIX - JOAO'));
        $this->assertFalse(PixIdentificadorOfx::historicoParecePix('TARIFA BANCARIA'));
    }

    public function test_nao_extrai_fitid_hex32_como_identificador(): void
    {
        $fitid = '96E259774A94383687F1632F6FB09ACB';
        $extrato = (object) [
            'historico' => 'CREDITO PIX - ALENCAR DE OLIVEIRA',
            'memo' => 'CREDITO PIX - ALENCAR DE OLIVEIRA',
            'payee' => null,
            'ref_num' => null,
            'fitid' => $fitid,
        ];

        $this->assertSame([], PixIdentificadorOfx::extrairIdentificadoresDoExtrato($extrato));
        $this->assertFalse(PixIdentificadorOfx::fitidEquivaleTxid(
            $fitid,
            'AILOSPDV501M75EF9ADEA666C5D1EE13B',
        ));
    }

    public function test_end_to_end_nao_hex_e_extraido_do_texto(): void
    {
        $e2e = 'E1055875020261116ABCDXYZ78901234';
        $this->assertFalse(PixIdentificadorOfx::looksLikeOfxFitidHex32($e2e));
        $this->assertSame([$e2e], PixIdentificadorOfx::extrairIdentificadoresPix('PIX '.$e2e));
    }
}
