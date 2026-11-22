<?php

namespace Tests\Unit;

use App\Support\Erp\Nfce\NfceRegularizacaoPagamentos;
use App\Support\Fiscal\FormaPagamentoNfceTPagMap;
use DomainException;
use PHPUnit\Framework\TestCase;

class NfceRegularizacaoPagamentosTest extends TestCase
{
    public function test_dinheiro_detalhe_ignora_cabecalho_diversos(): void
    {
        $linhas = NfceRegularizacaoPagamentos::resolverLinhas(
            [['forma' => 'DINHEIRO', 'valor' => 335.40]],
            null,
            'DIVERSOS',
        );

        $this->assertSame('DINHEIRO', NfceRegularizacaoPagamentos::rotuloGrade($linhas));
        $this->assertSame(['01'], NfceRegularizacaoPagamentos::tPags(
            NfceRegularizacaoPagamentos::garantirParaEmissao(
                [['forma' => 'DINHEIRO', 'valor' => 335.40]],
                null,
                'DIVERSOS',
                335.40,
                '56305',
            )
        ));
    }

    public function test_cartao_credito(): void
    {
        $linhas = NfceRegularizacaoPagamentos::resolverLinhas(
            [['forma' => 'CARTAO CREDITO', 'valor' => 150.70]],
            null,
            'DIVERSOS',
        );

        $this->assertSame('CARTAO CREDITO', NfceRegularizacaoPagamentos::rotuloGrade($linhas));
        $this->assertSame(
            '03',
            FormaPagamentoNfceTPagMap::fromDescricao($linhas[0]['forma']),
        );
    }

    public function test_cartao_debito(): void
    {
        $linhas = NfceRegularizacaoPagamentos::resolverLinhas(
            [['forma' => 'CARTAO DEBITO', 'valor' => 83.90]],
            null,
            'DIVERSOS',
        );

        $this->assertSame('CARTAO DEBITO', NfceRegularizacaoPagamentos::rotuloGrade($linhas));
        $this->assertSame('04', FormaPagamentoNfceTPagMap::fromDescricao($linhas[0]['forma']));
    }

    public function test_pix(): void
    {
        $linhas = NfceRegularizacaoPagamentos::resolverLinhas(
            [['forma' => 'PIX', 'valor' => 50.00]],
            null,
            'DIVERSOS',
        );

        $this->assertSame('PIX', NfceRegularizacaoPagamentos::rotuloGrade($linhas));
        $this->assertSame(['20'], NfceRegularizacaoPagamentos::tPags(
            NfceRegularizacaoPagamentos::garantirParaEmissao(
                [['forma' => 'PIX', 'valor' => 50.00]],
                null,
                'DIVERSOS',
                50.00,
            )
        ));
    }

    public function test_pagamento_misto_preserva_valores(): void
    {
        $detalhe = [
            ['forma' => 'DINHEIRO', 'valor' => 40.00],
            ['forma' => 'CARTAO CREDITO', 'valor' => 60.00],
        ];

        $linhas = NfceRegularizacaoPagamentos::resolverLinhas($detalhe, null, 'DIVERSOS');
        $this->assertSame('DINHEIRO / CARTAO CREDITO', NfceRegularizacaoPagamentos::rotuloGrade($linhas));

        $emissao = NfceRegularizacaoPagamentos::garantirParaEmissao($detalhe, null, 'DIVERSOS', 100.00);
        $this->assertSame([
            ['forma' => 'DINHEIRO', 'valor' => 40.00],
            ['forma' => 'CARTAO CREDITO', 'valor' => 60.00],
        ], $emissao);
        $this->assertSame(['01', '03'], NfceRegularizacaoPagamentos::tPags($emissao));

        $tooltip = NfceRegularizacaoPagamentos::tooltipGrade($linhas);
        $this->assertStringContainsString('DINHEIRO — R$ 40,00', $tooltip);
        $this->assertStringContainsString('CARTAO CREDITO — R$ 60,00', $tooltip);
    }

