<?php

namespace Tests\Unit;

use App\Support\Erp\Ccg\CcgConsGtinClient;
use App\Support\Erp\Ccg\CcgConsGtinEndpoints;
use App\Support\Erp\Ccg\CcgConsultaException;
use App\Support\Erp\Ccg\CcgSoapTransport;
use App\Support\Erp\Ccg\Gtin;
use App\Support\Erp\EmpresaParametros;
use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;

class CcgConsGtinClientTest extends TestCase
{
    public function test_gtin_7896188401189_tem_digito_valido_e_prefixo_brasil(): void
    {
        $gtin = '7896188401189';

        $this->assertTrue(Gtin::isAcceptedLength($gtin));
        $this->assertTrue(Gtin::hasValidCheckDigit($gtin));
        $this->assertTrue(Gtin::isPrefixBrasil($gtin));
        $this->assertFalse(Gtin::hasValidCheckDigit('7896188401180'));
        $this->assertFalse(Gtin::isAcceptedLength('78961884011'));
        $this->assertTrue(Gtin::isPrefixBrasil('17896188401186'));
        $this->assertFalse(Gtin::isPrefixBrasil('4006381333931'));
    }

    public function test_envelope_e_soap_12_sem_credencial_cosmos(): void
    {
        $envelope = (new CcgConsGtinClient())->buildEnvelope('7896188401189');

        $this->assertSame(
            '<soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">'
            . '<soap12:Body>'
            . '<ccgConsGTIN xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/ccgConsGtin">'
            . '<nfeDadosMsg>'
            . '<consGTIN xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00">'
            . '<GTIN>7896188401189</GTIN>'
            . '</consGTIN>'
            . '</nfeDadosMsg>'
            . '</ccgConsGTIN>'
            . '</soap12:Body>'
            . '</soap12:Envelope>',
            $envelope,
        );
        $this->assertSame(
            'http://www.portalfiscal.inf.br/nfe/wsdl/ccgConsGtin/ccgConsGTIN',
            CcgConsGtinEndpoints::SOAP_ACTION,
        );
        $this->assertStringNotContainsString('ccgConsGTIN/ccgConsGTIN', CcgConsGtinEndpoints::WSDL_NS);
    }

    public function test_digito_invalido_nao_dispara_transporte(): void
    {
        $transport = new class implements CcgSoapTransport
        {
            public int $calls = 0;

            public function post(string $url, string $envelope, Certificate $certificate, int $timeoutSeconds): string
            {
                $this->calls++;

                return '';
            }
        };

        $client = new CcgConsGtinClient($transport);

        try {
            $client->consultar('7896188401180', new Certificate('k', 'c', '00000000000000'), CcgConsGtinEndpoints::URL, 10);
            $this->fail('Deveria rejeitar o dígito antes da consulta.');
        } catch (CcgConsultaException $exception) {
            $this->assertSame(CcgConsultaException::REJEICAO, $exception->tipo);
            $this->assertStringContainsString('9491', $exception->getMessage());
        }

        $this->assertSame(0, $transport->calls);
    }

    public function test_sucesso_9490_expoe_gtin_tipo_descricao_ncm_e_cest(): void
    {
        $client = new CcgConsGtinClient($this->transport($this->resposta(
            '9490',
            'Consulta realizada com sucesso',
            <<<'XML'
            <GTIN>7896188401189</GTIN>
            <tpGTIN>13</tpGTIN>
            <xProd>ACUCAR CRISTAL 1KG</xProd>
            <NCM>17019900</NCM>
            <CEST>1709601</CEST>
            <CEST>1709600</CEST>
            XML,
        )));

        $result = $client->consultar(
            '7896188401189',
            new Certificate('k', 'c', '00000000000000'),
            CcgConsGtinEndpoints::URL,
            10,
        );

        $this->assertTrue($result->sucesso());
        $this->assertSame('7896188401189', $result->gtin);
        $this->assertSame('13', $result->tpGtin);
        $this->assertSame('ACUCAR CRISTAL 1KG', $result->xProd);
        $this->assertSame('17019900', $result->ncm);
        $this->assertSame(['1709601', '1709600'], $result->cests);
    }

