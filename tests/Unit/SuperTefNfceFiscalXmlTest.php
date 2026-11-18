<?php

namespace Tests\Unit;

use App\Models\TefIntencao;
use App\Support\Fiscal\PdvNfceFiscalPayloadBuilder;
use ReflectionMethod;
use Tests\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\DestinatarioDto;
use Unitec\FiscalEngine\Dto\EmitenteDto;
use Unitec\FiscalEngine\Dto\EmitirNfceRequest;
use Unitec\FiscalEngine\Dto\IdeDto;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Dto\PagamentoDto;
use Unitec\FiscalEngine\Xml\NfceXmlBuilder;

/**
 * NFC-e + SuperTEF: XML controlado (tpAmb=2), sem transmissão SEFAZ e sem cobrança.
 * Política: cobrança SuperTEF integrada; card fiscal com tpIntegra=2 (sem CNPJ adquirente).
 */
class SuperTefNfceFiscalXmlTest extends TestCase
{
    public function test_xml_supertef_debito_tp_integra_2_com_caut_sem_cnpj(): void
    {
        $xml = $this->xmlComPagamentos([
            new PagamentoDto(
                tipo: '04',
                valor: 25.50,
                tpIntegra: '2',
                tBand: '02',
                cAut: 'AUTHDEB',
                cnpjCredenciadora: null,
            ),
        ]);

        $this->assertStringContainsString('<tPag>04</tPag>', $xml);
        $this->assertStringContainsString('<tpIntegra>2</tpIntegra>', $xml);
        $this->assertStringContainsString('<tBand>02</tBand>', $xml);
        $this->assertStringContainsString('<cAut>AUTHDEB</cAut>', $xml);
        $this->assertStringContainsString('<vPag>25.50</vPag>', $xml);
        $this->assertStringNotContainsString('<card><tpIntegra>2</tpIntegra><CNPJ>', $xml);
    }

    public function test_xml_supertef_credito_avista_e_parcelado(): void
    {
        $xmlVista = $this->xmlComPagamentos([
            new PagamentoDto('03', 10.00, '2', '01', 'AV1', null),
        ]);
        $this->assertStringContainsString('<tPag>03</tPag>', $xmlVista);
        $this->assertStringContainsString('<tpIntegra>2</tpIntegra>', $xmlVista);
        $this->assertStringContainsString('<cAut>AV1</cAut>', $xmlVista);

        $xmlParc = $this->xmlComPagamentos([
            new PagamentoDto('03', 90.00, '2', '06', 'P3X', null),
        ]);
        $this->assertStringContainsString('<tPag>03</tPag>', $xmlParc);
        $this->assertStringContainsString('<cAut>P3X</cAut>', $xmlParc);
        $this->assertStringContainsString('<tBand>06</tBand>', $xmlParc);
    }

    public function test_xml_pagamento_misto_nao_exige_cnpj_no_cartao(): void
    {
        $xml = $this->xmlComPagamentos([
            new PagamentoDto('01', 10.00),
            new PagamentoDto('03', 40.00, '2', '01', 'MIX1', null),
            new PagamentoDto('20', 5.00),
        ], valorNota: 55.00);

        $this->assertSame(1, substr_count($xml, '<tpIntegra>2</tpIntegra>'));
        $this->assertSame(1, substr_count($xml, '<tPag>01</tPag>'));
        $this->assertSame(1, substr_count($xml, '<tPag>03</tPag>'));
        $this->assertSame(1, substr_count($xml, '<tPag>20</tPag>'));
        $this->assertStringContainsString('<cAut>MIX1</cAut>', $xml);
        $this->assertSame(1, substr_count($xml, '<card>'));
    }

    public function test_pos_manual_continua_tp_integra_2_sem_exigir_cnpj(): void
    {
        $xml = $this->xmlComPagamentos([
            new PagamentoDto('03', 15.00, '2', '01', 'POSAUT', null),
        ]);

        $this->assertStringContainsString('<tpIntegra>2</tpIntegra>', $xml);
        $this->assertStringContainsString('<cAut>POSAUT</cAut>', $xml);
        $this->assertStringContainsString('<card><tpIntegra>2</tpIntegra><tBand>01</tBand><cAut>POSAUT</cAut></card>', $xml);
        $this->assertStringNotContainsString('<card><tpIntegra>2</tpIntegra><CNPJ>', $xml);
    }

