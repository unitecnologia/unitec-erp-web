<?php

namespace Tests\Unit;

use App\Support\Erp\Balanca\BalancaProductRules;
use PHPUnit\Framework\TestCase;

class BalancaProductRulesTest extends TestCase
{
    public function test_nao_critica_quando_nao_e_produto_de_balanca(): void
    {
        $this->assertSame([], BalancaProductRules::criticas([
            'produto_pesado' => false,
            'codigo' => '1234567890123',
            'codigo_barras' => '',
            'grupo' => '',
        ]));
    }

    public function test_codigo_barras_vazio_obrigatorio_quando_pesado(): void
    {
        $criticas = BalancaProductRules::criticas([
            'produto_pesado' => true,
            'codigo' => '100',
            'codigo_barras' => '',
            'grupo' => '',
        ]);

        $this->assertArrayHasKey('codigo_barras', $criticas);
        $this->assertStringContainsString('código de barras', mb_strtolower($criticas['codigo_barras']));
    }

    public function test_sem_gtin_nao_serve_para_balanca(): void
    {
        $msg = BalancaProductRules::criticaCodigoBarras('SEM GTIN');

        $this->assertNotNull($msg);
    }

    public function test_plu_curto_e_formatos_livres_ok(): void
    {
        $this->assertNull(BalancaProductRules::criticaCodigoBarras('207'));
        $this->assertNull(BalancaProductRules::criticaCodigoBarras('12345'));
        $this->assertNull(BalancaProductRules::criticaCodigoBarras('1234567'));
        $this->assertNull(BalancaProductRules::criticaCodigoBarras('7891234567890'));
        $this->assertNull(BalancaProductRules::criticaCodigoBarras('2000040002215'));
    }

    public function test_nao_critica_campo_codigo_quando_pesado(): void
    {
        $criticas = BalancaProductRules::criticas([
            'produto_pesado' => true,
            'codigo' => '1234567890123',
            'codigo_barras' => '207',
            'grupo' => '',
        ]);

        $this->assertArrayNotHasKey('codigo', $criticas);
        $this->assertArrayNotHasKey('codigo_barras', $criticas);
    }
}
