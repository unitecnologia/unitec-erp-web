<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\EmitenteDto;
use Unitec\FiscalEngine\Dto\EmitirNfeRequest;
use Unitec\FiscalEngine\Dto\IdeDto;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Dto\NfeDestinatarioDto;
use Unitec\FiscalEngine\Dto\NfeTransporteDto;
use Unitec\FiscalEngine\Dto\PagamentoDto;
use Unitec\FiscalEngine\Xml\NfeXmlBuilder;

final class NfeIbscbsXmlBuilderTest extends TestCase
{
    public function test_inclui_grupo_ibscbs_no_item_e_totais(): void
    {
        $builder = new NfeXmlBuilder();
        $request = new EmitirNfeRequest(
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
                cNf: 12345678,
                tpAmb: 2,
                tpEmis: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-07-12T10:00:00-03:00'),
            ),
            destinatario: new NfeDestinatarioDto(
                cpf: '12345678909',
                cnpj: null,
                nome: 'CLIENTE TESTE',
                logradouro: 'RUA A',
                numero: '10',
                bairro: 'CENTRO',
                codigoMunicipio: '4205407',
                municipio: 'FLORIANOPOLIS',
                uf: 'SC',
                cep: '88000000',
                indIeDest: 9,
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '1',
                    descricao: 'PRODUTO IBS CBS',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 100,
                    valorTotal: 100,
                    imposto: new ItemImpostoDto(
                        origem: 0,
                        csosn: '102',
                        vTotTrib: 10,
                        cstIbsCbs: '000',
                        cClassTrib: '000001',
                        vBcIbscbs: 100,
                        pIbsUf: 0.1,
                        vIbsUf: 0.10,
                        pIbsMun: 0.0,
                        vIbsMun: 0.0,
                        pCbs: 0.9,
                        vCbs: 0.90,
                    ),
                ),
            ],
            valorProdutos: 100,
            valorNota: 100,
            idDest: 1,
            indFinal: 1,
            finNFe: 1,
            modFrete: 9,
            pagamentos: [new PagamentoDto('01', 100)],
            homologacao: true,
        );

        $built = $builder->build($request);
        $xml = $built['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<IBSCBS>', $xml);
        $this->assertStringContainsString('<CST>000</CST>', $xml);
        $this->assertStringContainsString('<cClassTrib>000001</cClassTrib>', $xml);
        $this->assertStringContainsString('<pIBSUF>0.1000</pIBSUF>', $xml);
        $this->assertStringContainsString('<vIBSUF>0.10</vIBSUF>', $xml);
        $this->assertStringContainsString('<pCBS>0.9000</pCBS>', $xml);
        $this->assertStringContainsString('<vCBS>0.90</vCBS>', $xml);
        $this->assertStringContainsString('<IBSCBSTot>', $xml);
        $this->assertStringContainsString('<vBCIBSCBS>100.00</vBCIBSCBS>', $xml);
        $this->assertStringContainsString('<gIBSUF><vDif>0.00</vDif><vDevTrib>0.00</vDevTrib><vIBSUF>0.10</vIBSUF></gIBSUF>', $xml);
        $this->assertStringContainsString('<gIBSMun><vDif>0.00</vDif><vDevTrib>0.00</vDevTrib><vIBSMun>0.00</vIBSMun></gIBSMun>', $xml);
        $this->assertStringContainsString('<gCBS><vDif>0.00</vDif><vDevTrib>0.00</vDevTrib><vCBS>0.90</vCBS>', $xml);
        $this->assertStringContainsString('<vIBS>0.10</vIBS>', $xml);
    }

    public function test_inclui_transporte_volumes_no_xml(): void
    {
        $builder = new NfeXmlBuilder();
        $request = new EmitirNfeRequest(
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
                cNf: 12345679,
                tpAmb: 2,
                tpEmis: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-07-12T10:00:00-03:00'),
            ),
            destinatario: new NfeDestinatarioDto(
                cpf: '12345678909',
                cnpj: null,
                nome: 'CLIENTE TESTE',
                logradouro: 'RUA A',
                numero: '10',
                bairro: 'CENTRO',
                codigoMunicipio: '4205407',
                municipio: 'FLORIANOPOLIS',
                uf: 'SC',
                cep: '88000000',
                indIeDest: 9,
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '1',
                    descricao: 'PRODUTO',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 100,
                    valorTotal: 100,
                    imposto: new ItemImpostoDto(
                        origem: 0,
                        csosn: '102',
                        vTotTrib: 0,
                    ),
                ),
            ],
            valorProdutos: 100,
            valorNota: 100,
            idDest: 1,
            indFinal: 1,
            finNFe: 1,
            modFrete: 1,
            pagamentos: [new PagamentoDto('01', 100)],
            homologacao: true,
            transporte: new NfeTransporteDto(
                transportadoraDocumento: '11222333000181',
                transportadoraNome: 'TRANSPORTADORA TESTE LTDA',
                transportadoraIe: '123456789',
                transportadoraEndereco: 'RUA FRETE, 50',
                transportadoraMunicipio: 'FLORIANOPOLIS',
                transportadoraUf: 'SC',
                placa: 'ABC1D23',
                ufPlaca: 'SC',
                qVol: 2,
                esp: 'CAIXAS',
                marca: 'UNITEC',
                pesoL: 10.5,
                pesoB: 12.0,
            ),
        );

        $built = $builder->build($request);
        $xml = $built['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<modFrete>1</modFrete>', $xml);
        $this->assertStringContainsString('<transporta>', $xml);
        $this->assertStringContainsString('<CNPJ>11222333000181</CNPJ>', $xml);
        $this->assertStringContainsString('<xNome>TRANSPORTADORA TESTE LTDA</xNome>', $xml);
        $this->assertStringContainsString('<veicTransp>', $xml);
        $this->assertStringContainsString('<placa>ABC1D23</placa>', $xml);
        $this->assertStringContainsString('<vol>', $xml);
        $this->assertStringContainsString('<qVol>2</qVol>', $xml);
        $this->assertStringContainsString('<esp>CAIXAS</esp>', $xml);
        $this->assertStringContainsString('<marca>UNITEC</marca>', $xml);
        $this->assertStringContainsString('<pesoL>10.500</pesoL>', $xml);
        $this->assertStringContainsString('<pesoB>12.000</pesoB>', $xml);
    }

    public function test_modfrete_9_nao_emite_grupo_transportador(): void
    {
        $builder = new NfeXmlBuilder();
        $request = new EmitirNfeRequest(
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
                cNf: 12345679,
                tpAmb: 2,
                tpEmis: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-07-12T10:00:00-03:00'),
            ),
            destinatario: new NfeDestinatarioDto(
                cpf: '12345678909',
                cnpj: null,
                nome: 'CLIENTE TESTE',
                logradouro: 'RUA A',
                numero: '10',
                bairro: 'CENTRO',
                codigoMunicipio: '4205407',
                municipio: 'FLORIANOPOLIS',
                uf: 'SC',
                cep: '88000000',
                indIeDest: 9,
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '1',
                    descricao: 'PRODUTO',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 100,
                    valorTotal: 100,
                    imposto: new ItemImpostoDto(origem: 0, csosn: '102', vTotTrib: 0),
                ),
            ],
            valorProdutos: 100,
            valorNota: 100,
            idDest: 1,
            indFinal: 1,
            finNFe: 1,
            modFrete: 9,
            pagamentos: [new PagamentoDto('01', 100)],
            homologacao: true,
            transporte: new NfeTransporteDto(
                transportadoraDocumento: '11222333000181',
                transportadoraNome: 'TRANSPORTADORA TESTE LTDA',
                placa: 'ABC1D23',
                ufPlaca: 'SC',
                qVol: 1,
                pesoL: 1.0,
                pesoB: 1.0,
            ),
        );

        $built = $builder->build($request);
        $xml = $built['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<modFrete>9</modFrete>', $xml);
        $this->assertStringNotContainsString('<transporta>', $xml);
        $this->assertStringNotContainsString('<veicTransp>', $xml);
        $this->assertStringContainsString('<vol>', $xml);
    }
}
