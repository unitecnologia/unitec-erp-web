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

final class NfeTpNfXmlBuilderTest extends TestCase
{
    public function test_movimento_entrada_gera_tpnf_0(): void
    {
        $xml = $this->finalize($this->makeRequest(tpNF: 0, cfop: '1102', natOp: 'COMPRA PARA COMERCIALIZACAO'));

        $this->assertStringContainsString('<tpNF>0</tpNF>', $xml);
        $this->assertStringNotContainsString('<tpNF>1</tpNF>', $xml);
        $this->assertStringContainsString('<CFOP>1102</CFOP>', $xml);
    }

    public function test_movimento_saida_gera_tpnf_1(): void
    {
        $xml = $this->finalize($this->makeRequest(tpNF: 1, cfop: '5102', natOp: 'VENDA'));

        $this->assertStringContainsString('<tpNF>1</tpNF>', $xml);
        $this->assertStringNotContainsString('<tpNF>0</tpNF>', $xml);
        $this->assertStringContainsString('<CFOP>5102</CFOP>', $xml);
    }

    public function test_default_sem_tpnf_explicito_permanece_saida(): void
    {
        // Omite tpNF para exercer o default do DTO (= 1).
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
                numero: 52,
                cNf: 12345692,
                tpAmb: 2,
                tpEmis: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-11-03T11:00:00-03:00'),
            ),
            destinatario: new NfeDestinatarioDto(
                cpf: null,
                cnpj: '12345678000195',
                nome: 'CLIENTE TESTE',
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
                    codigo: '1',
                    descricao: 'PRODUTO TESTE',
                    ncm: '87089990',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 100,
                    valorTotal: 100,
                    imposto: new ItemImpostoDto(
                        origem: 0,
                        csosn: '102',
                        vTotTrib: 0,
                        cstPis: '07',
                        cstCofins: '07',
                    ),
                ),
            ],
            valorProdutos: 100.0,
            valorNota: 100.0,
            idDest: 1,
            indFinal: 0,
            finNFe: 1,
            modFrete: 9,
            pagamentos: [new PagamentoDto('01', 100.0)],
            homologacao: true,
        );

        $xml = $this->finalize($request);

        $this->assertSame(1, $request->tpNF);
        $this->assertStringContainsString('<tpNF>1</tpNF>', $xml);
        $this->assertStringNotContainsString('<tpNF>0</tpNF>', $xml);
    }

    private function makeRequest(int $tpNF, string $cfop, string $natOp): EmitirNfeRequest
    {
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
                numero: 52,
                cNf: 12345692,
                tpAmb: 2,
                tpEmis: 1,
                natOp: $natOp,
                codigoMunicipioFg: '4205407',
                dataEmissao: new \DateTimeImmutable('2026-11-03T11:00:00-03:00'),
            ),
            destinatario: new NfeDestinatarioDto(
                cpf: null,
                cnpj: '12345678000195',
                nome: 'DESTINATARIO TESTE',
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
                    codigo: '1',
                    descricao: 'PRODUTO TESTE',
                    ncm: '87089990',
                    cfop: $cfop,
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 100,
                    valorTotal: 100,
                    imposto: new ItemImpostoDto(
                        origem: 0,
                        csosn: '102',
                        vTotTrib: 0,
                        cstPis: '07',
                        cstCofins: '07',
                    ),
                ),
            ],
            valorProdutos: 100.0,
            valorNota: 100.0,
            idDest: 1,
            indFinal: 0,
            finNFe: 1,
            modFrete: 9,
            pagamentos: [new PagamentoDto('01', 100.0)],
            homologacao: true,
            tpNF: $tpNF,
        );
    }

    private function finalize(EmitirNfeRequest $request): string
    {
        $builder = new NfeXmlBuilder();
        $built = $builder->build($request);

        return $builder->finalizeNfeXml($built['dom']);
    }
}
