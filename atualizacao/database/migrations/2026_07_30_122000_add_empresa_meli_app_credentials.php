<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_meli_client_id')) {
                $table->string('param_meli_client_id', 64)->nullable()->after('param_meli_habilitar');
            }

            if (! Schema::hasColumn('empresas', 'param_meli_client_secret')) {
                $table->text('param_meli_client_secret')->nullable()->after('param_meli_client_id');
            }

            if (! Schema::hasColumn('empresas', 'param_meli_redirect_uri')) {
                $table->string('param_meli_redirect_uri', 255)->nullable()->after('param_meli_client_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            foreach ([
                'param_meli_client_id',
                'param_meli_client_secret',
                'param_meli_redirect_uri',
            ] as $field) {
                if (Schema::hasColumn('empresas', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
};
