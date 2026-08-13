<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_meli_habilitar')) {
                $table->boolean('param_meli_habilitar')->default(false);
            }

            if (! Schema::hasColumn('empresas', 'param_meli_user_id')) {
                $table->string('param_meli_user_id', 32)->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_meli_nickname')) {
                $table->string('param_meli_nickname', 120)->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_meli_access_token')) {
                $table->text('param_meli_access_token')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_meli_refresh_token')) {
                $table->text('param_meli_refresh_token')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_meli_token_expires_at')) {
                $table->timestamp('param_meli_token_expires_at')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_meli_vinculado_em')) {
                $table->timestamp('param_meli_vinculado_em')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            foreach ([
                'param_meli_habilitar',
                'param_meli_user_id',
                'param_meli_nickname',
                'param_meli_access_token',
                'param_meli_refresh_token',
                'param_meli_token_expires_at',
                'param_meli_vinculado_em',
            ] as $field) {
                if (Schema::hasColumn('empresas', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
};