    /**
     * POS e SuperTEF no XML fiscal ficam equivalentes (tpIntegra=2, sem CNPJ).
     * A diferença fica só na cobrança (SuperTEF integrado via API).
     */
    public function test_diferenca_xml_pos_manual_vs_supertef_mesmo_card_fiscal(): void
    {
        $xmlPos = $this->xmlComPagamentos([
            new PagamentoDto('03', 10.00, '2', '01', 'POS123', null),
        ]);
        $xmlTef = $this->xmlComPagamentos([
            new PagamentoDto('03', 10.00, '2', '01', 'TEF456', null),
        ]);

        preg_match('#<card>[\s\S]*?</card>#', $xmlPos, $mPos);
        preg_match('#<card>[\s\S]*?</card>#', $xmlTef, $mTef);

        $this->assertSame(
            '<card><tpIntegra>2</tpIntegra><tBand>01</tBand><cAut>POS123</cAut></card>',
            $mPos[0] ?? '',
        );
        $this->assertSame(
            '<card><tpIntegra>2</tpIntegra><tBand>01</tBand><cAut>TEF456</cAut></card>',
            $mTef[0] ?? '',
        );
        $this->assertStringNotContainsString('<tpIntegra>1</tpIntegra>', $xmlPos);
        $this->assertStringNotContainsString('<tpIntegra>1</tpIntegra>', $xmlTef);
        $this->assertStringNotContainsString('<CNPJ>01027058000191</CNPJ>', $xmlTef);
    }

    public function test_map_tipo_pagamento_supertef_credito_debito(): void
    {
        $this->ensureFormasETefTables();

        $builder = new PdvNfceFiscalPayloadBuilder();
        $m = new ReflectionMethod(PdvNfceFiscalPayloadBuilder::class, 'mapTipoPagamento');
        $m->setAccessible(true);

        $this->assertSame('03', $m->invoke($builder, 'SUPERTEF CREDITO'));
        $this->assertSame('04', $m->invoke($builder, 'SUPERTEF DEBITO'));
        $this->assertSame('01', $m->invoke($builder, 'DINHEIRO'));
        $this->assertSame('20', $m->invoke($builder, 'PIX'));
    }

    public function test_acquirer_cnpj_fiscal_coluna_e_fallback_raw(): void
    {
        $comColuna = new TefIntencao([
            'acquirer_cnpj' => '01.027.058/0001-91',
            'raw_last' => null,
        ]);
        $this->assertSame('01027058000191', $comColuna->acquirerCnpjFiscal());

        $soRaw = new TefIntencao([
            'acquirer_cnpj' => null,
            'raw_last' => [
                'payment_data' => ['acquirer_cnpj' => '11.222.333/0001-81'],
            ],
        ]);
        $this->assertSame('11222333000181', $soRaw->acquirerCnpjFiscal());

        $invalido = new TefIntencao([
            'acquirer_cnpj' => '123',
            'raw_last' => ['payment_data' => ['acquirer_cnpj' => 'abc']],
        ]);
        $this->assertNull($invalido->acquirerCnpjFiscal());

        $dvInvalido = new TefIntencao([
            'acquirer_cnpj' => '03106213000100',
            'raw_last' => null,
        ]);
        $this->assertNull($dvInvalido->acquirerCnpjFiscal());
        $this->assertFalse(TefIntencao::isAcquirerCnpjValido('03106213000100'));
        $this->assertTrue(TefIntencao::isAcquirerCnpjValido('01027058000191'));
    }

    public function test_emulador_windows_sem_cnpj_nao_bloqueia_mais_transmissao(): void
    {
        $this->ensureFormasETefTables();

        $builder = new PdvNfceFiscalPayloadBuilder();

        $vendaTef = new \App\Models\PdvVenda(['id' => 900024]);
        $vendaTef->id = 900024;
        $vendaTef->setRelation('pagamentos', collect([
            new \App\Models\PdvVendaPagamento([
                'id' => 1,
                'forma' => 'SUPERTEF CREDITO',
                'valor' => 1.00,
                'cartao_maquininha' => 'SUPERTEF POS 5665',
                'cartao_autorizacao' => '90552',
                'cartao_bandeira' => 'VISA',
            ]),
        ]));

        $this->assertNull($builder->impedimentoTransmissaoCartaoSuperTef($vendaTef));
        $builder->assertCartaoIntegradoSuperTefCompleto($vendaTef);

        $vendaPos = new \App\Models\PdvVenda(['id' => 900026]);
        $vendaPos->id = 900026;
        $vendaPos->setRelation('pagamentos', collect([
            new \App\Models\PdvVendaPagamento([
                'id' => 3,
                'forma' => 'CARTAO CREDITO',
                'valor' => 1.00,
                'cartao_maquininha' => 'REDE',
                'cartao_autorizacao' => 'POSAUT',
                'cartao_bandeira' => 'MASTERCARD',
            ]),
        ]));

        $this->assertNull($builder->impedimentoTransmissaoCartaoSuperTef($vendaPos));
    }

