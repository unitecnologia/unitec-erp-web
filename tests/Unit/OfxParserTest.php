<?php

namespace Tests\Unit;

use App\Support\Erp\Financeiro\Conciliacao\OfxParser;
use InvalidArgumentException;
use Tests\TestCase;

class OfxParserTest extends TestCase
{
    public function test_parse_sgml_basico(): void
    {
        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:UTF-8
CHARSET:UTF-8
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<BANKMSGSRSV1>
<STMTTRNRS>
<STMTRS>
<BANKACCTFROM>
<BANKID>085
<BRANCHID>0001
<ACCTID>12345
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20261101
<DTEND>20261113
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20261110
<TRNAMT>50.00
<FITID>FIT-A1
<NAME>PIX RECEBIDO UM
</STMTTRN>
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20261111
<TRNAMT>-12.50
<FITID>FIT-B2
<NAME>TARIFA
<MEMO>Pacote
</STMTTRN>
</BANKTRANLIST>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $result = (new OfxParser)->parse($ofx, 'teste.ofx');

        $this->assertSame('085', $result->bankId);
        $this->assertSame('12345', $result->accountId);
        $this->assertCount(2, $result->transactions);
        $this->assertSame('credito', $result->transactions[0]->tipo);
        $this->assertSame('50.00', $result->transactions[0]->valor);
        $this->assertSame('debito', $result->transactions[1]->tipo);
        $this->assertSame('12.50', $result->transactions[1]->valor);
        $this->assertSame('50.00', $result->totalCreditos());
        $this->assertSame('12.50', $result->totalDebitos());
        $this->assertSame('2026-11-10', $result->transactions[0]->dataMovimento);
        $this->assertSame('2026-11-10', $result->transactions[0]->dataPosted);
    }

    public function test_dtposted_com_hora_ailos_preserva_horario(): void
    {
        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
CHARSET:UTF-8

<OFX>
<BANKTRANLIST>
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20261110154522[-3:BRT]
<TRNAMT>10.00
<FITID>AILOS-H
<NAME>PIX RECEBIDO
</STMTTRN>
</BANKTRANLIST>
</OFX>
OFX;

        $tx = (new OfxParser)->parse($ofx, 'ailos.ofx')->transactions[0];
        $this->assertSame('2026-11-10 15:45:22', $tx->dataMovimento);
        $this->assertSame('2026-11-10 15:45:22', $tx->dataPosted);
    }

    public function test_dtposted_meia_noite_nao_inventa_horario(): void
    {
        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
CHARSET:UTF-8

<OFX>
<BANKTRANLIST>
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20261110000000[-3:BRT]
<TRNAMT>10.00
<FITID>AILOS-0
<NAME>PIX
</STMTTRN>
</BANKTRANLIST>
</OFX>
OFX;

        $tx = (new OfxParser)->parse($ofx, 'ailos0.ofx')->transactions[0];
        $this->assertSame('2026-11-10', $tx->dataMovimento);
    }

    public function test_parse_xml_com_fechamento(): void
    {
        $ofx = <<<'OFX'
<?xml version="1.0" encoding="UTF-8"?>
<OFX>
  <BANKMSGSRSV1>
    <STMTTRNRS>
      <STMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>CREDIT</TRNTYPE>
            <DTPOSTED>20261105</DTPOSTED>
            <TRNAMT>10.00</TRNAMT>
            <FITID>XML-1</FITID>
            <NAME>TESTE XML</NAME>
          </STMTTRN>
        </BANKTRANLIST>
      </STMTRS>
    </STMTTRNRS>
  </BANKMSGSRSV1>
</OFX>
OFX;

        $result = (new OfxParser)->parse($ofx, 'xml.ofx');
        $this->assertSame('xml', $result->dialect);
        $this->assertSame('XML-1', $result->transactions[0]->fitid);
    }

    public function test_arquivo_vazio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new OfxParser)->parse('', 'vazio.ofx');
    }

    public function test_arquivo_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new OfxParser)->parse('isto nao e ofx', 'x.ofx');
    }

    public function test_caracteres_especiais_windows1252(): void
    {
        $body = "<OFX><BANKTRANLIST><STMTTRN>\n"
            ."<TRNTYPE>CREDIT\n<DTPOSTED>20261101\n<TRNAMT>1.00\n"
            ."<FITID>ACENTO\n<NAME>Pagamento Jo".chr(227)."o\n"
            ."</STMTTRN></BANKTRANLIST></OFX>";

        $raw = "OFXHEADER:100\nDATA:OFXSGML\nVERSION:102\nCHARSET:1252\n\n".$body;
        $result = (new OfxParser)->parse($raw, 'acentos.ofx');

        $this->assertStringContainsString('Jo', (string) $result->transactions[0]->historico);
    }

    public function test_sem_fitid_marca_ambiguo_se_sem_historico(): void
    {
        $ofx = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
CHARSET:UTF-8

<OFX>
<BANKTRANLIST>
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20261101
<TRNAMT>9.99
</STMTTRN>
</BANKTRANLIST>
</OFX>
OFX;

        $result = (new OfxParser)->parse($ofx, 'nofitid.ofx');
        $this->assertNull($result->transactions[0]->fitid);
        $this->assertTrue($result->transactions[0]->ambiguo);
    }
}
