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
            foreach (EmpresaParametros::whatsAppFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                if ($meta['type'] === 'integer') {
                    $table->unsignedInteger($field)->default((int) $meta['default']);
                } else {
                    $table->string($field, 255)->nullable()->default(
                        $meta['default'] === '' || $meta['default'] === null ? null : (string) $meta['default'],
                    );
                }
            }

            foreach (EmpresaParametros::whatsAppBooleanFields() as $field => $meta) {
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
                ...array_keys(EmpresaParametros::whatsAppFields()),
                ...array_keys(EmpresaParametros::whatsAppBooleanFields()),
            ];

            foreach ($fields as $field) {
                if (Schema::hasColumn('empresas', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
};
