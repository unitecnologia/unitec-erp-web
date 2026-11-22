<?php

namespace Tests\Unit;

use Tests\TestCase;
use Unitec\FiscalEngine\Util\XmlHelper;

/**
 * Wiring ERP: builders NF-e/NFC-e usam sanitizeTString nos campos textuais
 * afetados pelo mojibake SCV; XmlHelper concentra a reparação.
 */
class XmlHelperScvMojibakeWiringTest extends TestCase
{
    public function test_xml_helper_repara_casos_scv_e_preserva_acentos(): void
    {
        $this->assertSame('PROMOÇÃO', XmlHelper::sanitizeTString("PROMOÇ\u{0102}O", 60));
        $this->assertSame('CONFECÇÕES', XmlHelper::sanitizeTString("CONFECÇ\u{0150}ES", 60));
        $this->assertSame('CORRÊA', XmlHelper::sanitizeTString("CORR\u{0118}A", 60));
        $this->assertSame('Nº', XmlHelper::sanitizeTString("N\u{015F}", 60));
        $this->assertSame('São José', XmlHelper::sanitizeTString('São José', 60));
        $this->assertSame('CORRÊA', XmlHelper::sanitizeInfAdProd('CORRÊA', 60));
    }

    public function test_builders_usam_sanitize_tstring_em_campos_textuais(): void
    {
        $nfe = file_get_contents(base_path('packages/unitec-fiscal-engine/src/Xml/NfeXmlBuilder.php'));
        $nfce = file_get_contents(base_path('packages/unitec-fiscal-engine/src/Xml/NfceXmlBuilder.php'));
        $helper = file_get_contents(base_path('packages/unitec-fiscal-engine/src/Util/XmlHelper.php'));

        $this->assertIsString($nfe);
        $this->assertIsString($nfce);
        $this->assertIsString($helper);

        foreach (['xProd', 'xNome', 'xLgr', 'xBairro', 'xMun', 'infCpl'] as $campo) {
            $this->assertMatchesRegularExpression(
                '/\''.$campo.'\'\,\s*XmlHelper::sanitizeTString/s',
                $nfe,
                "NF-e deve sanitizar {$campo} com sanitizeTString",
            );
        }

        $this->assertStringContainsString("XmlHelper::sanitizeTString(\$descricao, 120)", $nfce);
        $this->assertStringContainsString("XmlHelper::sanitizeTString(\$nome, 60)", $nfce);
        $this->assertStringContainsString("infCpl', XmlHelper::sanitizeTString", $nfce);

        $this->assertStringContainsString('Ę → Ê', $helper);
        $this->assertStringContainsString('ş → º', $helper);
        $this->assertStringContainsString(
            'self::repairCommonMojibake($value)',
            $helper,
        );
    }
}