    public function test_fv_payload_quando_sem_pdv(): void
    {
        $fv = [
            ['descricao' => 'PIX', 'valor' => 25],
            ['descricao' => 'DINHEIRO', 'valor' => 10],
        ];

        $linhas = NfceRegularizacaoPagamentos::resolverLinhas(null, $fv, 'DIVERSOS');
        $this->assertSame('PIX / DINHEIRO', NfceRegularizacaoPagamentos::rotuloGrade($linhas));
    }

    public function test_cabecalho_utilizavel_sem_detalhe(): void
    {
        $linhas = NfceRegularizacaoPagamentos::resolverLinhas(null, null, 'DINHEIRO');
        $this->assertSame('DINHEIRO', NfceRegularizacaoPagamentos::rotuloGrade($linhas));

        $emissao = NfceRegularizacaoPagamentos::garantirParaEmissao(null, null, 'DINHEIRO', 99.90);
        $this->assertSame([['forma' => 'DINHEIRO', 'valor' => 99.90]], $emissao);
    }

    public function test_diversos_sem_detalhe_exibe_traco_e_bloqueia_emissao(): void
    {
        $linhas = NfceRegularizacaoPagamentos::resolverLinhas(null, null, 'DIVERSOS');
        $this->assertSame([], $linhas);
        $this->assertSame('—', NfceRegularizacaoPagamentos::rotuloGrade($linhas));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('DIVERSOS');

        NfceRegularizacaoPagamentos::garantirParaEmissao(null, null, 'DIVERSOS', 100.00, '56255');
    }

    public function test_misto_sem_valores_bloqueia_emissao(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('valores detalhados');

        NfceRegularizacaoPagamentos::garantirParaEmissao(
            null,
            null,
            'DINHEIRO / PIX',
            100.00,
        );
    }

    public function test_soma_divergente_bloqueia(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('diverge');

        NfceRegularizacaoPagamentos::garantirParaEmissao(
            [
                ['forma' => 'DINHEIRO', 'valor' => 10],
                ['forma' => 'PIX', 'valor' => 10],
            ],
            null,
            'DIVERSOS',
            50.00,
        );
    }

    public function test_diversos_como_linha_de_pagamento_e_ignorado(): void
    {
        $linhas = NfceRegularizacaoPagamentos::resolverLinhas(
            [['forma' => 'DIVERSOS', 'valor' => 100]],
            null,
            'DIVERSOS',
        );

        $this->assertSame([], $linhas);
        $this->assertTrue(NfceRegularizacaoPagamentos::ehRotuloInutilizavel('DIVERSOS'));
        $this->assertSame('99', FormaPagamentoNfceTPagMap::fromDescricao('DIVERSOS'));
    }

    public function test_amostras_scv_563xx(): void
    {
        $amostras = [
            '56255' => 3668.10,
            '56300' => 42.00,
            '56302' => 675.00,
            '56304' => 136.00,
            '56305' => 335.40,
        ];

        foreach ($amostras as $numero => $total) {
            $emissao = NfceRegularizacaoPagamentos::garantirParaEmissao(
                [['forma' => 'DINHEIRO', 'valor' => $total]],
                null,
                'DIVERSOS',
                $total,
                (string) $numero,
            );

            $this->assertSame([['forma' => 'DINHEIRO', 'valor' => $total]], $emissao);
            $this->assertSame(['01'], NfceRegularizacaoPagamentos::tPags($emissao));
            $this->assertSame(
                'DINHEIRO',
                NfceRegularizacaoPagamentos::rotuloGrade(
                    NfceRegularizacaoPagamentos::resolverLinhas(
                        [['forma' => 'DINHEIRO', 'valor' => $total]],
                        null,
                        'DIVERSOS',
                    )
                ),
            );
        }
    }
}