    public function test_rejeicoes_9491_a_9498_e_656(): void
    {
        $casos = [
            '9491' => 'Rejeição: GTIN com dígito verificador inválido',
            '9492' => 'Rejeição: GTIN não possui prefixo 789 ou 790 (Brasil)',
            '9493' => 'Rejeição: CNPJ/CPF do Certificado de Transmissão não é emitente de NF-e ou NFC-e',
            '9494' => 'Rejeição: GTIN inexistente no Cadastro Centralizado de GTIN (CCG)',
            '9495' => 'Rejeição: GTIN existe no CCG com situação inválida',
            '9496' => 'Rejeição: GTIN existe no CCG, mas o dono da marca não autorizou a publicação das informações',
            '9497' => 'Rejeição: GTIN existe no CCG com NCM não informado',
            '9498' => 'Rejeição: GTIN existe no CCG com NCM inválido',
            '656' => 'Rejeição: Consumo Indevido',
        ];

        foreach ($casos as $cStat => $motivo) {
            $client = new CcgConsGtinClient($this->transport($this->resposta($cStat, $motivo, '<GTIN>7896188401189</GTIN>')));

            try {
                $client->consultar('7896188401189', new Certificate('k', 'c', '00000000000000'), CcgConsGtinEndpoints::URL, 5);
                $this->fail('cStat ' . $cStat . ' deveria rejeitar.');
            } catch (CcgConsultaException $exception) {
                $this->assertStringContainsString((string) $cStat, $exception->getMessage());
                $this->assertStringContainsString($motivo, $exception->getMessage());
            }
        }
    }

    public function test_url_cosmos_volta_para_o_endpoint_svrs_e_serper_permanece(): void
    {
        $this->assertSame(
            CcgConsGtinEndpoints::URL,
            EmpresaParametros::normalizeCcgConsGtinUrl('https://api.cosmos.bluesoft.com.br/gtins'),
        );
        $this->assertSame(
            CcgConsGtinEndpoints::URL,
            EmpresaParametros::normalizeCcgConsGtinUrl(''),
        );
        $this->assertSame(
            'https://google.serper.dev/images',
            EmpresaParametros::apiServicosFields()['param_api_servicos_serper_url']['default'],
        );
        $this->assertArrayNotHasKey('param_api_servicos_token', EmpresaParametros::apiServicosFields());
        $this->assertArrayNotHasKey('param_api_servicos_usuario', EmpresaParametros::apiServicosFields());
        $this->assertArrayNotHasKey('param_api_servicos_senha', EmpresaParametros::apiServicosFields());
    }

    public function test_soap12_fault_mostra_a_mensagem_real(): void
    {
        $client = new CcgConsGtinClient($this->transport(<<<'XML'
            <?xml version="1.0" encoding="utf-8"?>
            <soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
              <soap12:Body>
                <soap12:Fault>
                  <soap12:Code><soap12:Value>soap12:Sender</soap12:Value></soap12:Code>
                  <soap12:Reason><soap12:Text xml:lang="en">Server did not recognize the value of HTTP Header SOAPAction</soap12:Text></soap12:Reason>
                </soap12:Fault>
              </soap12:Body>
            </soap12:Envelope>
            XML));

        try {
            $client->consultar('7896188401189', new Certificate('k', 'c', '00000000000000'), CcgConsGtinEndpoints::URL, 5);
            $this->fail('O SOAP Fault deveria ser exibido.');
        } catch (CcgConsultaException $exception) {
            $this->assertSame(
                'Server did not recognize the value of HTTP Header SOAPAction',
                $exception->getMessage(),
            );
            $this->assertStringNotContainsString('indisponível', $exception->getMessage());
        }
    }

    private function transport(string $xml): CcgSoapTransport
    {
        return new class($xml) implements CcgSoapTransport
        {
            public function __construct(private readonly string $xml) {}

            public function post(string $url, string $envelope, Certificate $certificate, int $timeoutSeconds): string
            {
                TestCase::assertStringContainsString('7896188401189', $envelope);
                TestCase::assertSame(CcgConsGtinEndpoints::URL, $url);

                return $this->xml;
            }
        };
    }

    private function resposta(string $cStat, string $motivo, string $corpo): string
    {
        return <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
              <soap12:Body>
                <ccgConsGTINResult xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/ccgConsGTIN">
                  <retConsGTIN versao="1.00" xmlns="http://www.portalfiscal.inf.br/nfe">
                    <verAplic>SVRS</verAplic>
                    <cStat>{$cStat}</cStat>
                    <xMotivo>{$motivo}</xMotivo>
                    <dhResp>2026-10-04T19:00:00-03:00</dhResp>
                    {$corpo}
                  </retConsGTIN>
                </ccgConsGTINResult>
              </soap12:Body>
            </soap12:Envelope>
            XML;
    }
}
