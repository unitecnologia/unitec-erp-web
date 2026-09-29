<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke: migration de modo_prazo existe e contém backfill da heurística.
 * Não executa migrate no banco real.
 */
class ModoPrazoMigrationSmokeTest extends TestCase
{
    public function test_migration_modo_prazo_existe_com_backfill_seguro(): void
    {
        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR
            .'database'.DIRECTORY_SEPARATOR
            .'migrations'.DIRECTORY_SEPARATOR
            .'2026_09_29_120000_add_modo_prazo_to_formas_pagamento_table.php';

        $this->assertFileExists($path);

        $src = (string) file_get_contents($path);

        $this->assertStringContainsString('modo_prazo', $src);
        $this->assertStringContainsString("'financeiro'", $src);
        $this->assertStringContainsString("'tabela'", $src);
        $this->assertStringContainsString('max_parcelas', $src);
        $this->assertStringContainsString('intervalo_parcelas', $src);
        $this->assertStringContainsString('chunkById', $src);
    }
}
