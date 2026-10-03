<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Support\Erp\Nfse\Ipm\NfseIpmCliente;
use App\Support\Erp\Nfse\Ipm\NfseIpmResposta;
use App\Support\Erp\Nfse\Ipm\NfseIpmXmlGerador;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use Tests\TestCase;

class NfseIpmXmlTest extends TestCase
{
    public function test_gera_rps_abrasf_204_com_os_dados_da_nota(): void
    {
        $xml = app(NfseIpmXmlGerador::class)->gerar($this->nota());

        $this->assertStringContainsString('http://www.abrasf.org.br/nfse.xsd', $xml);
        $this->assertStringContainsString('<ItemListaServico>14.01</ItemListaServico>', $xml);
        $this->assertStringContainsString('<Serie>NE</Serie>', $xml);
        $this->assertStringContainsString('<Tipo>1</Tipo>', $xml);
        $this->assertStringContainsString('<Cnpj>54644503000129</Cnpj>', $xml);
        $this->assertStringContainsString('<InscricaoMunicipal>230780</InscricaoMunicipal>', $xml);
        $this->assertStringContainsString('<IssRetido>2</IssRetido>', $xml);
        $this->assertStringContainsString('<CodigoMunicipio>4203204</CodigoMunicipio>', $xml);
        $this->assertStringContainsString('<OptanteSimplesNacional>1</OptanteSimplesNacional>', $xml);
        $this->assertStringNotContainsString('ws-camboriu', $xml);
        $this->assertStringNotContainsString('atende.net', $xml);
    }

    public function test_url_sai_da_configuracao_da_empresa(): void
    {
        $cliente = app(NfseIpmCliente::class);
        $producao = $this->empresa();
        $producao->setAttribute('nfse_ambiente', 'producao');
        $producao->setAttribute('nfse_url_producao', 'https://ws-camboriu.exemplo/?pg=services');
        $producao->setAttribute('nfse_url_homologacao', 'https://ws-homolog.exemplo/?pg=services');

        $homologacao = $this->empresa();
        $homologacao->setAttribute('nfse_ambiente', 'producao_restrita');
        $homologacao->setAttribute('nfse_url_producao', 'https://ws-camboriu.exemplo/?pg=services');
        $homologacao->setAttribute('nfse_url_homologacao', 'https://ws-homolog.exemplo/?pg=services');

        $this->assertSame('https://ws-camboriu.exemplo/?pg=services', $cliente->url($producao));
        $this->assertSame('https://ws-homolog.exemplo/?pg=services', $cliente->url($homologacao));
    }

    public function test_recusa_transmissao_sem_url_configurada(): void
    {
        $empresa = $this->empresa();
        $empresa->setAttribute('nfse_ambiente', 'producao');

        $this->expectException(NfseNaoTransmitida::class);
        app(NfseIpmCliente::class)->url($empresa);
    }

    public function test_interpreta_autorizacao_e_rejeicao_do_ipm(): void
    {
        $autorizada = NfseIpmResposta::interpretar(<<<'XML'
            <GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd">
                <ListaNfse>
                    <CompNfse>
                        <Nfse>
                            <InfNfse>
                                <Numero>3</Numero>
                                <CodigoVerificacao>ABC123</CodigoVerificacao>
                                <DataEmissao>2026-09-28T17:41:00</DataEmissao>
                            </InfNfse>
                        </Nfse>
                    </CompNfse>
                </ListaNfse>
            </GerarNfseResposta>
            XML);

        $this->assertTrue($autorizada->autorizada);
        $this->assertSame('3', $autorizada->numero);
        $this->assertSame('ABC123', $autorizada->codigoVerificacao);

        $rejeitada = NfseIpmResposta::interpretar(<<<'XML'
            <GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd">
                <ListaMensagemRetorno>
                    <MensagemRetorno>
                        <Codigo>E160</Codigo>
                        <Mensagem>Série do RPS inválida.</Mensagem>
                        <Correcao>Use a série liberada pela prefeitura.</Correcao>
                    </MensagemRetorno>
                </ListaMensagemRetorno>
            </GerarNfseResposta>
            XML);

        $this->assertFalse($rejeitada->autorizada);
        $this->assertSame('E160', $rejeitada->erros[0]['codigo']);
        $this->assertStringContainsString('Série do RPS inválida.', $rejeitada->erros[0]['descricao']);

        $soap = NfseIpmResposta::interpretar(<<<'XML'
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
                <soapenv:Body>
                    <nfseDadosMsg><![CDATA[<GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd"><ListaNfse><CompNfse><Nfse><InfNfse><Numero>9</Numero><CodigoVerificacao>XYZ</CodigoVerificacao></InfNfse></Nfse></CompNfse></ListaNfse></GerarNfseResposta>]]></nfseDadosMsg>
                </soapenv:Body>
            </soapenv:Envelope>
            XML);

        $this->assertTrue($soap->autorizada);
        $this->assertSame('9', $soap->numero);
        $this->assertSame('XYZ', $soap->codigoVerificacao);
    }

    private function nota(): Nfse
    {
        $empresa = $this->empresa();
        $empresa->setAttribute('nfse_tipo_rps', '1');
        $empresa->setAttribute('regime_tributario', 'simples');

        $nota = new Nfse([
            'status' => Nfse::STATUS_ABERTA,
            'serie_dps' => 'NE',
            'numero_dps' => 1,
            'competencia' => '2026-09-01',
            'data_emissao' => '2026-09-28',
            'tomador_nome' => 'CLIENTE TESTE',
            'tomador_cpf_cnpj' => '123.456.789-09',
            'tomador_endereco' => 'RUA TESTE',
            'tomador_numero' => '10',
            'tomador_bairro' => 'CENTRO',
            'tomador_cep' => '88340-000',
            'tomador_cidade_codigo' => '4203204',
            'tomador_uf' => 'SC',
            'municipio_prestacao_codigo' => '4203204',
            'trib_issqn' => '1',
            'tp_ret_issqn' => '1',
            'valor_servicos' => '100.00',
            'desconto' => '0.00',
            'iss' => '0.00',
            'total' => '100.00',
        ]);
        $nota->setRelation('empresa', $empresa);
        $nota->setRelation('itens', collect([
            new NfseItem([
                'descricao' => 'TROCA DE OLEO DE CAMBIO',
                'c_trib_nac' => '140101',
                'c_nbs' => '120013110',
            ]),
        ]));

        return $nota;
    }

    private function empresa(): Empresa
    {
        $empresa = new Empresa([
            'cnpj' => '54.644.503/0001-29',
            'razao_social' => 'JUNINHO CAMBIOS',
            'im' => '230780',
            'cidade_codigo' => '4203204',
            'uf' => 'SC',
        ]);
        $empresa->id = 1;

        return $empresa;
    }
}
