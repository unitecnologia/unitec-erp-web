<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\DestinatarioDto;
use Unitec\FiscalEngine\Dto\EmitenteDto;
use Unitec\FiscalEngine\Dto\EmitirNfceRequest;
use Unitec\FiscalEngine\Dto\IdeDto;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Dto\PagamentoDto;
use Unitec\FiscalEngine\Xml\NfceXmlBuilder;

final class NfceXmlBuilderTest extends TestCase
{
    public function test_monta_xml_nfce_com_itens(): void
    {
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
                numero: 1,
                cNf: 87654321,
                tpAmb: 2,
                tpEmis: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-07-06T14:30:00-03:00'),
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '1',
                    descricao: 'PRODUTO TESTE',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 10,
                    valorTotal: 10,
                    imposto: new ItemImpostoDto(origem: 0, csosn: '102'),
                ),
            ],
            pagamentos: [new PagamentoDto('01', 10)],
            valorProdutos: 10,
            valorNota: 10,
            destinatario: new DestinatarioDto(cpf: '12345678909', nome: 'JOAO DA SILVA TESTE'),
            idToken: '1',
            csc: 'FF1BFD3B-29D5-48F2-B472-5E76C281D283',
            homologacao: true,
        );

        $built = $builder->build($request);
        $xml = $built['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<mod>65</mod>', $xml);
        $this->assertStringContainsString('NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO', $xml);
        $this->assertStringContainsString('NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL', $xml);
        $this->assertStringNotContainsString('JOAO DA SILVA TESTE', $xml);
        $this->assertStringContainsString('<ICMSSN102>', $xml);
        $this->assertSame(44, strlen($built['chave']));
    }

    public function test_inclui_inf_resp_tec_quando_informado(): void
    {
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
                numero: 1,
                cNf: 87654321,
                tpAmb: 2,
                tpEmis: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-07-06T14:30:00-03:00'),
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '1',
                    descricao: 'PRODUTO TESTE',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 10,
                    valorTotal: 10,
                    imposto: new ItemImpostoDto(origem: 0, csosn: '102'),
                ),
            ],
            pagamentos: [new PagamentoDto('01', 10)],
            valorProdutos: 10,
            valorNota: 10,
            respTecnico: new \Unitec\FiscalEngine\Dto\RespTecnicoDto(
                cnpj: '22469772000100',
                contato: 'SUPORTE TECNICO',
                email: 'suporte@unitec.com.br',
                fone: '4833334444',
            ),
        );

        $built = $builder->build($request);
        $xml = $built['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<infRespTec>', $xml);
        $this->assertStringContainsString('<xContato>SUPORTE TECNICO</xContato>', $xml);
        $this->assertStringContainsString('<email>suporte@unitec.com.br</email>', $xml);
    }

    public function test_inclui_dhcont_e_xjust_em_contingencia(): void
    {
        $builder = new NfceXmlBuilder();
        $emissao = new \DateTimeImmutable('2026-07-06T14:30:00-03:00');
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
                numero: 2,
                cNf: 87654321,
                tpAmb: 2,
                tpEmis: 9,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: $emissao,
                justificativaContingencia: 'SEFAZ indisponivel para autorizacao da NFC-e em tempo real',
                dataContingencia: $emissao,
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '1',
                    descricao: 'PRODUTO',
                    ncm: '12345678',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 10,
                    valorTotal: 10,
                    imposto: new ItemImpostoDto(origem: 0, csosn: '102'),
                ),
            ],
            pagamentos: [new PagamentoDto('01', 10)],
            valorProdutos: 10,
            valorNota: 10,
        );

        $built = $builder->build($request);
        $xml = $built['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<tpEmis>9</tpEmis>', $xml);
        $this->assertStringContainsString('<verProc>UnitecERP-1.0</verProc>', $xml);
        $this->assertStringContainsString('<dhCont>2026-07-06T14:30:00-03:00</dhCont>', $xml);
        $this->assertStringContainsString('<xJust>SEFAZ indisponivel para autorizacao da NFC-e em tempo real</xJust>', $xml);
        $this->assertLessThan(
            strpos($xml, '<dhCont>'),
            strpos($xml, '<verProc>'),
            'dhCont deve vir após verProc no grupo ide',
        );
    }

    public function test_inclui_nome_e_endereco_do_consumidor_identificado(): void
    {
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
                numero: 1,
                cNf: 87654321,
                tpAmb: 1,
                tpEmis: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-07-06T14:30:00-03:00'),
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '1',
                    descricao: 'PRODUTO TESTE',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 10,
                    valorTotal: 10,
                    imposto: new ItemImpostoDto(origem: 0, csosn: '102'),
                ),
            ],
            pagamentos: [new PagamentoDto('01', 10)],
            valorProdutos: 10,
            valorNota: 10,
            destinatario: new DestinatarioDto(
                cpf: '04533323901',
                nome: 'MARIA SILVA',
                logradouro: 'RUA DAS PALMEIRAS',
                numero: '120',
                bairro: 'CENTRO',
                codigoMunicipio: '4205407',
                municipio: 'FLORIANOPOLIS',
                uf: 'SC',
                cep: '88015000',
            ),
            idToken: '1',
            csc: 'FF1BFD3B-29D5-48F2-B472-5E76C281D283',
            homologacao: false,
        );

        $built = $builder->build($request);
        $xml = $built['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<CPF>04533323901</CPF>', $xml);
        $this->assertStringContainsString('<xNome>MARIA SILVA</xNome>', $xml);
        $this->assertStringContainsString('<enderDest>', $xml);
        $this->assertStringContainsString('<xLgr>RUA DAS PALMEIRAS</xLgr>', $xml);
        $this->assertStringContainsString('<CEP>88015000</CEP>', $xml);
    }

    public function test_crt3_emite_icms_cst_e_pis_cofins_aliq_sem_csosn(): void
    {
        $builder = new NfceXmlBuilder();
        $request = new EmitirNfceRequest(
            certificate: new Certificate('key', 'cert', '22469772000100'),
            emitente: new EmitenteDto(
                cnpj: '22469772000100',
                razaoSocial: 'EMPRESA PRESUMIDO LTDA',
                nomeFantasia: 'PRESUMIDO',
                ie: '255000000',
                crt: 3,
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
                numero: 10,
                cNf: 11223344,
                tpAmb: 2,
                tpEmis: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-10-27T10:00:00-03:00'),
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '1',
                    descricao: 'PRODUTO TRIBUTADO',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 100,
                    valorTotal: 100,
                    imposto: new ItemImpostoDto(
                        origem: 0,
                        csosn: '102',
                        vBc: 100,
                        vIcms: 17,
                        vPis: 0.65,
                        vCofins: 3.0,
                        pIcms: 17,
                        cstIcms: '00',
                        pPis: 0.65,
                        vBcPis: 100,
                        cstPis: '01',
                        pCofins: 3.0,
                        vBcCofins: 100,
                        cstCofins: '01',
                        crt: 3,
                    ),
                ),
            ],
            pagamentos: [new PagamentoDto('01', 100)],
            valorProdutos: 100,
            valorNota: 100,
            homologacao: true,
        );

        $built = $builder->build($request);
        $xml = $built['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<CRT>3</CRT>', $xml);
        $this->assertStringContainsString('<ICMS00>', $xml);
        $this->assertStringContainsString('<CST>00</CST>', $xml);
        $this->assertStringContainsString('<PISAliq>', $xml);
        $this->assertStringContainsString('<pPIS>0.6500</pPIS>', $xml);
        $this->assertStringContainsString('<COFINSAliq>', $xml);
        $this->assertStringContainsString('<pCOFINS>3.0000</pCOFINS>', $xml);
        $this->assertStringNotContainsString('<ICMSSN', $xml);
        $this->assertStringNotContainsString('<CSOSN>', $xml);
        $this->assertStringNotContainsString('<PISNT><CST>07</CST>', $xml);
        $this->assertSame(17.0, $built['valorIcms']);
    }

    public function test_crt3_monofasico_emite_pisnt_sem_forcar_07(): void
    {
        $builder = new NfceXmlBuilder();
        $request = new EmitirNfceRequest(
            certificate: new Certificate('key', 'cert', '22469772000100'),
            emitente: new EmitenteDto(
                cnpj: '22469772000100',
                razaoSocial: 'EMPRESA PRESUMIDO LTDA',
                nomeFantasia: 'PRESUMIDO',
                ie: '255000000',
                crt: 3,
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
                numero: 11,
                cNf: 11223345,
                tpAmb: 2,
                tpEmis: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-10-27T10:00:00-03:00'),
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '2',
                    descricao: 'PRODUTO MONOFASICO',
                    ncm: '22021000',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 10,
                    valorTotal: 10,
                    imposto: new ItemImpostoDto(
                        origem: 0,
                        csosn: '102',
                        vBc: 10,
                        vIcms: 1.7,
                        pIcms: 17,
                        cstIcms: '00',
                        cstPis: '04',
                        cstCofins: '04',
                        crt: 3,
                    ),
                ),
            ],
            pagamentos: [new PagamentoDto('01', 10)],
            valorProdutos: 10,
            valorNota: 10,
            homologacao: true,
        );

        $xml = (new NfceXmlBuilder())->build($request)['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<PISNT><CST>04</CST></PISNT>', $xml);
        $this->assertStringContainsString('<COFINSNT><CST>04</CST></COFINSNT>', $xml);
        $this->assertStringNotContainsString('<PISNT><CST>07</CST>', $xml);
    }
}
