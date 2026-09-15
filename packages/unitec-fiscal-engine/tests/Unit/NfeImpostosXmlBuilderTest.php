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
use Unitec\FiscalEngine\Dto\PagamentoDto;
use Unitec\FiscalEngine\Xml\NfeXmlBuilder;

final class NfeImpostosXmlBuilderTest extends TestCase
{
    public function test_inclui_icms_ipi_pis_cofins_no_item_e_totais(): void
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
                numero: 3,
                cNf: 12345680,
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
                    descricao: 'PRODUTO IMPOSTOS',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 120,
                    valorTotal: 120,
                    imposto: new ItemImpostoDto(
                        origem: 0,
                        csosn: '102',
                        vBc: 120,
                        vIcms: 14.4,
                        vPis: 1.98,
                        vCofins: 9.12,
                        vTotTrib: 0,
                        pIcms: 12,
                        pPis: 1.65,
                        vBcPis: 120,
                        cstPis: '01',
                        pCofins: 7.6,
                        vBcCofins: 120,
                        cstCofins: '01',
                        vIpi: 14.4,
                        pIpi: 12,
                        vBcIpi: 120,
                        cstIpi: '99',
                        crt: 1,
                    ),
                ),
            ],
            valorProdutos: 120,
            valorNota: 120,
            idDest: 1,
            indFinal: 1,
            finNFe: 1,
            modFrete: 9,
            pagamentos: [new PagamentoDto('01', 120)],
            homologacao: true,
        );

        $built = $builder->build($request);
        $xml = $built['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<ICMSSN900>', $xml);
        $this->assertStringContainsString('<modBC>3</modBC>', $xml);
        $this->assertStringContainsString('<vBC>120.00</vBC>', $xml);
        $this->assertStringContainsString('<pICMS>12.0000</pICMS>', $xml);
        $this->assertStringContainsString('<vICMS>14.40</vICMS>', $xml);
        $this->assertStringContainsString('<IPITrib>', $xml);
        $this->assertStringContainsString('<vIPI>14.40</vIPI>', $xml);
        $this->assertStringContainsString('<PISAliq>', $xml);
        $this->assertStringContainsString('<vPIS>1.98</vPIS>', $xml);
        $this->assertStringContainsString('<COFINSAliq>', $xml);
        $this->assertStringContainsString('<vCOFINS>9.12</vCOFINS>', $xml);
        $this->assertStringContainsString('<vPIS>1.98</vPIS>', $xml);
        $this->assertSame(14.4, $built['valorIcms']);
    }

    public function test_icmssn900_nao_emite_campos_icms_parciais(): void
    {
        $builder = new NfeXmlBuilder();
        $request = $this->makeSimpleNfeRequest(new ItemImpostoDto(
            origem: 0,
            csosn: '102',
            vBc: 0,
            vIcms: 0,
            pIcms: 12,
            crt: 1,
        ));

        $built = $builder->build($request);
        $xml = $built['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<ICMSSN900>', $xml);
        $this->assertStringNotContainsString('<modBC>', $xml);
        $this->assertStringNotContainsString('<pICMS>', $xml);
        $this->assertStringNotContainsString('<vICMS>', $xml);
    }

    public function test_icmssn102_com_base_sem_icms_nao_soma_vbc_no_total(): void
    {
        $builder = new NfeXmlBuilder();
        $request = $this->makeSimpleNfeRequest(new ItemImpostoDto(
            origem: 0,
            csosn: '102',
            vBc: 45.5,
            vIcms: 0,
            pIcms: 0,
            crt: 1,
        ));

        $built = $builder->build($request);
        $xml = $built['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<ICMSSN900>', $xml);
        $this->assertStringNotContainsString('<modBC>', $xml);
        $this->assertMatchesRegularExpression(
            '/<ICMSTot>.*?<vBC>0\.00<\/vBC>.*?<\/ICMSTot>/s',
            $xml,
        );
        $this->assertSame(0.0, $built['valorIcms']);
    }

    /**
     * @param  list<ItemDto>|ItemImpostoDto  $itensOrImposto
     */
    private function makeSimpleNfeRequest(ItemImpostoDto|array $itensOrImposto): EmitirNfeRequest
    {
        $imposto = $itensOrImposto instanceof ItemImpostoDto
            ? $itensOrImposto
            : $itensOrImposto[0]->imposto;

        return new EmitirNfeRequest(
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
                numero: 3,
                cNf: 12345680,
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
                    descricao: 'PRODUTO IMPOSTOS',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 120,
                    valorTotal: 120,
                    imposto: $imposto,
                ),
            ],
            valorProdutos: 120,
            valorNota: 120,
            idDest: 1,
            indFinal: 1,
            finNFe: 1,
            modFrete: 9,
            pagamentos: [new PagamentoDto('01', 120)],
            homologacao: true,
        );
    }
}
