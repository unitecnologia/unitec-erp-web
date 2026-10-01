<?php

namespace Tests\Unit;

use App\Support\Erp\Nfse\NfseSefinAmbiente;
use App\Support\Erp\Nfse\NfseSefinClient;
use App\Support\Erp\Nfse\NfseSefinEndpoints;
use App\Support\Erp\Nfse\NfseSefinNaoEnviada;
use App\Support\Erp\Nfse\NfseSefinRequisicao;
use App\Support\Erp\Nfse\NfseSefinTransporte;
use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;

class NfseSefinClientTest extends TestCase
{
    public function test_prepara_post_da_producao_restrita_sem_chamar_a_rede(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?><DPS xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01"><infDPS Id="DPS420200822246977200010000001000000000000004"><tpAmb>1</tpAmb></infDPS></DPS>
XML;
        $certificado = new Certificate(
            "-----BEGIN PRIVATE KEY-----\nCHAVE-EMPRESA\n-----END PRIVATE KEY-----\n",
            "-----BEGIN CERTIFICATE-----\nCERTIFICADO-EMPRESA\n-----END CERTIFICATE-----\n",
            '22469772000100',
        );
        $transporte = new NfseSefinTransporteFake;
        $client = new NfseSefinClient(NfseSefinAmbiente::ProducaoRestrita);

        $client->enviar($xml, $certificado, $transporte);

        $requisicao = $transporte->requisicao;
        $this->assertNotNull($requisicao);
        $this->assertSame(NfseSefinEndpoints::NFSE_PRODUCAO_RESTRITA, $requisicao->url);
        $this->assertSame('sefin.producaorestrita.nfse.gov.br', parse_url($requisicao->url, PHP_URL_HOST));
        $this->assertStringNotContainsString(NfseSefinEndpoints::HOST_PRODUCAO, $requisicao->url);
        $this->assertSame('POST', $requisicao->metodo);
        $this->assertSame('application/json', $requisicao->headers['Content-Type']);
        $this->assertSame('application/json', $requisicao->headers['Accept']);
        $this->assertSame($certificado->certificatePem, $requisicao->mtls['certificado']);
        $this->assertSame($certificado->privateKeyPem, $requisicao->mtls['chave_privada']);

        $payload = json_decode($requisicao->corpo, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('dpsXmlGZipB64', $payload);
        $this->assertSame(
            $xml,
            gzdecode(base64_decode((string) $payload['dpsXmlGZipB64'], true)),
        );
    }

    public function test_o_mesmo_ambiente_define_tp_amb_e_host(): void
    {
        $restrita = NfseSefinAmbiente::ProducaoRestrita;
        $producao = NfseSefinAmbiente::Producao;

        $this->assertSame('2', $restrita->tpAmb());
        $this->assertSame(NfseSefinEndpoints::HOST_PRODUCAO_RESTRITA, $restrita->host());
        $this->assertSame(NfseSefinEndpoints::NFSE_PRODUCAO_RESTRITA, $restrita->url());
        $this->assertSame($restrita->url(), (new NfseSefinClient($restrita))->url());

        $this->assertSame('1', $producao->tpAmb());
        $this->assertSame(NfseSefinEndpoints::HOST_PRODUCAO, $producao->host());
        $this->assertSame(NfseSefinEndpoints::NFSE_PRODUCAO, $producao->url());
        $this->assertNotSame($restrita->host(), $producao->host());
        $this->assertSame($producao->host(), parse_url($producao->url(), PHP_URL_HOST));
        $this->assertSame($restrita->host(), parse_url($restrita->url(), PHP_URL_HOST));
    }

    public function test_ambiente_de_teste_nunca_resolve_o_host_de_producao(): void
    {
        foreach (['testing', 'local', 'producao_restrita', 'homologacao'] as $ambiente) {
            $url = NfseSefinEndpoints::nfse(NfseSefinAmbiente::para($ambiente));

            $this->assertSame(NfseSefinEndpoints::NFSE_PRODUCAO_RESTRITA, $url);
            $this->assertSame(NfseSefinEndpoints::HOST_PRODUCAO_RESTRITA, parse_url($url, PHP_URL_HOST));
            $this->assertNotSame(NfseSefinEndpoints::HOST_PRODUCAO, parse_url($url, PHP_URL_HOST));
        }
    }

    public function test_producao_nao_dispara_o_transporte(): void
    {
        $transporte = new NfseSefinTransporteFake;
        $client = new NfseSefinClient(NfseSefinAmbiente::Producao);

        try {
            $client->enviar('<DPS/>', $this->certificado(), $transporte);
            $this->fail('Produção não pode ser enviada.');
        } catch (NfseSefinNaoEnviada) {
            $this->assertNull($transporte->requisicao);
        }
    }

    private function certificado(): Certificate
    {
        return new Certificate('chave', 'certificado', '22469772000100');
    }
}

final class NfseSefinTransporteFake implements NfseSefinTransporte
{
    public ?NfseSefinRequisicao $requisicao = null;

    public function enviar(NfseSefinRequisicao $requisicao): void
    {
        $this->requisicao = $requisicao;
    }
}
