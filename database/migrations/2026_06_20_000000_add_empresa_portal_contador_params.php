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
            foreach (EmpresaParametros::portalContadorFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                if ($meta['type'] === 'integer') {
                    if ($field === 'param_portal_contador_contador_id') {
                        $table->unsignedInteger($field)->nullable();
                    } else {
                        $table->unsignedInteger($field)->default((int) $meta['default']);
                    }
                } else {
                    $table->string($field, 255)->nullable()->default($meta['default'] === '' || $meta['default'] === null ? null : (string) $meta['default']);
                }
            }

            foreach (EmpresaParametros::portalContadorBooleanFields() as $field => $meta) {
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
                ...array_keys(EmpresaParametros::portalContadorFields()),
                ...array_keys(EmpresaParametros::portalContadorBooleanFields()),
            ];

            foreach ($fields as $field) {
                if (Schema::hasColumn('empresas', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
};
