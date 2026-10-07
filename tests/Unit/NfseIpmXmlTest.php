<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Models\OrdemServico;
use App\Support\Erp\Nfse\Ipm\NfseIpmAssinador;
use App\Support\Erp\Nfse\Ipm\NfseIpmCancelamentoResposta;
use App\Support\Erp\Nfse\Ipm\NfseIpmCliente;
use App\Support\Erp\Nfse\Ipm\NfseIpmMunicipios;
use App\Support\Erp\Nfse\Ipm\NfseIpmResposta;
use App\Support\Erp\Nfse\Ipm\NfseIpmXmlGerador;
use App\Support\Erp\Nfse\Ipm\NfseIpmXmlValidador;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use App\Support\Erp\Nfse\NfseOsDiscriminacao;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;

class NfseIpmXmlTest extends TestCase
{
    private const DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    private static ?Certificate $certificado = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-28 10:00:00');
    }

    public function test_rps_assinado_valida_no_xsd_oficial_ipm(): void
    {
        $xml = $this->assinado($this->nota());

        $this->assertSame([], app(NfseIpmXmlValidador::class)->erros($xml));
    }

    public function test_rps_segue_campos_e_regras_da_nte_para_simples_sem_retencao(): void
    {
        $xml = app(NfseIpmXmlGerador::class)->gerar($this->nota());

        $this->assertStringContainsString('<GerarNfseEnvio xmlns="http://www.abrasf.org.br/nfse.xsd"><Rps><InfDeclaracaoPrestacaoServico Id="RPS_1">', $xml);
        $this->assertStringContainsString('<InfDeclaracaoPrestacaoServico Id="RPS_1"><Competencia>2026-09-28</Competencia>', $xml);
        $this->assertStringNotContainsString('<IdentificacaoRps>', $xml);
        $this->assertStringContainsString('<Valores><ValorServicos>100.00</ValorServicos><ValorIss>2.00</ValorIss><Aliquota>2.00</Aliquota></Valores><IssRetido>2</IssRetido>', $xml);
        $this->assertStringContainsString('<ItemListaServico>14.01.01</ItemListaServico>', $xml);
        $this->assertStringContainsString('<CodigoCnae>4520001</CodigoCnae>', $xml);
        $this->assertStringContainsString('<CodigoNbs>120013110</CodigoNbs>', $xml);
        $this->assertStringContainsString('<ExigibilidadeISS>1</ExigibilidadeISS><MunicipioIncidencia>4101804</MunicipioIncidencia>', $xml);
        $this->assertStringContainsString('<Prestador><CpfCnpj><Cnpj>54644503000129</Cnpj></CpfCnpj><InscricaoMunicipal>230780</InscricaoMunicipal></Prestador>', $xml);
        $this->assertStringContainsString('<TomadorServico><IdentificacaoTomador><CpfCnpj><Cpf>12345678909</Cpf></CpfCnpj></IdentificacaoTomador><RazaoSocial>CLIENTE TESTE &amp; FILHOS</RazaoSocial>', $xml);
        $this->assertStringContainsString('<Endereco><Endereco>RUA TESTE</Endereco><Numero>10</Numero><Bairro>CENTRO</Bairro><CodigoMunicipio>4101804</CodigoMunicipio><Uf>PR</Uf><Cep>83702000</Cep></Endereco>', $xml);
        $this->assertStringContainsString('<Contato><Telefone>41999990000</Telefone><Email>cliente@teste.com</Email></Contato>', $xml);
        $this->assertStringContainsString('<OptanteSimplesNacional>1</OptanteSimplesNacional><IncentivoFiscal>2</IncentivoFiscal></InfDeclaracaoPrestacaoServico>', $xml);
        $this->assertStringNotContainsString('<RegimeEspecialTributacao>', $xml);
        $this->assertStringNotContainsString('IBSCBS', $xml);
        $this->assertStringNotContainsString('EnvioTeste', $xml);
        $this->assertStringNotContainsString('<Tomador>', $xml);
    }

    public function test_desconto_vai_abatido_do_valor_dos_servicos_sem_desconto_incondicionado(): void
    {
        $nota = $this->nota();
        $nota->setAttribute('desconto', '20.00');
        $nota->setAttribute('total', '80.00');

        $xml = app(NfseIpmXmlGerador::class)->gerar($nota);

        $this->assertStringContainsString('<Valores><ValorServicos>80.00</ValorServicos><ValorIss>1.60</ValorIss><Aliquota>2.00</Aliquota></Valores>', $xml);
        $this->assertStringNotContainsString('DescontoIncondicionado', $xml);
    }

    public function test_pedido_de_cancelamento_assinado_valida_no_xsd_oficial(): void
    {
        $nota = $this->nota();
        $nota->setAttribute('status', Nfse::STATUS_AUTORIZADA);
        $nota->setAttribute('numero_nfse', '8');

        $xml = app(NfseIpmXmlGerador::class)->gerarCancelamento($nota, '1');

        $this->assertStringContainsString(
            '<CancelarNfseEnvio xmlns="http://www.abrasf.org.br/nfse.xsd"><Pedido><InfPedidoCancelamento Id="CANC_8"><IdentificacaoNfse><Numero>8</Numero><CpfCnpj><Cnpj>54644503000129</Cnpj></CpfCnpj><InscricaoMunicipal>230780</InscricaoMunicipal><CodigoMunicipio>4101804</CodigoMunicipio></IdentificacaoNfse><CodigoCancelamento>1</CodigoCancelamento></InfPedidoCancelamento>',
            $xml,
        );

        $assinado = app(NfseIpmAssinador::class)->assinarPedidoCancelamento($xml, $this->certificado());
        $doc = new DOMDocument;
        $doc->loadXML($assinado);
        $signature = $doc->getElementsByTagNameNS(self::DSIG, 'Signature')->item(0);

        $this->assertSame([], app(NfseIpmXmlValidador::class)->erros($assinado));
        $this->assertSame('Pedido', $signature?->parentNode?->localName);
        $this->assertStringContainsString('URI="#CANC_8"', $assinado);

        $envelope = app(NfseIpmCliente::class)->envelopeCancelamento($assinado);
        $this->assertStringContainsString('<soapenv:Body><CancelarNfseEnvio><Pedido><InfPedidoCancelamento xmlns="http://www.abrasf.org.br/nfse.xsd" Id="CANC_8">', $envelope);
    }

    public function test_cancelamento_recusa_motivo_invalido_e_nota_sem_numero(): void
    {
        $nota = $this->nota();
        $nota->setAttribute('numero_nfse', '8');

        try {
            app(NfseIpmXmlGerador::class)->gerarCancelamento($nota, '9');
            $this->fail('Motivo inválido deveria ser recusado.');
        } catch (NfseNaoTransmitida $exception) {
            $this->assertStringContainsString('motivo', $exception->getMessage());
        }

        $nota->setAttribute('numero_nfse', null);
        $this->expectException(NfseNaoTransmitida::class);
        app(NfseIpmXmlGerador::class)->gerarCancelamento($nota, '1');
    }

    public function test_resposta_do_cancelamento_confirmada_recusada_e_ja_cancelada(): void
    {
        $confirmada = NfseIpmCancelamentoResposta::interpretar(
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            .'<CancelarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd"><RetCancelamento><NfseCancelamento><Confirmacao Id="C1">'
            .'<Pedido><InfPedidoCancelamento Id="CANC_8"><IdentificacaoNfse><Numero>8</Numero></IdentificacaoNfse><CodigoCancelamento>1</CodigoCancelamento></InfPedidoCancelamento></Pedido>'
            .'<DataHora>2026-10-06T21:30:00</DataHora></Confirmacao></NfseCancelamento></RetCancelamento></CancelarNfseResposta>'
            .'</soap:Body></soap:Envelope>'
        );
        $this->assertTrue($confirmada->cancelada);
        $this->assertSame('2026-10-06T21:30:00', $confirmada->dataHora);
        $this->assertSame([], $confirmada->erros);

        $recusada = NfseIpmCancelamentoResposta::interpretar(
            '<CancelarNfseResposta><ListaMensagemRetorno><MensagemRetorno><Codigo>L999</Codigo><Mensagem>Prazo de cancelamento expirado.</Mensagem></MensagemRetorno></ListaMensagemRetorno></CancelarNfseResposta>'
        );
        $this->assertFalse($recusada->cancelada);
        $this->assertSame([['codigo' => 'L999', 'descricao' => 'Prazo de cancelamento expirado.']], $recusada->erros);

        $jaCancelada = NfseIpmCancelamentoResposta::interpretar(
            '<ListaMensagemRetorno><MensagemRetorno><Codigo>E79</Codigo><Mensagem>Esta NFS-e já está cancelada.</Mensagem></MensagemRetorno></ListaMensagemRetorno>'
        );
        $this->assertTrue($jaCancelada->cancelada);
        $this->assertTrue($jaCancelada->jaCancelada);
    }

    public function test_modo_teste_envia_envio_teste_1_antes_do_rps(): void
    {
        $xml = $this->assinado($this->nota(), true);

        $this->assertStringContainsString('<GerarNfseEnvio xmlns="http://www.abrasf.org.br/nfse.xsd"><EnvioTeste>1</EnvioTeste><Rps>', $xml);
        $this->assertSame([], app(NfseIpmXmlValidador::class)->erros($xml));
    }

    public function test_assinatura_fica_em_rps_apos_a_declaracao_e_referencia_o_id(): void
    {
        $xml = $this->assinado($this->nota());
        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $signature = $doc->getElementsByTagNameNS(self::DSIG, 'Signature')->item(0);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', self::DSIG);

        $this->assertInstanceOf(DOMElement::class, $signature);
        $this->assertSame('Rps', $signature->parentNode?->localName);
        $this->assertSame('GerarNfseEnvio', $signature->parentNode?->parentNode?->localName);
        $this->assertSame('InfDeclaracaoPrestacaoServico', $signature->previousSibling?->localName);
        $this->assertNull($signature->nextSibling);
        $this->assertSame('#RPS_1', $xpath->evaluate('string(//ds:Reference/@URI)'));
        $this->assertSame('http://www.w3.org/TR/2001/REC-xml-c14n-20010315', $xpath->evaluate('string(//ds:CanonicalizationMethod/@Algorithm)'));
        $this->assertSame(self::DSIG.'rsa-sha1', $xpath->evaluate('string(//ds:SignatureMethod/@Algorithm)'));
        $this->assertSame(self::DSIG.'sha1', $xpath->evaluate('string(//ds:DigestMethod/@Algorithm)'));
        $this->assertSame(
            [self::DSIG.'enveloped-signature', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315'],
            array_map(fn (DOMElement $no): string => $no->getAttribute('Algorithm'), iterator_to_array($xpath->query('//ds:Transform'))),
        );
        $this->assertSame(1, $xpath->query('//ds:KeyInfo/ds:X509Data/ds:X509Certificate')->length);

        app(NfseIpmAssinador::class)->validar($xml);

        $this->expectException(NfseNaoTransmitida::class);
        app(NfseIpmAssinador::class)->validar(str_replace('<ValorServicos>100.00</ValorServicos>', '<ValorServicos>900.00</ValorServicos>', $xml));
    }

    public function test_recusa_certificado_de_outro_cnpj(): void
    {
        $xml = app(NfseIpmXmlGerador::class)->gerar($this->nota());
        $outro = $this->certificado('11222333000181');

        $this->expectException(NfseNaoTransmitida::class);
        $this->expectExceptionMessage('não pertence ao CNPJ do prestador');
        app(NfseIpmAssinador::class)->assinar($xml, $outro);
    }

    public function test_codigo_municipal_vai_junto_com_cnae_da_empresa(): void
    {
        $xml = $this->assinado($this->nota(['c_trib_mun' => '452000100']));

        $this->assertStringContainsString('<ItemListaServico>14.01.01</ItemListaServico><CodigoCnae>4520001</CodigoCnae><CodigoTributacaoMunicipio>452000100</CodigoTributacaoMunicipio><CodigoNbs>', $xml);
        $this->assertSame([], app(NfseIpmXmlValidador::class)->erros($xml));
    }

    public function test_discriminacao_da_nota_vai_abaixo_da_descricao_do_servico(): void
    {
        $nota = $this->nota();
        $nota->setAttribute('discriminacao', 'PLACA ABC1D23 - CAMBIO AUTOMATICO');
        $xml = $this->assinado($nota);

        $this->assertStringContainsString("<Discriminacao>TROCA DE OLEO DE CAMBIO\nPLACA ABC1D23 - CAMBIO AUTOMATICO</Discriminacao>", $xml);
        $this->assertSame([], app(NfseIpmXmlValidador::class)->erros($xml));
    }

    public function test_bloco_da_os_vai_no_xml_assinado_uma_vez_e_valida_no_xsd(): void
    {
        $os = new OrdemServico([
            'numero' => '6',
            'descricao' => 'FIAT FIORINO',
            'placa' => 'lzk6311',
            'problema' => 'Câmbio raspando a 2ª marcha',
            'laudo' => 'Embreagem gasta; trocado kit completo',
        ]);
        $nota = $this->nota();
        $nota->setRelation('itens', collect([
            new NfseItem(['descricao' => 'MAO DE OBRA', 'c_trib_nac' => '140101', 'c_nbs' => '120013110']),
            new NfseItem(['descricao' => 'PROGRAMACAO', 'c_trib_nac' => '140101', 'c_nbs' => '120013110']),
        ]));
        $nota->setAttribute('discriminacao', NfseOsDiscriminacao::texto($os));
        $xml = $this->assinado($nota);

        $this->assertStringContainsString(
            "<Discriminacao>MAO DE OBRA | PROGRAMACAO\nOS nº 6\nEquipamento/Veículo: FIAT FIORINO | Placa: LZK6311\nProblema: Câmbio raspando a 2ª marcha\nLaudo: Embreagem gasta; trocado kit completo</Discriminacao>",
            $xml,
        );
        $this->assertSame(1, substr_count($xml, 'OS nº 6'));
        $this->assertSame([], app(NfseIpmXmlValidador::class)->erros($xml));
    }

    public function test_rejeicao_de_atividade_indica_o_campo_do_erp(): void
    {
        $resposta = NfseIpmResposta::interpretar(<<<'XML'
            <GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd">
                <ListaMensagemRetorno>
                    <MensagemRetorno><Codigo>L1024</Codigo><Mensagem>O município não utiliza código Cnae padrão.</Mensagem></MensagemRetorno>
                    <MensagemRetorno><Codigo>L1003</Codigo><Mensagem>A atividade informada não está vinculada à lista de serviço.</Mensagem></MensagemRetorno>
                </ListaMensagemRetorno>
            </GerarNfseResposta>
            XML);

        $this->assertStringContainsString('"Cód. municipal"', $resposta->erros[0]['descricao']);
        $this->assertStringContainsString('"Cód. tributação nacional"', $resposta->erros[1]['descricao']);
    }

    public function test_nao_incidencia_omite_municipio_de_incidencia(): void
    {
        $nota = $this->nota();
        $nota->setAttribute('trib_issqn', '4');
        $xml = $this->assinado($nota);

        $this->assertStringContainsString('<ExigibilidadeISS>2</ExigibilidadeISS>', $xml);
        $this->assertStringNotContainsString('MunicipioIncidencia', $xml);
        $this->assertSame([], app(NfseIpmXmlValidador::class)->erros($xml));
    }

    public function test_regime_especial_converte_codigo_nacional_para_abrasf(): void
    {
        $nota = $this->nota();
        $nota->empresa->setAttribute('nfse_reg_esp_trib', '3');
        $this->assertStringContainsString('<RegimeEspecialTributacao>1</RegimeEspecialTributacao>', app(NfseIpmXmlGerador::class)->gerar($nota));

        $nota->empresa->setAttribute('nfse_reg_esp_trib', '6');
        $this->assertStringContainsString('<RegimeEspecialTributacao>3</RegimeEspecialTributacao>', app(NfseIpmXmlGerador::class)->gerar($nota));

        $nota->empresa->setAttribute('nfse_reg_esp_trib', '0');
        $nota->empresa->setAttribute('regime_tributario', 'mei');
        $this->assertStringContainsString('<RegimeEspecialTributacao>5</RegimeEspecialTributacao>', app(NfseIpmXmlGerador::class)->gerar($nota));
    }

    public function test_obra_por_endereco_e_por_cno_validam_no_xsd(): void
    {
        $nota = $this->nota([
            'c_trib_nac' => '070201',
            'c_nbs' => '101190100',
            'obra_tipo' => 'end',
            'obra_cep' => '83702-000',
            'obra_logradouro' => 'RUA DA OBRA',
            'obra_numero' => '50',
            'obra_bairro' => 'CENTRO',
        ]);
        $xml = $this->assinado($nota);

        $this->assertStringContainsString('<Obra><Cep>83702000</Cep><CodigoMunicipio>4101804</CodigoMunicipio><Endereco>RUA DA OBRA</Endereco><Bairro>CENTRO</Bairro><Numero>50</Numero></Obra>', $xml);
        $this->assertSame([], app(NfseIpmXmlValidador::class)->erros($xml));

        $cno = $this->nota(['c_trib_nac' => '070201', 'c_nbs' => '101190100', 'obra_tipo' => 'cObra', 'obra_c_obra' => '900012345678']);
        $xml = $this->assinado($cno);

        $this->assertStringContainsString('<ConstrucaoCivil><CodigoObra>900012345678</CodigoObra></ConstrucaoCivil><OptanteSimplesNacional>', $xml);
        $this->assertSame([], app(NfseIpmXmlValidador::class)->erros($xml));
    }

    public function test_validador_aponta_xml_fora_do_xsd(): void
    {
        $xml = str_replace('<Competencia>', '<Competencia>x', app(NfseIpmXmlGerador::class)->gerar($this->nota()));

        $this->assertNotSame([], app(NfseIpmXmlValidador::class)->erros($xml));
    }

    /**
     * @return array<string, array{0: callable(Nfse): void, 1: string}>
     */
    public static function bloqueios(): array
    {
        return [
            'simples sem alíquota' => [fn (Nfse $nota) => $nota->setAttribute('aliquota_iss', null), 'alíquota do ISS do Simples'],
            'incidência fora do município sem alíquota' => [function (Nfse $nota): void {
                $nota->empresa->setAttribute('regime_tributario', 'normal');
                $nota->setAttribute('aliquota_iss', null);
                $nota->setAttribute('municipio_prestacao_codigo', '4106902');
            }, 'fora do município'],
            'sem NBS' => [fn (Nfse $nota) => $nota->itens->first()->setAttribute('c_nbs', null), 'NBS'],
            'endereço do tomador incompleto' => [fn (Nfse $nota) => $nota->setAttribute('tomador_bairro', ''), 'endereço do tomador'],
            'serviços com códigos diferentes' => [fn (Nfse $nota) => $nota->itens->push(new NfseItem(['descricao' => 'OUTRO', 'c_trib_nac' => '170101', 'c_nbs' => '120013110'])), 'único código'],
            'sem código municipal e sem CNAE' => [fn (Nfse $nota) => $nota->empresa->setAttribute('cnae', null), 'Cód. municipal'],
            'código nacional incompleto' => [fn (Nfse $nota) => $nota->itens->first()->setAttribute('c_trib_nac', '1401'), '6 dígitos'],
        ];
    }

    #[DataProvider('bloqueios')]
    public function test_bloqueia_dados_que_a_ipm_rejeitaria(callable $alterar, string $mensagem): void
    {
        $nota = $this->nota();
        $alterar($nota);

        $this->expectException(NfseNaoTransmitida::class);
        $this->expectExceptionMessage($mensagem);
        app(NfseIpmXmlGerador::class)->gerar($nota);
    }

    public function test_envelope_soap_segue_o_wsdl_ipm(): void
    {
        $xml = app(NfseIpmXmlGerador::class)->gerar($this->nota(), true);
        $envelope = app(NfseIpmCliente::class)->envelope($xml);

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Header/><soapenv:Body><GerarNfseEnvio><EnvioTeste>1</EnvioTeste><Rps><InfDeclaracaoPrestacaoServico xmlns="http://www.abrasf.org.br/nfse.xsd" Id="RPS_1">', $envelope);
        $this->assertStringEndsWith('</GerarNfseEnvio></soapenv:Body></soapenv:Envelope>', $envelope);
        $this->assertSame(1, substr_count($envelope, '<?xml'));
        $this->assertStringNotContainsString('CDATA', $envelope);
        $this->assertStringNotContainsString('nfseDadosMsg', $envelope);
        $this->assertSame('net.atende#GerarNfseEnvio', NfseIpmCliente::SOAP_ACTION_GERAR_NFSE);
    }

    public function test_cliente_recusa_modo_de_envio_diferente_do_ambiente(): void
    {
        $empresa = $this->empresa();
        $empresa->setAttribute('nfse_ws_usuario', '54644503000129');
        $empresa->setAttribute('nfse_ws_senha', 'segredo');
        $empresa->setAttribute('nfse_ambiente', 'producao_restrita');
        $empresa->setAttribute('nfse_url_producao', 'https://ipm.invalid/?pg=services');

        $this->expectException(NfseNaoTransmitida::class);
        $this->expectExceptionMessage('modo de envio');
        app(NfseIpmCliente::class)->enviar($empresa, app(NfseIpmXmlGerador::class)->gerar($this->nota()), true);
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
        $this->assertFalse($cliente->modoTeste($producao));
        $this->assertTrue($cliente->modoTeste($homologacao));
    }

    public function test_teste_sem_url_de_homologacao_usa_a_url_do_municipio_sem_wsdl(): void
    {
        $empresa = $this->empresa();
        $empresa->setAttribute('nfse_ambiente', 'producao_restrita');
        $empresa->setAttribute('nfse_url_producao', 'https://araucaria.atende.net/?pg=services&service=WNENotaFiscalEletronicaNfe&wsdl');

        $this->assertSame(
            'https://araucaria.atende.net/?pg=services&service=WNENotaFiscalEletronicaNfe',
            app(NfseIpmCliente::class)->url($empresa),
        );
    }

    public function test_recusa_transmissao_sem_url_configurada(): void
    {
        $empresa = $this->empresa();
        $empresa->setAttribute('nfse_ambiente', 'producao');

        $this->expectException(NfseNaoTransmitida::class);
        app(NfseIpmCliente::class)->url($empresa);
    }

    public function test_sugere_endpoint_oficial_por_codigo_ibge(): void
    {
        $this->assertSame('https://araucaria.atende.net/?pg=services&service=WNENotaFiscalEletronicaNfe', NfseIpmMunicipios::endpoint('4101804'));
        $this->assertSame('https://camboriu.atende.net/?pg=services&service=WNENotaFiscalEletronicaNfe', NfseIpmMunicipios::endpoint('420320-4'));
        $this->assertNull(NfseIpmMunicipios::endpoint('4106902'));
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
                    <ListaMensagemAlertaRetorno>
                        <MensagemRetorno>
                            <Codigo>L1001</Codigo>
                            <Mensagem>Alerta qualquer.</Mensagem>
                        </MensagemRetorno>
                    </ListaMensagemAlertaRetorno>
                </ListaNfse>
            </GerarNfseResposta>
            XML);

        $this->assertTrue($autorizada->autorizada);
        $this->assertSame('3', $autorizada->numero);
        $this->assertSame('ABC123', $autorizada->codigoVerificacao);
        $this->assertSame([], $autorizada->erros);
        $this->assertSame(['L1001 Alerta qualquer.'], $autorizada->alertas);
        $this->assertFalse($autorizada->modoTeste);

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
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="net.atende">
                <SOAP-ENV:Body>
                    <ns1:GerarNfseEnvioResponse><return>&lt;GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd"&gt;&lt;ListaNfse&gt;&lt;CompNfse&gt;&lt;Nfse&gt;&lt;InfNfse&gt;&lt;Numero&gt;9&lt;/Numero&gt;&lt;CodigoVerificacao&gt;XYZ&lt;/CodigoVerificacao&gt;&lt;/InfNfse&gt;&lt;/Nfse&gt;&lt;/CompNfse&gt;&lt;/ListaNfse&gt;&lt;/GerarNfseResposta&gt;</return></ns1:GerarNfseEnvioResponse>
                </SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML);

        $this->assertTrue($soap->autorizada);
        $this->assertSame('9', $soap->numero);
        $this->assertSame('XYZ', $soap->codigoVerificacao);

        $falha = NfseIpmResposta::interpretar(<<<'XML'
            <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
                <SOAP-ENV:Body><SOAP-ENV:Fault><faultcode>SOAP-ENV:Server</faultcode><faultstring>Usuário sem permissão.</faultstring></SOAP-ENV:Fault></SOAP-ENV:Body>
            </SOAP-ENV:Envelope>
            XML);

        $this->assertFalse($falha->autorizada);
        $this->assertSame('Usuário sem permissão.', $falha->erros[0]['descricao']);
    }

    public function test_retorno_em_modo_teste_nunca_vira_nfse_autorizada(): void
    {
        $retorno = <<<'XML'
            <GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd">
                <ListaNfse>
                    <CompNfse><Nfse><InfNfse><Numero>15</Numero><CodigoVerificacao>TESTE</CodigoVerificacao></InfNfse></Nfse></CompNfse>
                    <ListaMensagemAlertaRetorno>
                        <MensagemRetorno><Codigo>L1079</Codigo><Mensagem>A nota foi enviada com o modo teste ativado.</Mensagem></MensagemRetorno>
                    </ListaMensagemAlertaRetorno>
                </ListaNfse>
            </GerarNfseResposta>
            XML;

        foreach ([true, false] as $envioTeste) {
            $resposta = NfseIpmResposta::interpretar($retorno, $envioTeste);

            $this->assertFalse($resposta->autorizada);
            $this->assertTrue($resposta->modoTeste);
            $this->assertTrue($resposta->aceitaEmTeste);
            $this->assertNull($resposta->numero);
            $this->assertNull($resposta->codigoVerificacao);
            $this->assertSame([], $resposta->erros);
        }

        $comoErro = NfseIpmResposta::interpretar(<<<'XML'
            <GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd">
                <ListaMensagemRetorno>
                    <MensagemRetorno><Codigo>L1079</Codigo><Mensagem>A nota foi enviada com o modo teste ativado.</Mensagem></MensagemRetorno>
                </ListaMensagemRetorno>
            </GerarNfseResposta>
            XML, true);

        $this->assertTrue($comoErro->aceitaEmTeste);
        $this->assertSame([], $comoErro->erros);
        $this->assertSame(['L1079 A nota foi enviada com o modo teste ativado.'], $comoErro->alertas);

        $recusada = NfseIpmResposta::interpretar(<<<'XML'
            <GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd">
                <ListaMensagemRetorno>
                    <MensagemRetorno><Codigo>L1079</Codigo><Mensagem>A nota foi enviada com o modo teste ativado.</Mensagem></MensagemRetorno>
                    <MensagemRetorno><Codigo>L1027</Codigo><Mensagem>Inscrição municipal obrigatória.</Mensagem></MensagemRetorno>
                </ListaMensagemRetorno>
            </GerarNfseResposta>
            XML, true);

        $this->assertFalse($recusada->aceitaEmTeste);
        $this->assertFalse($recusada->autorizada);
        $this->assertSame('L1027', $recusada->erros[0]['codigo']);
        $this->assertCount(1, $recusada->erros);
    }

    private function assinado(Nfse $nota, bool $envioTeste = false): string
    {
        return app(NfseIpmAssinador::class)->assinar(
            app(NfseIpmXmlGerador::class)->gerar($nota, $envioTeste),
            $this->certificado(),
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function nota(array $item = []): Nfse
    {
        $empresa = $this->empresa();
        $empresa->setAttribute('nfse_tipo_rps', '1');
        $empresa->setAttribute('regime_tributario', 'simples');
        $empresa->setAttribute('nfse_reg_esp_trib', '0');

        $nota = new Nfse([
            'status' => Nfse::STATUS_ABERTA,
            'serie_dps' => 'NE',
            'numero_dps' => 1,
            'competencia' => '2026-09-01',
            'data_emissao' => '2026-09-28',
            'tomador_nome' => 'CLIENTE TESTE & FILHOS',
            'tomador_cpf_cnpj' => '123.456.789-09',
            'tomador_telefone' => '(41) 99999-0000',
            'tomador_email' => 'cliente@teste.com',
            'tomador_endereco' => 'RUA TESTE',
            'tomador_numero' => '10',
            'tomador_bairro' => 'CENTRO',
            'tomador_cep' => '83702-000',
            'tomador_cidade_codigo' => '4101804',
            'tomador_uf' => 'PR',
            'municipio_prestacao_codigo' => '4101804',
            'trib_issqn' => '1',
            'tp_ret_issqn' => '1',
            'aliquota_iss' => '2.00',
            'valor_servicos' => '100.00',
            'desconto' => '0.00',
            'iss' => '0.00',
            'total' => '100.00',
        ]);
        $nota->setRelation('empresa', $empresa);
        $nota->setRelation('itens', collect([
            new NfseItem($item + [
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
            'cnae' => '4520-0/01',
            'cidade_codigo' => '4101804',
            'uf' => 'PR',
        ]);
        $empresa->id = 1;

        return $empresa;
    }

    private function certificado(string $cnpj = '54644503000129'): Certificate
    {
        if ($cnpj === '54644503000129' && self::$certificado instanceof Certificate) {
            return self::$certificado;
        }

        $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];
        $cnf = base_path('tools/php/extras/ssl/openssl.cnf');

        if (is_file($cnf)) {
            $config['config'] = $cnf;
        }

        $chave = openssl_pkey_new($config);
        $csr = openssl_csr_new(['commonName' => 'EMPRESA TESTE:'.$cnpj], $chave, $config);
        $x509 = openssl_csr_sign($csr, null, $chave, 1, $config);
        openssl_x509_export($x509, $pem);
        openssl_pkey_export($chave, $chavePem, null, $config);
        $certificado = new Certificate($chavePem, $pem, $cnpj);

        if ($cnpj === '54644503000129') {
            self::$certificado = $certificado;
        }

        return $certificado;
    }
}
