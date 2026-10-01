<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\EmitenteDto;
use Unitec\FiscalEngine\Dto\EmitirNfeRequest;
use Unitec\FiscalEngine\Dto\IdeDto;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Dto\NfeDestinatarioDto;
use Unitec\FiscalEngine\Dto\PagamentoDto;
use Unitec\FiscalEngine\Xml\NfeXmlBuilder;
use Unitec\FiscalEngine\Xml\NfeXmlSchemaGuard;

final class NfeInfAdProdXmlBuilderTest extends TestCase
{
    public function test_inf_ad_prod_fica_em_det_depois_de_imposto_nao_em_prod(): void
    {
        $xml = $this->xmlComInfoAdicionais('Lote 12 validade 2026');

        $this->assertDoesNotMatchRegularExpression('/<prod>[\s\S]*?<infAdProd>[\s\S]*?<\/prod>/', $xml);
        $this->assertMatchesRegularExpression('/<\/imposto><infAdProd>Lote 12 validade 2026<\/infAdProd>/', $xml);
    }

    public function test_omite_inf_ad_prod_quando_vazio(): void
    {
        $xml = $this->xmlComInfoAdicionais(null);

        $this->assertStringNotContainsString('<infAdProd', $xml);
    }

    public function test_schema_guard_rejeita_inf_ad_prod_dentro_de_prod(): void
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML(
            '<NFe xmlns="http://www.portalfiscal.inf.br/nfe">'
            . '<infNFe><det nItem="1"><prod><infAdProd>x</infAdProd></prod><imposto/></det></infNFe>'
            . '</NFe>',
        );

        $this->expectException(\Unitec\FiscalEngine\Exception\FiscalEngineException::class);
        $this->expectExceptionMessage('infAdProd deve ser filho de det');

        NfeXmlSchemaGuard::assertDetLayout($dom);
    }

    public function test_schema_guard_aceita_imposto_devol_e_obs_item(): void
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML(
            '<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe>'
            . '<det nItem="1">'
            . '<prod><cProd>1</cProd></prod>'
            . '<imposto><vTotTrib>0.00</vTotTrib></imposto>'
            . '<impostoDevol><pDevol>10.00</pDevol></impostoDevol>'
            . '<infAdProd>Lote 1</infAdProd>'
            . '<obsItem><obsCont xCampo="x"><xTexto>y</xTexto></obsCont></obsItem>'
            . '<DFeReferenciado><chaveAcesso>42260122469772000100550010000000011000000019</chaveAcesso></DFeReferenciado>'
            . '</det></infNFe></NFe>',
        );

        NfeXmlSchemaGuard::assertDetLayout($dom);
        $this->addToAssertionCount(1);
    }

    public function test_schema_guard_aceita_det_sem_inf_ad_prod_com_obs_item(): void
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML(
            '<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe>'
            . '<det nItem="1">'
            . '<prod><cProd>1</cProd></prod>'
            . '<imposto/>'
            . '<obsItem><obsCont xCampo="x"><xTexto>y</xTexto></obsCont></obsItem>'
            . '</det></infNFe></NFe>',
        );

        NfeXmlSchemaGuard::assertDetLayout($dom);
        $this->addToAssertionCount(1);
    }

    public function test_assert_det_layout_xml_valida_copia_sem_exigir_grupos_opcionais(): void
    {
        $xml = '<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe>'
            . '<det nItem="1"><prod/><imposto/><infAdProd>ok</infAdProd></det>'
            . '</infNFe></NFe>';

        NfeXmlSchemaGuard::assertDetLayoutXml($xml);
        $this->addToAssertionCount(1);
    }

    private function xmlComInfoAdicionais(?string $infoAdicionais): string
    {
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
                numero: 11,
                cNf: 12345691,
                tpAmb: 2,
                tpEmis: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-09-10T10:00:00-03:00'),
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
                    descricao: 'PRODUTO TESTE',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 10,
                    valorTotal: 10,
                    imposto: new ItemImpostoDto(origem: 0, csosn: '102', vTotTrib: 0),
                    infoAdicionais: $infoAdicionais,
                ),
            ],
            valorProdutos: 10,
            valorNota: 10,
            idDest: 1,
            indFinal: 1,
            finNFe: 1,
            modFrete: 9,
            pagamentos: [new PagamentoDto('01', 10)],
            homologacao: true,
        );

        $built = (new NfeXmlBuilder)->build($request);

        return $built['dom']->saveXML() ?: '';
    }
}
