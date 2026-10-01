<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\EmitenteDto;
use Unitec\FiscalEngine\Dto\EmitirNfeRequest;
use Unitec\FiscalEngine\Dto\FaturaParcelaDto;
use Unitec\FiscalEngine\Dto\IdeDto;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Dto\NfeDestinatarioDto;
use Unitec\FiscalEngine\Dto\PagamentoDto;
use Unitec\FiscalEngine\Xml\NfeXmlBuilder;

final class NfeCobrancaXmlBuilderTest extends TestCase
{
    public function test_vliq_igual_soma_das_parcelas_cstat_851(): void
    {
        $builder = new NfeXmlBuilder;
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
                numero: 51,
                cNf: 12345678,
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
                    descricao: 'PRODUTO',
                    ncm: '84713012',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 10,
                    valorTotal: 10,
                    imposto: new ItemImpostoDto(origem: 0, csosn: '102', vTotTrib: 0),
                ),
            ],
            valorProdutos: 10,
            // valorNota propositalmente diferente da soma das parcelas (cenário do bug).
            valorNota: 9.97,
            idDest: 1,
            indFinal: 1,
            finNFe: 1,
            modFrete: 9,
            pagamentos: [new PagamentoDto('15', 10)],
            homologacao: true,
            parcelas: [
                new FaturaParcelaDto('001', new \DateTimeImmutable('2026-10-10'), 3.33),
                new FaturaParcelaDto('002', new \DateTimeImmutable('2026-11-10'), 3.33),
                new FaturaParcelaDto('003', new \DateTimeImmutable('2026-12-10'), 3.34),
            ],
        );

        $xml = $builder->finalizeNfeXml($builder->build($request)['dom']);

        $this->assertStringContainsString('<vOrig>10.00</vOrig>', $xml);
        $this->assertStringContainsString('<vLiq>10.00</vLiq>', $xml);
        $this->assertStringContainsString('<vDup>3.33</vDup>', $xml);
        $this->assertStringContainsString('<vDup>3.34</vDup>', $xml);
        $this->assertDoesNotMatchRegularExpression('/<vLiq>9\.97<\/vLiq>/', $xml);
    }
}
