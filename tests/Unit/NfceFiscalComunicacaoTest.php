<?php

namespace Tests\Unit;

use App\Support\Fiscal\NfceFiscalComunicacao;
use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class NfceFiscalComunicacaoTest extends TestCase
{
    public function test_detecta_falha_dns_como_indisponivel(): void
    {
        $exception = new FiscalEngineException(
            'Falha na comunicação com a SEFAZ: não foi possível resolver o endereço do webservice da SEFAZ (verifique DNS/internet).',
        );

        $this->assertTrue(NfceFiscalComunicacao::isIndisponivel($exception));
    }

    public function test_rejeicao_sefaz_com_cstat_nao_e_contingencia(): void
    {
        $exception = new FiscalEngineException(
            'Duplicidade de NF-e [cStat 539]',
            '539',
            'Duplicidade de NF-e',
        );

        $this->assertFalse(NfceFiscalComunicacao::isIndisponivel($exception));
    }

    public function test_http_sefaz_indisponivel(): void
    {
        $exception = new FiscalEngineException('SEFAZ retornou HTTP 503.');

        $this->assertTrue(NfceFiscalComunicacao::isIndisponivel($exception));
    }
}
