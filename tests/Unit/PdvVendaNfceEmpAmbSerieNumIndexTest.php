<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Migration do índice composto para MAX(numero) NFC-e — wiring, sem DB.
 */
class PdvVendaNfceEmpAmbSerieNumIndexTest extends TestCase
{
    public function test_migration_cria_indice_composto_sem_alterar_sql_de_numeracao(): void
    {
        $mig = file_get_contents(database_path(
            'migrations/2026_11_18_154500_add_pdv_venda_nfce_emp_amb_serie_num_index.php'
        ));
        $seq = file_get_contents(app_path('Support/Fiscal/NfceTerminalSequencia.php'));

        $this->assertIsString($mig);
        $this->assertStringContainsString('pdv_venda_nfce_emp_amb_serie_num_idx', $mig);
        $this->assertStringContainsString("'empresa_id', 'ambiente', 'serie', 'numero'", $mig);
        $this->assertStringContainsString('pdv_venda_nfce', $mig);

        $this->assertIsString($seq);
        // SQL de cálculo permanece o mesmo (max via Eloquent).
        $this->assertStringContainsString("->max('numero')", $seq);
        $this->assertStringContainsString("->whereIn('serie', self::seriesEquivalentes(\$serie))", $seq);
    }
}
