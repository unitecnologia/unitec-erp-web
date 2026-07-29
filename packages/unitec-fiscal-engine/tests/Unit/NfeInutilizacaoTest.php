<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Dto\InutilizarNfeRequest;
use Unitec\FiscalEngine\Xml\NfceInutilizacaoXmlBuilder;

final class NfeInutilizacaoTest extends TestCase
{
    public function test_monta_xml_inutilizacao_nfe_modelo_55_com_id_correto(): void
    {
        $builder = new NfceInutilizacaoXmlBuilder();
        $certificate = new \Unitec\FiscalEngine\Certificate\Certificate('key', 'cert', '22469772000100');
        $request = new InutilizarNfeRequest(
            certificate: $certificate,
            cnpj: '22469772000100',
            tpAmb: 1,
            serie: 1,
            numeroInicial: 10,
            numeroFinal: 12,
            justificativa: 'Quebra de sequencia por falha operacional na emissao',
            dataEvento: new \DateTimeImmutable('2026-07-06'),
        );

        $built = $builder->build(new \Unitec\FiscalEngine\Dto\InutilizarNfceRequest(
            certificate: $request->certificate,
            cnpj: $request->cnpj,
            tpAmb: $request->tpAmb,
            serie: $request->serie,
            numeroInicial: $request->numeroInicial,
            numeroFinal: $request->numeroFinal,
            justificativa: $request->justificativa,
            modelo: $request->modelo,
            dataEvento: $request->dataEvento,
        ));
        $xml = $builder->finalizeInutNfe($built['dom']);

        $this->assertStringContainsString('<mod>55</mod>', $xml);
        $this->assertStringContainsString('<xServ>INUTILIZAR</xServ>', $xml);
        $this->assertStringContainsString('<nNFIni>10</nNFIni>', $xml);
        $this->assertStringContainsString('<nNFFin>12</nNFFin>', $xml);
        $this->assertStringStartsWith('ID42262246977200010055001000000010000000012', $built['infInutId']);
    }
}
