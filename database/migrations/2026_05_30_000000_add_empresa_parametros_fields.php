<?php

use App\Support\Erp\EmpresaParametros;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            foreach (EmpresaParametros::numericFields() as $field => $meta) {
                $this->addNumericColumn($table, $field, $meta);
            }

            foreach (EmpresaParametros::permissionFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                if (($meta['tri'] ?? false) === true) {
                    $table->boolean($field)->nullable();
                } else {
                    $table->boolean($field)->default((bool) $meta['default']);
                }
            }

            foreach (EmpresaParametros::impostoFields() as $field => $meta) {
                $this->addNumericColumn($table, $field, $meta);
            }

            if (! Schema::hasColumn('empresas', 'param_imp_observacao')) {
                $table->text('param_imp_observacao')->nullable();
            }

            foreach (EmpresaParametros::difalFields() as $field => $meta) {
                $this->addNumericColumn($table, $field, $meta);
            }

            foreach (EmpresaParametros::difalBooleanFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                $table->boolean($field)->default((bool) $meta['default']);
            }

            foreach (EmpresaParametros::pixFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                $table->string($field, 255)->nullable()->default($meta['default'] === '' ? null : (string) $meta['default']);
            }

            foreach (EmpresaParametros::pixBooleanFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                $table->boolean($field)->default((bool) $meta['default']);
            }

            foreach (EmpresaParametros::apiServicosFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                if ($meta['type'] === 'integer') {
                    $table->unsignedInteger($field)->default((int) $meta['default']);
                } else {
                    $table->string($field, 255)->nullable()->default($meta['default'] === '' ? null : (string) $meta['default']);
                }
            }

            foreach (EmpresaParametros::apiServicosBooleanFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                $table->boolean($field)->default((bool) $meta['default']);
            }
        });
    }

    /**
     * @param  array{default: mixed, type: string, decimals?: int}  $meta
     */
    private function addNumericColumn(Blueprint $table, string $field, array $meta): void
    {
        if (Schema::hasColumn('empresas', $field)) {
            return;
        }

        $default = $meta['default'];

        match ($meta['type']) {
            'integer' => $table->unsignedInteger($field)->nullable()->default($default === null ? null : (int) $default),
            'decimal' => $table->decimal($field, 12, $meta['decimals'] ?? 2)->default($default ?? '0.00'),
            default => $table->string($field, 40)->default((string) ($default ?? '')),
        };
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(EmpresaParametros::allFieldNames());
        });
    }
};
