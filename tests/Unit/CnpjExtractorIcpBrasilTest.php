<?php

namespace Tests\Unit;

use Unitec\FiscalEngine\Certificate\CnpjExtractor;
use Tests\TestCase;

class CnpjExtractorIcpBrasilTest extends TestCase
{
    public function test_prioriza_cnpj_do_cn_e_ignora_ou_da_autoridade(): void
    {
        $parsed = [
            'subject' => [
                'C' => 'BR',
                'O' => 'ICP-Brasil',
                'OU' => [
                    'Secretaria da Receita Federal do Brasil - RFB',
                    'RFB e-CNPJ A1',
                    '83729848000183',
                    'videoconferencia',
                ],
                'CN' => 'MINI MERCADO AYALA LTDA:41292075000170',
            ],
            'extensions' => [],
        ];

        $cnpj = CnpjExtractor::fromCertificatePem("-----BEGIN CERTIFICATE-----\n-----END CERTIFICATE-----", $parsed);

        $this->assertSame('41292075000170', $cnpj);
    }
}
