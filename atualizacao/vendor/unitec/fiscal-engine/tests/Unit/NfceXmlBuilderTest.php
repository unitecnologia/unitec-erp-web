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
}
