<?php

declare(strict_types=1);

namespace Tests\Unit\Boleto;

use App\Support\Erp\Boleto\BoletoInterleaved2of5;
use Tests\TestCase;

final class BoletoInterleaved2of5Test extends TestCase
{
    public function test_gera_png_para_codigo_barras_de_44_digitos(): void
    {
        $barras = '08595163200000005331090049951647000000003701';
        $png = BoletoInterleaved2of5::png($barras);

        self::assertNotNull($png);
        self::assertStringStartsWith("\x89PNG", $png);

        $uri = BoletoInterleaved2of5::dataUri($barras);
        self::assertNotNull($uri);
        self::assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function test_retorna_null_para_codigo_vazio(): void
    {
        self::assertNull(BoletoInterleaved2of5::png(''));
        self::assertNull(BoletoInterleaved2of5::dataUri(null));
    }
}
