<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unicidade definitiva de CPF/CNPJ em people via coluna gerada STORED + UNIQUE.
 * Vários NULL (sem documento) são permitidos.
 *
 * Se houver o mesmo documento em mais de uma pessoa, a atualização para
 * com erro. Nenhum cadastro é apagado, mesclado ou corrigido.
 */
return new class extends Migration
{
    /**
     * Expressão portátil MySQL 5.7+ / MariaDB 10.2+ / SQLite 3.31+ (STORED).
     * Não usa REGEXP_REPLACE (disponível só em versões mais novas).
     */
    public static function digitsExpression(string $column = 'cpf_cnpj'): string
    {
        return "NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL({$column}, ''), '.', ''), '-', ''), '/', ''), ' ', ''), '')";
    }

    public function up(): void
    {
        if (! Schema::hasTable('people')) {
            return;
        }

        if (! Schema::hasColumn('people', 'cpf_cnpj_digits')) {
            $expression = self::digitsExpression('cpf_cnpj');
            $driver = Schema::getConnection()->getDriverName();

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                Schema::table('people', function (Blueprint $table) use ($expression): void {
                    $table->string('cpf_cnpj_digits', 14)
                        ->nullable()
                        ->storedAs($expression)
                        ->after('cpf_cnpj');
                });
            } elseif ($driver === 'sqlite') {
                // Ambiente de teste: mesma semântica STORED (SQLite 3.31+).
                Schema::table('people', function (Blueprint $table) use ($expression): void {
                    $table->string('cpf_cnpj_digits', 14)
                        ->nullable()
                        ->storedAs($expression);
                });
            } else {
                throw new RuntimeException(
                    "Migration people.cpf_cnpj_digits não suportada no driver [{$driver}]. Use MySQL/MariaDB (produção) ou SQLite (testes)."
                );
            }
        }

        if (! $this->hasUniqueOnCpfCnpjDigits()) {
            $this->abortarSeDocumentoDuplicado();

            Schema::table('people', function (Blueprint $table): void {
                $table->unique('cpf_cnpj_digits', 'people_cpf_cnpj_digits_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('people')) {
            return;
        }

        Schema::table('people', function (Blueprint $table): void {
            try {
                $table->dropUnique('people_cpf_cnpj_digits_unique');
            } catch (Throwable) {
                // índice pode não existir
            }
            if (Schema::hasColumn('people', 'cpf_cnpj_digits')) {
                $table->dropColumn('cpf_cnpj_digits');
            }
        });
    }

    private function abortarSeDocumentoDuplicado(): void
    {
        $duplicados = DB::table('people')
            ->whereNotNull('cpf_cnpj_digits')
            ->groupBy('cpf_cnpj_digits')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('cpf_cnpj_digits');

        if ($duplicados->isEmpty()) {
            return;
        }

        $amostra = $duplicados->take(8)->implode(', ');
        $resto = $duplicados->count() > 8 ? '…' : '';

        throw new RuntimeException(
            'Atualização interrompida: existem CPF/CNPJ duplicados em pessoas ('
            .$duplicados->count().' documento(s) repetido(s): '.$amostra.$resto.'). '
            .'Corrija os cadastros e execute a atualização novamente. '
            .'Nenhum registro foi apagado ou mesclado. '
            .'O índice people_cpf_cnpj_digits_unique não foi criado.'
        );
    }

    private function hasUniqueOnCpfCnpjDigits(): bool
    {
        try {
            $indexes = Schema::getIndexes('people');
        } catch (Throwable) {
            return false;
        }

        foreach ($indexes as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === ['cpf_cnpj_digits']) {
                return true;
            }
        }

        return false;
    }
};
