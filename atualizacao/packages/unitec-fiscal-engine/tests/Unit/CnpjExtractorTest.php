<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Certificate\CnpjExtractor;

final class CnpjExtractorTest extends TestCase
{
    public function test_extrai_cnpj_do_subject_serial_number(): void
    {
        $parsed = [
            'subject' => [
                'CN' => 'EMPRESA TESTE LTDA',
                'serialNumber' => 'CNPJ: 22469772000100',
            ],
            'extensions' => [],
        ];

        $cnpj = CnpjExtractor::fromCertificatePem('-----BEGIN CERTIFICATE-----\n-----END CERTIFICATE-----', $parsed);

        $this->assertSame('22469772000100', $cnpj);
    }
}
