<?php

namespace Tests\Unit;

use App\Models\NfeItem;
use App\Models\Product;
use App\Support\Fiscal\IbscbsApplicabilityResolver;
use App\Support\Fiscal\IbscbsImpostoFactory;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IbscbsApplicabilityResolverTest extends TestCase
{
    #[DataProvider('cenarios2026Provider')]
    public function test_transicao_2026_por_crt(int $crt, bool $deveEmitir): void
    {
        $this->assertSame(
            $deveEmitir,
            IbscbsApplicabilityResolver::deveEmitir($crt, '2026-09-10'),
        );
    }

    /**
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function cenarios2026Provider(): array
    {
        return [
            'CRT 4 MEI em 10/09/2026' => [4, false],
            'CRT 1 Simples em 10/09/2026' => [1, false],
            'CRT 2 excesso em 10/09/2026' => [2, false],
            'CRT 3 normal em 10/09/2026' => [3, true],
        ];
    }

    public function test_fronteira_ultimo_dia_2026_bloqueia_crt4(): void
    {
        $this->assertFalse(
            IbscbsApplicabilityResolver::deveEmitir(4, new DateTimeImmutable('2026-12-31')),
        );
    }

    public function test_fronteira_primeiro_dia_2027_nao_bloqueia_crt4(): void
    {
        // Não inventa regra de 2027: só prova que o bloqueio de 2026 não continua.
        $this->assertTrue(
            IbscbsApplicabilityResolver::deveEmitir(4, new DateTimeImmutable('2027-01-01')),
        );
    }

    public function test_factory_nao_leva_ibscbs_para_crt4_em_2026(): void
    {
        $item = new NfeItem([
            'csosn' => '900',
            'origem' => 4,
            'base_icms' => 245.94,
            'valor_icms' => 41.81,
            'aliq_icms' => 17,
            'p_red_bc_icms' => 29.412,
            'cst_pis' => '99',
            'valor_pis_icms' => 0,
            'cst_cofins' => '99',
            'valor_cofins_icms' => 0,
            'total' => 278.24,
            'cst_ibs_cbs' => '000',
            'class_trib' => '000001',
            'bc_ibs' => 278.24,
            'alq_ibs_uf' => 0.1,
            'v_ibs_uf' => 0.28,
            'alq_ibs_mun' => 0,
            'v_ibs_mun' => 0,
            'alq_cbs' => 0.9,
            'v_cbs' => 2.50,
        ]);

        $dto = IbscbsImpostoFactory::fromNfeItem(
            item: $item,
            origem: 4,
            csosn: '900',
            crt: 4,
            dataEmissao: new DateTimeImmutable('2026-09-10'),
        );

        $this->assertFalse($dto->hasIbscbs());
        $this->assertNull($dto->cstIbsCbs);
        $this->assertNull($dto->cClassTrib);
        $this->assertSame(0.0, $dto->vBcIbscbs);
        $this->assertSame(0.0, $dto->vIbsUf);
        $this->assertSame(0.0, $dto->vIbsMun);
        $this->assertSame(0.0, $dto->vCbs);
        // ICMS preservado
        $this->assertSame(245.94, $dto->vBc);
        $this->assertSame(41.81, $dto->vIcms);
    }

    public function test_factory_mantem_ibscbs_para_crt3_em_2026(): void
    {
        $item = new NfeItem([
            'csosn' => '102',
            'origem' => 0,
            'total' => 278.24,
            'cst_ibs_cbs' => '000',
            'class_trib' => '000001',
            'bc_ibs' => 278.24,
            'alq_ibs_uf' => 0.1,
            'v_ibs_uf' => 0.28,
            'alq_cbs' => 0.9,
            'v_cbs' => 2.50,
        ]);

        $dto = IbscbsImpostoFactory::fromNfeItem(
            item: $item,
            origem: 0,
            csosn: '102',
            crt: 3,
            dataEmissao: new DateTimeImmutable('2026-09-10'),
        );

        $this->assertTrue($dto->hasIbscbs());
        $this->assertSame('000', $dto->cstIbsCbs);
        $this->assertSame('000001', $dto->cClassTrib);
        $this->assertSame(278.24, $dto->vBcIbscbs);
        $this->assertSame(0.28, $dto->vIbsUf);
        $this->assertSame(2.50, $dto->vCbs);
    }

    public function test_factory_product_bloqueia_crt1_em_2026(): void
    {
        $product = new Product([
            'origem' => 0,
            'csosn' => '102',
            'iva_cst' => '000',
            'cclass_trib' => '000001',
            'aliq_ibs_uf' => 0.1,
            'aliq_cbs' => 0.9,
        ]);

        $dto = IbscbsImpostoFactory::fromProduct(
            $product,
            100.0,
            0.0,
            1,
            new DateTimeImmutable('2026-09-10'),
        );

        $this->assertFalse($dto->hasIbscbs());
        $this->assertNull($dto->cstIbsCbs);
        $this->assertNull($dto->cClassTrib);
    }

    public function test_factory_product_libera_crt4_em_2027(): void
    {
        $product = new Product([
            'origem' => 0,
            'csosn' => '102',
            'iva_cst' => '000',
            'cclass_trib' => '000001',
            'aliq_ibs_uf' => 0.1,
            'aliq_cbs' => 0.9,
        ]);

        $dto = IbscbsImpostoFactory::fromProduct(
            $product,
            100.0,
            0.0,
            4,
            new DateTimeImmutable('2027-01-01'),
        );

        $this->assertTrue($dto->hasIbscbs());
        $this->assertSame('000', $dto->cstIbsCbs);
        $this->assertSame('000001', $dto->cClassTrib);
    }
}
