<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Util\XmlHelper;

/**
 * Mojibake SCV: WIN1252 lido como CP1250/ISO-8859-2.
 * Reparação só no XML (não grava cadastro).
 */
final class XmlHelperMojibakeTest extends TestCase
{
    public function test_repara_promocao_confeccoes_e_correa(): void
    {
        $this->assertSame(
            'PROMOÇÃO BERMUDA',
            XmlHelper::sanitizeTString("PROMOÇ\u{0102}O BERMUDA", 60),
        );
        $this->assertSame(
            'PHIZALIS CONFECÇÕES LTDA',
            XmlHelper::sanitizeTString("PHIZALIS CONFECÇ\u{0150}ES LTDA", 60),
        );
        $this->assertSame(
            'MARILIA CORRÊA BORGES',
            XmlHelper::sanitizeTString("MARILIA CORR\u{0118}A BORGES", 60),
        );
    }

    public function test_repara_endereco_com_ordinal_n_cedilha(): void
    {
        $this->assertSame(
            'LUANDA Nº96',
            XmlHelper::sanitizeTString("LUANDA N\u{015F}96", 60),
        );
        $this->assertSame(
            'RUA JOÃO RISSON',
            XmlHelper::sanitizeTString("RUA JO\u{0102}O RISSON", 60),
        );
    }

    public function test_preserva_acentos_portugueses_corretos(): void
    {
        $ok = 'Informação complementar: CONFECÇÕES, PROMOÇÃO, CORRÊA, João, São José';

        $this->assertSame($ok, XmlHelper::sanitizeInfAdProd($ok, 200));
        $this->assertSame(
            'JONITAM CONFECÇÕES LTDA',
            XmlHelper::sanitizeTString('JONITAM CONFECÇÕES LTDA', 60),
        );
        $this->assertSame('PROMOÇÃO', XmlHelper::sanitizeText('PROMOÇÃO', 60));
    }

    public function test_sanitize_inf_ad_prod_nao_descarta_mojibake_antes_do_filtro(): void
    {
        // Sem repair prévio, Ő (U+0150) seria removido por estar > U+00FF.
        $this->assertSame(
            'CONFECÇÕES',
            XmlHelper::sanitizeInfAdProd("CONFECÇ\u{0150}ES", 60),
        );
        $this->assertSame(
            'PROMOÇÃO',
            XmlHelper::sanitizeInfAdProd("PROMOÇ\u{0102}O", 60),
        );
    }

    public function test_sanitize_text_tambem_repara_mojibake(): void
    {
        $this->assertSame(
            'FACÇÃO',
            XmlHelper::sanitizeText("FACÇ\u{0102}O", 60),
        );
    }
}
