<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\CancelarNfceRequest;
use Unitec\FiscalEngine\Dto\CartaCorrecaoNfeRequest;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Xml\NfceEventoXmlBuilder;
use Unitec\FiscalEngine\Xml\XmlSigner;

final class NfceEventoXmlBuilderTest extends TestCase
{
    private const CHAVE = '42260722469772000100650010000000011000000010';

    public function test_monta_xml_cancelamento(): void
    {
        $builder = new NfceEventoXmlBuilder();
        $request = new CancelarNfceRequest(
            certificate: new Certificate('key', 'cert', '22469772000100'),
            cnpj: '22469772000100',
            chave: self::CHAVE,
            protocoloAutorizacao: '342260000851334',
            justificativa: 'Estorno de venda no PDV web conforme solicitacao do operador.',
            tpAmb: 2,
            dataEvento: new \DateTimeImmutable('2026-07-06T21:30:00-03:00'),
        );

        $built = $builder->buildCancelamento($request);
        $xml = $builder->finalizeEnvEvento($built['dom']);

        $this->assertStringContainsString('<tpEvento>110111</tpEvento>', $xml);
        $this->assertStringContainsString('<descEvento>Cancelamento</descEvento>', $xml);
        $this->assertStringContainsString('<nProt>342260000851334</nProt>', $xml);
        $this->assertStringContainsString('<xJust>Estorno de venda no PDV web conforme solicitacao do operador.</xJust>', $xml);
        $this->assertStringContainsString(
            'Id="ID110111' . self::CHAVE . '01"',
            $xml,
        );
        $this->assertStringContainsString('<cOrgao>42</cOrgao>', $xml);
        $this->assertStringContainsString('<dhEvento>2026-07-06T21:30:00-03:00</dhEvento>', $xml);
    }

    public function test_monta_xml_carta_correcao(): void
    {
        $builder = new NfceEventoXmlBuilder();
        $request = new CartaCorrecaoNfeRequest(
            certificate: new Certificate('key', 'cert', '22469772000100'),
            cnpj: '22469772000100',
            chave: self::CHAVE,
            correcao: 'Correcao de endereco de entrega informado incorretamente na nota fiscal.',
            tpAmb: 2,
            nSeqEvento: 1,
            dataEvento: new \DateTimeImmutable('2026-07-07T21:40:00-03:00'),
        );

        $built = $builder->buildCartaCorrecao($request);
        $xml = $builder->finalizeEnvEvento($built['dom']);

        $this->assertStringContainsString('<tpEvento>110110</tpEvento>', $xml);
        $this->assertStringContainsString('<descEvento>Carta de Correcao</descEvento>', $xml);
        $this->assertStringContainsString('<xCorrecao>Correcao de endereco de entrega informado incorretamente na nota fiscal.</xCorrecao>', $xml);
        $this->assertStringContainsString('<xCondUso>A Carta de Correcao e disciplinada', $xml);
        $this->assertStringContainsString('de saida.</xCondUso>', $xml);
        $this->assertStringContainsString(
            'Id="ID110110' . self::CHAVE . '01"',
            $xml,
        );
        $this->assertStringContainsString('<dhEvento>2026-07-07T21:40:00-03:00</dhEvento>', $xml);
    }

    public function test_rejeita_correcao_curta(): void
    {
        $builder = new NfceEventoXmlBuilder();
        $request = new CartaCorrecaoNfeRequest(
            certificate: new Certificate('key', 'cert', '22469772000100'),
            cnpj: '22469772000100',
            chave: self::CHAVE,
            correcao: 'Curta',
            tpAmb: 2,
        );

        $this->expectException(FiscalEngineException::class);
        $this->expectExceptionMessage('15 caracteres');

        $builder->buildCartaCorrecao($request);
    }

    public function test_rejeita_justificativa_curta(): void
    {
        $builder = new NfceEventoXmlBuilder();
        $request = new CancelarNfceRequest(
            certificate: new Certificate('key', 'cert', '22469772000100'),
            cnpj: '22469772000100',
            chave: self::CHAVE,
            protocoloAutorizacao: '342260000851334',
            justificativa: 'Curta',
            tpAmb: 2,
        );

        $this->expectException(FiscalEngineException::class);
        $this->expectExceptionMessage('15 caracteres');

        $builder->buildCancelamento($request);
    }

    public function test_assina_inf_evento(): void
    {
        if (! is_readable(__DIR__ . '/../fixtures/test-cert.pem')) {
            $this->markTestSkipped('Certificado de teste não disponível.');
        }

        $certificatePem = (string) file_get_contents(__DIR__ . '/../fixtures/test-cert.pem');
        $privateKeyPem = (string) file_get_contents(__DIR__ . '/../fixtures/test-key.pem');
        $certificate = new Certificate($privateKeyPem, $certificatePem, '22469772000100');

        $builder = new NfceEventoXmlBuilder();
        $request = new CancelarNfceRequest(
            certificate: $certificate,
            cnpj: '22469772000100',
            chave: self::CHAVE,
            protocoloAutorizacao: '342260000851334',
            justificativa: 'Estorno de venda no PDV web conforme solicitacao do operador.',
            tpAmb: 2,
        );

        $built = $builder->buildCancelamento($request);
        (new XmlSigner())->signInfEvento($built['dom'], $certificate);

        $xml = $builder->finalizeEnvEvento($built['dom']);

        $this->assertStringContainsString('<Signature', $xml);
        $this->assertStringContainsString('<SignatureValue>', $xml);
        $this->assertStringContainsString('<X509Certificate>', $xml);
    }
}
