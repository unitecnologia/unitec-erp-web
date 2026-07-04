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
            foreach (EmpresaParametros::sistemaFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                if ($meta['type'] === 'integer') {
                    $table->unsignedInteger($field)->default((int) $meta['default']);
                } elseif ($field === 'param_update_download_url') {
                    $table->text($field)->nullable();
                } else {
                    $table->string($field, 255)->nullable()->default($meta['default'] === '' || $meta['default'] === null ? null : (string) $meta['default']);
                }
            }

            foreach (EmpresaParametros::sistemaBooleanFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                $table->boolean($field)->default((bool) $meta['default']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $fields = [
                ...array_keys(EmpresaParametros::sistemaFields()),
                ...array_keys(EmpresaParametros::sistemaBooleanFields()),
            ];

            foreach ($fields as $field) {
                if (Schema::hasColumn('empresas', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
};
