<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_meli_modo')) {
                $table->string('param_meli_modo', 20)->default('hub')->after('param_meli_habilitar');
            }
        });

        if (! Schema::hasTable('meli_hub_pairs')) {
            Schema::create('meli_hub_pairs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->string('client_label', 120)->nullable();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->string('meli_user_id', 32)->nullable();
                $table->string('nickname', 120)->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->text('erro')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meli_hub_pairs');

        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'param_meli_modo')) {
                $table->dropColumn('param_meli_modo');
            }
        });
    }
};
