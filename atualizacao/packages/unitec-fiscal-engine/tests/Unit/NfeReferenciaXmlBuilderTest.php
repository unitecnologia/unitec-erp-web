<?php

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\EmitenteDto;
use Unitec\FiscalEngine\Dto\EmitirNfeRequest;
use Unitec\FiscalEngine\Dto\IdeDto;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\NfeDestinatarioDto;
use Unitec\FiscalEngine\Dto\PagamentoDto;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Xml\NfeXmlBuilder;

final class NfeReferenciaXmlBuilderTest extends TestCase
{
    public function test_devolucao_inclui_nfref_no_ide(): void
    {
        $chaveOriginal = '42260122469772000100550010000000011000000019';

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
                numero: 9,
                cNf: 12345689,
                tpAmb: 2,
                tpEmis: 1,
                natOp: 'DEVOLUCAO DE COMPRA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-08-07T10:00:00-03:00'),
            ),
            destinatario: new NfeDestinatarioDto(
                cpf: null,
                cnpj: '12345678000195',
                nome: 'FORNECEDOR TESTE',
                logradouro: 'RUA A',
                numero: '10',
                bairro: 'CENTRO',
                codigoMunicipio: '4205407',
                municipio: 'FLORIANOPOLIS',
                uf: 'SC',
                cep: '88000000',
                indIeDest: 1,
                ie: '255000001',
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '17',
                    descricao: 'PRODUTO TESTE',
                    ncm: '95030099',
                    cfop: '5202',
                    unidade: 'UN',
                    quantidade: 10,
                    valorUnitario: 45.5,
                    valorTotal: 455,
                    imposto: new ItemImpostoDto(origem: 0, csosn: '102', vTotTrib: 0),
                ),
            ],
            valorProdutos: 455,
            valorNota: 455,
            idDest: 1,
            indFinal: 0,
            finNFe: 4,
            modFrete: 9,
            pagamentos: [new PagamentoDto('01', 455)],
            homologacao: true,
            chavesReferenciadas: [$chaveOriginal],
        );

        $builder = new NfeXmlBuilder();
        $result = $builder->build($request);
        $xml = $builder->finalizeNfeXml($result['dom']);

        $this->assertStringContainsString('<finNFe>4</finNFe>', $xml);
        $this->assertStringContainsString('<NFref>', $xml);
        $this->assertStringContainsString('<refNFe>'.$chaveOriginal.'</refNFe>', $xml);
    }
}
