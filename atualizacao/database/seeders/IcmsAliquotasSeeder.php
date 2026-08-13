<?php

namespace Database\Seeders;

use App\Models\IcmsAliquota;
use Illuminate\Database\Seeder;

/**
 * Matriz ICMS interestadual / interna (DIFAL) — padrão oficial 2026.
 */
class IcmsAliquotasSeeder extends Seeder
{
    public function run(): void
    {
        $count = IcmsAliquota::seedPadrao2026();

        $this->command?->info("Tabela ICMS padrão 2026: {$count} célula(s).");
    }
}
