<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice para MAX(numero) da NFC-e (aba PDVs Offline / NfceTerminalSequencia::ultimoNumero):
 * WHERE empresa_id = ? AND serie IN (...) AND ambiente = ? AND (simulada = 0 OR NULL).
 * Antes: full scan (~29k rows). Depois: range no índice composto.
 * Não altera SQL de numeração — só acesso físico.
 */
return new class extends Migration
{
    private const TABLE = 'pdv_venda_nfce';

    private const INDEX = 'pdv_venda_nfce_emp_amb_serie_num_idx';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->indexExists(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(
                ['empresa_id', 'ambiente', 'serie', 'numero'],
                self::INDEX,
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! $this->indexExists(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $row) {
            if (($row['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
