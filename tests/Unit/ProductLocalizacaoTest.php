<?php

namespace Tests\Unit;

use App\Support\Erp\ProductLocalizacao;
use PHPUnit\Framework\TestCase;

class ProductLocalizacaoTest extends TestCase
{
    public function test_formata_padrao_cm_p_g(): void
    {
        $this->assertSame('C:1/M:2/P:3/G:4', ProductLocalizacao::format(1, 2, 3, 4));
        $this->assertSame('C:12/M:99/P:5/G:8', ProductLocalizacao::format(12, 99, 5, 8));
        $this->assertSame('C:10/M:2/P:3/G:4', ProductLocalizacao::format(100, 2, 3, 4));
    }

    public function test_parse_valor_estruturado(): void
    {
        $parts = ProductLocalizacao::parse('C:1/M:2/P:3/G:4');

        $this->assertSame('1', $parts['c']);
        $this->assertSame('2', $parts['m']);
        $this->assertSame('3', $parts['p']);
        $this->assertSame('4', $parts['g']);
    }

    public function test_collapse_from_form_grava_localizacao(): void
    {
        $data = ProductLocalizacao::collapseFromForm([
            'loc_corredor' => '1',
            'loc_modulo' => '2',
            'loc_prateleira' => '3',
            'loc_gaveta' => '4',
            'localizacao' => 'LEGADO',
        ]);

        $this->assertSame('C:1/M:2/P:3/G:4', $data['localizacao']);
        $this->assertArrayNotHasKey('loc_corredor', $data);
    }

    public function test_expand_into_form_separa_campos(): void
    {
        $data = ProductLocalizacao::expandIntoForm([
            'localizacao' => 'C:5/M:1/P:2/G:3',
        ]);

        $this->assertSame('5', $data['loc_corredor']);
        $this->assertSame('1', $data['loc_modulo']);
        $this->assertSame('2', $data['loc_prateleira']);
        $this->assertSame('3', $data['loc_gaveta']);
    }

    public function test_collapse_preserva_localizacao_estruturada(): void
    {
        $data = ProductLocalizacao::collapseFromForm([
            'localizacao' => 'C:2/M:2/P:3/G:4',
        ]);

        $this->assertSame('C:2/M:2/P:3/G:4', $data['localizacao']);
    }

    public function test_compare_bipagem_por_localizacao_cm_pg(): void
    {
        $this->assertSame(
            -1,
            ProductLocalizacao::compareForBipagemSort(
                'C:2/M:5/P:3/G:4',
                'C:3/M:1/P:1/G:1',
                'PRODUTO A',
                'PRODUTO B',
                '1',
                '2',
                1.0,
                1.0,
            ),
        );

        $this->assertSame(
            -1,
            ProductLocalizacao::compareForBipagemSort(
                'C:2/M:5/P:3/G:4',
                'C:2/M:6/P:1/G:1',
                'PRODUTO A',
                'PRODUTO B',
                '1',
                '2',
                1.0,
                1.0,
            ),
        );
    }

    public function test_compare_bipagem_desempate_alfabetico_codigo_quantidade(): void
    {
        $this->assertSame(
            -1,
            ProductLocalizacao::compareForBipagemSort(
                'C:2/M:5/P:3/G:4',
                'C:2/M:5/P:3/G:4',
                'AAA PRODUTO',
                'BBB PRODUTO',
                '10',
                '10',
                5.0,
                5.0,
            ),
        );

        $this->assertSame(
            -1,
            ProductLocalizacao::compareForBipagemSort(
                'C:2/M:5/P:3/G:4',
                'C:2/M:5/P:3/G:4',
                'MESMO NOME',
                'MESMO NOME',
                '2',
                '10',
                5.0,
                5.0,
            ),
        );

        $this->assertSame(
            -1,
            ProductLocalizacao::compareForBipagemSort(
                'C:2/M:5/P:3/G:4',
                'C:2/M:5/P:3/G:4',
                'MESMO NOME',
                'MESMO NOME',
                '10',
                '10',
                2.0,
                5.0,
            ),
        );
    }

    public function test_corredor_label(): void
    {
        $this->assertSame('CORREDOR 2', ProductLocalizacao::corredorLabel('C:2/M:5/P:3/G:4'));
        $this->assertSame('SEM CORREDOR', ProductLocalizacao::corredorLabel(''));
    }

    public function test_valor_legado_preservado_ate_padronizar(): void
    {
        $expanded = ProductLocalizacao::expandIntoForm(['localizacao' => 'B01']);

        $this->assertSame('B01', $expanded['loc_legado']);
        $this->assertSame('', $expanded['loc_corredor']);

        $collapsed = ProductLocalizacao::collapseFromForm($expanded);

        $this->assertSame('B01', $collapsed['localizacao']);
    }
}