    public function test_assert_nao_bloqueia_pos_manual_sem_cnpj(): void
    {
        $this->ensureFormasETefTables();

        $venda = new \App\Models\PdvVenda(['id' => 900025]);
        $venda->id = 900025;
        $venda->setRelation('pagamentos', collect([
            new \App\Models\PdvVendaPagamento([
                'id' => 2,
                'forma' => 'CARTAO CREDITO',
                'valor' => 15.00,
                'cartao_maquininha' => 'REDE',
                'cartao_autorizacao' => 'POSAUT',
                'cartao_bandeira' => 'VISA',
            ]),
        ]));

        (new PdvNfceFiscalPayloadBuilder())->assertCartaoIntegradoSuperTefCompleto($venda);
        $this->assertTrue(true);
    }

    private function ensureFormasETefTables(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('formas_pagamento')) {
            \Illuminate\Support\Facades\Schema::create('formas_pagamento', function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id();
                $table->string('descricao')->nullable();
                $table->string('tipo_movimento')->nullable();
            });
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('tef_intencoes')) {
            \Illuminate\Support\Facades\Schema::create('tef_intencoes', function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('pdv_venda_id')->nullable();
                $table->unsignedBigInteger('pdv_venda_pagamento_id')->nullable();
                $table->string('status')->nullable();
                $table->string('autorizacao')->nullable();
                $table->string('bandeira')->nullable();
                $table->string('nsu')->nullable();
                $table->string('acquirer_cnpj', 14)->nullable();
                $table->json('raw_last')->nullable();
                $table->string('payment_uniqueid')->nullable();
                $table->unsignedBigInteger('pos_id')->nullable();
            });
        }
    }

    /**
     * @param  list<PagamentoDto>  $pagamentos
     */
    private function xmlComPagamentos(array $pagamentos, float $valorNota = 0.0): string
    {
        if ($valorNota <= 0) {
            $valorNota = array_sum(array_map(static fn (PagamentoDto $p): float => $p->valor, $pagamentos));
        }

        $builder = new NfceXmlBuilder();
        $request = new EmitirNfceRequest(
            certificate: new Certificate('key', 'cert', '22469772000100'),
            emitente: new EmitenteDto(
                cnpj: '22469772000100',
                razaoSocial: 'EMPRESA TESTE LTDA',
                nomeFantasia: 'EMPRESA TESTE',
                ie: '255000000',
                crt: 1,
                logradouro: 'RUA TESTE',
                numero: '100',
                bairro: 'CENTRO',
                codigoMunicipio: '4205407',
                municipio: 'FLORIANOPOLIS',
                uf: 'SC',
                cep: '88000000',
            ),
            ide: new IdeDto(
                serie: 1,
                numero: 99,
                cNf: 11223344,
                tpAmb: 2,
                tpEmis: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-11-12T10:00:00-03:00'),
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '1',
                    descricao: 'PRODUTO SUPERTEF',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: $valorNota,
                    valorTotal: $valorNota,
                    imposto: new ItemImpostoDto(
                        origem: 0,
                        csosn: '102',
                        cstPis: '49',
                        cstCofins: '49',
                    ),
                ),
            ],
            pagamentos: $pagamentos,
            valorProdutos: $valorNota,
            valorNota: $valorNota,
            destinatario: new DestinatarioDto(cpf: '12345678909'),
            idToken: '1',
            csc: 'FF1BFD3B-29D5-48F2-B472-5E76C281D283',
            homologacao: true,
        );

        $built = $builder->build($request);

        return $built['dom']->saveXML() ?: '';
    }
}
