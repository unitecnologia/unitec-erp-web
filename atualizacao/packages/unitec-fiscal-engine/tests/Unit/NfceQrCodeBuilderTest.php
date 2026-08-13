<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Nfce\NfceQrCodeBuilder;

final class NfceQrCodeBuilderTest extends TestCase
{
    public function test_versao_2_online_usa_apenas_quatro_parametros_mais_hash(): void
    {
        $builder = new NfceQrCodeBuilder();
        $chave = '42260722469772000100650010000000011000000000';
        $csc = 'FF1BFD3B-29D5-48F2-B472-5E76C281D283';
        $seq = $chave . '|2|2|1';
        $hash = strtoupper(sha1($seq . $csc));

        $url = $builder->buildUrl(
            chave: $chave,
            tpAmb: 2,
            tpEmis: 1,
            versaoQrcode: 2,
            idToken: '1',
            csc: $csc,
            dhEmiIso: '2026-07-06T19:50:00-03:00',
            valorNota: 10.0,
            digestValueBase64: 'abc123==',
        );

        $this->assertSame(
            'https://hom.sat.sef.sc.gov.br/nfce/consulta?p=' . $seq . '|' . $hash,
            $url,
        );
        $this->assertStringNotContainsString('3A2F2F', $url);
    }

    public function test_versao_3_online_usa_apenas_chave_ambiente(): void
    {
        $builder = new NfceQrCodeBuilder();
        $chave = '42260722469772000100650010000000011000000000';

        $url = $builder->buildUrl(
            chave: $chave,
            tpAmb: 2,
            tpEmis: 1,
            versaoQrcode: 3,
            idToken: '1',
            csc: 'token',
            dhEmiIso: '2026-07-06T19:50:00-03:00',
            valorNota: 10.0,
            digestValueBase64: 'abc123==',
        );

        $this->assertSame(
            'https://hom.sat.sef.sc.gov.br/nfce/consulta?p=' . $chave . '|3|2',
            $url,
        );
    }
}
