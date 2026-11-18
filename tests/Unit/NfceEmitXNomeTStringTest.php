<?php

namespace Tests\Unit;

use Tests\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\EmitenteDto;
use Unitec\FiscalEngine\Dto\EmitirNfceRequest;
use Unitec\FiscalEngine\Dto\IdeDto;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Dto\PagamentoDto;
use Unitec\FiscalEngine\Util\XmlHelper;
use Unitec\FiscalEngine\Xml\NfceXmlBuilder;

/**
 * emit/xNome: TString SEFAZ (Latin-1). Sem transmissão.
 */
class NfceEmitXNomeTStringTest extends TestCase
{
    public function test_sanitize_tstring_repara_o_mojibake_de_confeccoes(): void
    {
        // Cadastro DEV corrompido: Ő (U+0150) no lugar de Õ (U+00D5).
        $origem = "JONITAM CONFECÇ\u{0150}ES LTDA";

        $this->assertSame(0x0150, mb_ord(mb_substr($origem, 15, 1, 'UTF-8'), 'UTF-8'));

        $sanitizado = XmlHelper::sanitizeTString($origem, 60);

        $this->assertSame('JONITAM CONFECÇÕES LTDA', $sanitizado);
        $this->assertSame(0x00D5, mb_ord(mb_substr($sanitizado, 15, 1, 'UTF-8'), 'UTF-8'));
    }

    public function test_xml_nfce_emit_xnome_nao_carrega_codepoints_fora_do_tstring(): void
    {
        $builder = new NfceXmlBuilder();
        $request = new EmitirNfceRequest(
            certificate: new Certificate('key', 'cert', '22469772000100'),
            emitente: new EmitenteDto(
                cnpj: '22469772000100',
                razaoSocial: "JONITAM CONFECÇ\u{0150}ES LTDA",
                nomeFantasia: "JONITAM FACÇ\u{0102}O",
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
                dataEmissao: new \DateTimeImmutable('2026-11-18T10:00:00-03:00'),
            ),
            itens: [
                new ItemDto(
                    numero: 1,
                    codigo: '1',
                    descricao: 'PRODUTO',
                    ncm: '61091000',
                    cfop: '5102',
                    unidade: 'UN',
                    quantidade: 1,
                    valorUnitario: 10,
                    valorTotal: 10,
                    imposto: new ItemImpostoDto(
                        origem: 0,
                        csosn: '102',
                        cstPis: '49',
                        cstCofins: '49',
                    ),
                ),
            ],
            pagamentos: [new PagamentoDto('01', 10)],
            valorProdutos: 10,
            valorNota: 10,
            idToken: '1',
            csc: 'FF1BFD3B-29D5-48F2-B472-5E76C281D283',
            homologacao: true,
        );

        $xml = $builder->build($request)['dom']->saveXML() ?: '';

        $this->assertStringContainsString('<xNome>JONITAM CONFECÇÕES LTDA</xNome>', $xml);
        $this->assertStringContainsString('<xFant>JONITAM FACÇÃO</xFant>', $xml);
        $this->assertStringNotContainsString("\u{0150}", $xml);
        $this->assertStringNotContainsString("\u{0102}", $xml);
    }

    public function test_sanitize_text_antigo_ainda_deixaria_o_caractere_invalido(): void
    {
        $origem = "JONITAM CONFECÇ\u{0150}ES LTDA";

        $this->assertStringContainsString("\u{0150}", XmlHelper::sanitizeText($origem, 60));
        $this->assertStringNotContainsString("\u{0150}", XmlHelper::sanitizeTString($origem, 60));
    }
}
