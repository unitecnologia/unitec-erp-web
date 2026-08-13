<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Nfce\ScNfceEndpoints;

final class ScNfceEndpointsTest extends TestCase
{
    public function test_urls_homologacao_svrs_e_sat_sc(): void
    {
        $this->assertSame(
            'https://nfce-homologacao.svrs.rs.gov.br/ws/NfeAutorizacao/NFeAutorizacao4.asmx',
            ScNfceEndpoints::autorizacao(2),
        );

        $this->assertSame(
            'https://hom.sat.sef.sc.gov.br/nfce/consulta',
            ScNfceEndpoints::consultaQrCode(2),
        );

        $this->assertSame(
            'https://nfce-homologacao.svrs.rs.gov.br/ws/NfeConsultaProtocolo/NFeConsultaProtocolo4.asmx',
            ScNfceEndpoints::consultaProtocolo(2),
        );

        $this->assertSame(
            'https://nfce-homologacao.svrs.rs.gov.br/ws/NfeInutilizacao/NFeInutilizacao4.asmx',
            ScNfceEndpoints::inutilizacao(2),
        );
    }
}
