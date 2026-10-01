<?php

namespace Tests\Unit;

use App\Support\Fiscal\MeiFiscalNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MeiFiscalNormalizerTest extends TestCase
{
    #[DataProvider('nfeProvider')]
    public function test_normaliza_nfe_mei(string $csosn, string $cfop, string $csosnEsperado, string $cfopEsperado): void
    {
        $out = MeiFiscalNormalizer::normalizeItem(
            $csosn,
            $cfop,
            MeiFiscalNormalizer::MODELO_NFE,
        );

        $this->assertSame($csosnEsperado, $out['csosn']);
        $this->assertSame($cfopEsperado, $out['cfop']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function nfeProvider(): array
    {
        return [
            'CSOSN 500 + CFOP 5405 (caso da rejeição 782)' => ['500', '5405', '102', '5102'],
            'CSOSN 102 + CFOP 5102 mantém' => ['102', '5102', '102', '5102'],
            'CSOSN 102 + CFOP 6102 mantém' => ['102', '6102', '102', '6102'],
            'CSOSN 102 + CFOP interestadual inválido' => ['102', '6405', '102', '6102'],
            'CSOSN 900 + CFOP devolução mantém' => ['900', '5202', '900', '5202'],
            'CSOSN 101 venda vira 102' => ['101', '5102', '102', '5102'],
            'CSOSN 400 mantém' => ['400', '5102', '400', '5102'],
        ];
    }

    public function test_nfce_mei_so_aceita_102_ou_300_e_cfop_5102(): void
    {
        $out = MeiFiscalNormalizer::normalizeItem('500', '5405', MeiFiscalNormalizer::MODELO_NFCE);

        $this->assertSame('102', $out['csosn']);
        $this->assertSame('5102', $out['cfop']);
    }

    public function test_normalize_if_mei_ignora_crt_nao_mei(): void
    {
        $out = MeiFiscalNormalizer::normalizeIfMei(1, '500', '5405');

        $this->assertSame('500', $out['csosn']);
        $this->assertSame('5405', $out['cfop']);
    }

    public function test_detecta_regime_mei(): void
    {
        $this->assertTrue(MeiFiscalNormalizer::isMeiRegime('mei'));
        $this->assertTrue(MeiFiscalNormalizer::isMeiRegime('SIMEI'));
        $this->assertFalse(MeiFiscalNormalizer::isMeiRegime('simples'));
    }
}
