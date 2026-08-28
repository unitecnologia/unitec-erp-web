<?php

namespace Tests\Unit;

use App\Support\Erp\Balanca\BalancaEtiquetaLayout;
use PHPUnit\Framework\TestCase;

class PdvScaleBarcodeServiceTest extends TestCase
{
    public function test_modelo_05_decodifica_etiqueta_acougue_real(): void
    {
        $barcode = '2000040002215';
        $precoKg = 13.50;

        $this->assertTrue(BalancaEtiquetaLayout::isTotalPrice(5));
        $this->assertSame(6, BalancaEtiquetaLayout::digitosForModelo(5));
        $this->assertSame(5, BalancaEtiquetaLayout::valorLength(5));

        $code = BalancaEtiquetaLayout::productCodeFromBarcode($barcode, '2', 6, 5);
        $this->assertSame('000040', $code);
        $this->assertSame('000040', BalancaEtiquetaLayout::normalizeProductCode('40', '2', 6));

        $valorStart = strlen('2') + 6;
        $segmento = substr($barcode, $valorStart, 5);
        $this->assertSame('00221', $segmento);

        $total = round(((float) $segmento) / 100, 2);
        $quantidade = round($total / $precoKg, 3);

        $this->assertSame(2.21, $total);
        $this->assertEqualsWithDelta(0.164, $quantidade, 0.001);
    }

    public function test_modelo_05_decodifica_etiqueta_salmao(): void
    {
        $barcode = '2000018025024';
        $precoKg = 16.00;

        $code = BalancaEtiquetaLayout::productCodeFromBarcode($barcode, '2', 6, 5);
        $this->assertSame('000018', $code);

        $segmento = substr($barcode, 7, 5);
        $total = round(((float) $segmento) / 100, 2);
        $quantidade = round($total / $precoKg, 3);

        $this->assertSame(25.02, $total);
        $this->assertEqualsWithDelta(1.564, $quantidade, 0.001);
    }
}
