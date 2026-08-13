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

                if (($meta['type'] ?? '') === 'integer') {
                    $table->unsignedInteger($field)->default((int) $meta['default']);
                } elseif (($meta['type'] ?? '') === 'date') {
                    $table->date($field)->nullable();
                } else {
                    $default = $meta['default'] ?? null;

                    if ($default === '' || $default === null) {
                        $table->string($field, 255)->nullable();
                    } else {
                        $table->string($field, 255)->default((string) $default);
                    }
                }
            }

            foreach (EmpresaParametros::whatsAppBooleanFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                $table->boolean($field)->default((bool) $meta['default']);
            }
        });

        Schema::table('empresas', function (Blueprint $table) {
            foreach (['param_whatsapp_url', 'param_whatsapp_token', 'param_whatsapp_fechar_ticket'] as $field) {
                if (Schema::hasColumn('empresas', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'param_whatsapp_url')) {
                $table->string('param_whatsapp_url', 255)->nullable()->default('http://localhost:8080');
            }

            if (! Schema::hasColumn('empresas', 'param_whatsapp_token')) {
                $table->string('param_whatsapp_token', 255)->nullable()->default('');
            }

            if (! Schema::hasColumn('empresas', 'param_whatsapp_fechar_ticket')) {
                $table->boolean('param_whatsapp_fechar_ticket')->default(true);
            }

            $fields = [
                ...array_keys(EmpresaParametros::whatsAppFields()),
                ...array_keys(EmpresaParametros::whatsAppBooleanFields()),
            ];

            foreach ($fields as $field) {
                if (in_array($field, [
                    'param_whatsapp_habilitar',
                    'param_whatsapp_timeout',
                ], true)) {
                    continue;
                }

                if (Schema::hasColumn('empresas', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
};
