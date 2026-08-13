<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Nfe\ScNfeEndpoints;

final class ScNfeEndpointsTest extends TestCase
{
    public function test_consulta_protocolo_usa_endpoint_nfe_svrs(): void
    {
        $this->assertSame(
            'https://nfe-homologacao.svrs.rs.gov.br/ws/NfeConsultaProtocolo/NFeConsultaProtocolo4.asmx',
            ScNfeEndpoints::consultaProtocolo(2),
        );

        $this->assertSame(
            'https://nfe.svrs.rs.gov.br/ws/NfeConsultaProtocolo/NFeConsultaProtocolo4.asmx',
            ScNfeEndpoints::consultaProtocolo(1),
        );
    }
}
