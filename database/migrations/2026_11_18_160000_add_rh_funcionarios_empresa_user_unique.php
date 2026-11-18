<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Um usuário (operador) por empresa no RH.
 * Permite o mesmo user_id em empresas diferentes; bloqueia duplicata na mesma empresa.
 * MySQL: vários NULL em user_id continuam permitidos no UNIQUE.
 */
return new class extends Migration
{
    private const TABLE = 'rh_funcionarios';

    private const INDEX = 'rh_funcionarios_empresa_user_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)
            || ! Schema::hasColumn(self::TABLE, 'empresa_id')
            || ! Schema::hasColumn(self::TABLE, 'user_id')) {
            return;
        }

        if ($this->indexExists(self::TABLE, self::INDEX)) {
            return;
        }

        // Segurança: não cria UNIQUE se já houver duplicata (empresa_id, user_id) com user_id preenchido.
        $dup = DB::table(self::TABLE)
            ->whereNotNull('user_id')
            ->whereNotNull('empresa_id')
            ->select('empresa_id', 'user_id', DB::raw('COUNT(*) as c'))
            ->groupBy('empresa_id', 'user_id')
            ->having('c', '>', 1)
            ->limit(1)
            ->exists();

        if ($dup) {
            throw new \RuntimeException(
                'Não foi possível criar '.self::INDEX.': existem funcionários com o mesmo user_id na mesma empresa. Corrija os duplicados e rode a migration novamente.'
            );
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique(['empresa_id', 'user_id'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! $this->indexExists(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
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
