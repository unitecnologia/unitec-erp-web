<?php

namespace Tests\Unit;

use App\Support\Erp\Nfse\NfseNaoTransmitida;
use App\Support\Erp\Nfse\NfseSefinEndpoints;
use App\Support\Erp\Nfse\NfseSefinHttp;
use App\Support\Erp\Nfse\NfseSefinRequisicao;
use App\Support\Erp\Nfse\NfseSefinResposta;
use PHPUnit\Framework\TestCase;

class NfseSefinRespostaTest extends TestCase
{
    public function test_interpreta_autorizacao_e_descompacta_o_xml(): void
    {
        $xml = '<NFSe><infNFSe Id="NFS1"/></NFSe>';
        $body = json_encode([
            'tipoAmbiente' => 2,
            'versaoAplicativo' => 'SefinNacional_1.6.0',
            'dataHoraProcessamento' => '2026-09-14T00:30:00.0000000-03:00',
            'idDps' => 'DPS420200822246977200010000001000000000000006',
            'chaveAcesso' => '42020082222469772000100000000000000126094658154600',
            'nfseXmlGZipB64' => base64_encode(gzencode($xml)),
            'alertas' => [],
        ], JSON_THROW_ON_ERROR);

        $resposta = NfseSefinResposta::interpretar(201, $body);

        $this->assertTrue($resposta->autorizada());
        $this->assertSame($xml, $resposta->xmlNfse);
        $this->assertSame('2', $resposta->tipoAmbiente);
        $this->assertSame('SefinNacional_1.6.0', $resposta->versaoAplicativo);
        $this->assertSame([], $resposta->erros);
    }

    public function test_interpreta_rejeicao_sem_gerar_nova_dps(): void
    {
        $body = json_encode([
            'tipoAmbiente' => 2,
            'idDPS' => 'DPS420200822246977200010000001000000000000005',
            'erros' => [[
                'Codigo' => 'E0316',
                'Descricao' => 'Código da lista NBS informado inexistente tabela de NBS do sistema.',
            ]],
        ], JSON_THROW_ON_ERROR);

        $resposta = NfseSefinResposta::interpretar(400, $body);

        $this->assertFalse($resposta->autorizada());
        $this->assertSame('DPS420200822246977200010000001000000000000005', $resposta->idDps);
        $this->assertSame('E0316 — Código da lista NBS informado inexistente tabela de NBS do sistema.', $resposta->mensagemErros());
    }

    public function test_producao_usa_somente_o_host_oficial(): void
    {
        $this->assertSame('https://sefin.nfse.gov.br/SefinNacional/nfse', NfseSefinEndpoints::NFSE_PRODUCAO);
        $this->assertSame(NfseSefinEndpoints::HOST_PRODUCAO, parse_url(NfseSefinEndpoints::NFSE_PRODUCAO, PHP_URL_HOST));
        $this->assertTrue(NfseSefinEndpoints::urlOficial(NfseSefinEndpoints::NFSE_PRODUCAO));
        $this->assertTrue(NfseSefinEndpoints::urlOficial(NfseSefinEndpoints::NFSE_PRODUCAO_RESTRITA));
        $this->assertFalse(NfseSefinEndpoints::urlOficial('https://sefin.nfse.gov.br/API/SefinNacional/nfse'));
        $this->assertNotSame(NfseSefinEndpoints::HOST_PRODUCAO, NfseSefinEndpoints::HOST_PRODUCAO_RESTRITA);

        $this->expectException(NfseNaoTransmitida::class);

        (new NfseSefinHttp)->enviar(new NfseSefinRequisicao(
            url: 'https://sefin.nfse.gov.br/API/SefinNacional/nfse',
            metodo: 'POST',
            headers: [],
            corpo: '{}',
            mtls: ['certificado' => 'cert', 'chave_privada' => 'chave'],
        ));
    }
}
