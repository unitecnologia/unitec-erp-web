<?php

namespace Tests\Unit;

use App\Support\Fiscal\RegimeCrtMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

class RegimeCrtMapperTest extends TestCase
{
    #[DataProvider('regimesCrt3Provider')]
    public function test_aliases_presumido_viram_crt3(string $regime): void
    {
        $this->assertSame(3, RegimeCrtMapper::toCrt($regime));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function regimesCrt3Provider(): array
    {
        return [
            'presumido' => ['presumido'],
            'lucro_presumido' => ['lucro_presumido'],
            'real' => ['real'],
            'lucro_real' => ['lucro_real'],
            'normal' => ['normal'],
        ];
    }

    public function test_simples_e_mei_preservados(): void
    {
        $this->assertSame(1, RegimeCrtMapper::toCrt('simples'));
        $this->assertSame(4, RegimeCrtMapper::toCrt('mei'));
        $this->assertSame(2, RegimeCrtMapper::toCrt('excesso_sublimite'));
    }

    public function test_regime_desconhecido_nao_vira_crt1_em_modo_strict(): void
    {
        $this->expectException(FiscalEngineException::class);
        RegimeCrtMapper::toCrt('regime_inventado');
    }

    public function test_normalize_lucro_presumido(): void
    {
        $this->assertSame('presumido', RegimeCrtMapper::normalize('lucro_presumido'));
    }
}
