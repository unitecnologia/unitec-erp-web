<?php

namespace Tests\Unit;

use App\Support\Erp\Rh\RhFuncionarioFormSnapshot;
use Tests\TestCase;

class RhFuncionarioFormSnapshotTest extends TestCase
{
    public function test_sem_baseline_nao_tem_alteracao(): void
    {
        $this->assertFalse(RhFuncionarioFormSnapshot::hasChanges(null, ['nome' => 'A']));
        $this->assertFalse(RhFuncionarioFormSnapshot::hasChanges('', ['nome' => 'A']));
    }

    public function test_detecta_alteracao_de_campo_texto(): void
    {
        $base = [
            'codigo' => '1',
            'nome' => '',
            'ativo' => true,
            'terminais' => [],
        ];
        $hash = RhFuncionarioFormSnapshot::hash($base);

        $this->assertFalse(RhFuncionarioFormSnapshot::hasChanges($hash, $base));

        $alterado = $base;
        $alterado['nome'] = 'JOAO';
        $this->assertTrue(RhFuncionarioFormSnapshot::hasChanges($hash, $alterado));
    }

    public function test_ignora_diferenca_cosmetica_de_espacos_e_ordem_de_terminais(): void
    {
        $a = [
            'nome' => ' JOAO ',
            'ativo' => true,
            'terminais' => [3, 1, 2],
        ];
        $b = [
            'nome' => 'JOAO',
            'ativo' => '1',
            'terminais' => [1, 2, 3],
        ];

        $this->assertSame(
            RhFuncionarioFormSnapshot::hash($a),
            RhFuncionarioFormSnapshot::hash($b),
        );
    }

    public function test_checkbox_e_array_alterados_contam_como_dirty(): void
    {
        $base = [
            'eh_operador' => false,
            'terminais' => [1],
        ];
        $hash = RhFuncionarioFormSnapshot::hash($base);

        $this->assertTrue(RhFuncionarioFormSnapshot::hasChanges($hash, [
            'eh_operador' => true,
            'terminais' => [1],
        ]));
        $this->assertTrue(RhFuncionarioFormSnapshot::hasChanges($hash, [
            'eh_operador' => false,
            'terminais' => [1, 2],
        ]));
    }
}
