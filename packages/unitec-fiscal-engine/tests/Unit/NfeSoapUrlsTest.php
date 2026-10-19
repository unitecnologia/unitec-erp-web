<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Nfe\NfeSoapUrls;

final class NfeSoapUrlsTest extends TestCase
{
    public function test_pr_usa_sefaz_pr_producao(): void
    {
        $this->assertSame(
            'https://nfe.sefa.pr.gov.br/nfe/NFeAutorizacao4',
            NfeSoapUrls::autorizacao('PR', 1),
        );
        $this->assertSame(
            'https://nfe.sefa.pr.gov.br/nfe/NFeConsultaProtocolo4',
            NfeSoapUrls::consultaProtocolo('41', 1),
        );
    }

    public function test_sc_continua_svrs(): void
    {
        $this->assertSame(
            'https://nfe.svrs.rs.gov.br/ws/NfeAutorizacao/NFeAutorizacao4.asmx',
            NfeSoapUrls::autorizacao('SC', 1),
        );
    }
}
