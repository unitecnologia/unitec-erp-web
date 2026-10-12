<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App Unitec Mesas (garçom): aparelhos autorizados, flag de acesso do usuário e o
 * registro idempotente dos lançamentos. As mesas continuam em `pdv_mesas` (PDV).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'acesso_app_mesas')) {
                $table->boolean('acesso_app_mesas')->default(false)->after('acesso_app_inventario');
            }
        });

        if (! Schema::hasTable('mesas_devices')) {
            Schema::create('mesas_devices', function (Blueprint $table): void {
                $table->id();
                $table->string('device_uuid', 100)->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->string('device_name')->nullable();
                $table->string('platform', 40)->nullable();
                $table->string('app_version', 40)->nullable();
                $table->string('status', 20)->default('pendente');
                $table->string('pairing_code', 12)->nullable();
                $table->unsignedBigInteger('current_token_id')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('registered_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index('empresa_id');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('pdv_mesa_lancamentos')) {
            Schema::create('pdv_mesa_lancamentos', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedSmallInteger('numero');
                $table->unsignedBigInteger('pdv_mesa_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('device_uuid', 100)->nullable();
                $table->unsignedInteger('qtd_itens')->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->longText('itens')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'numero']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_mesa_lancamentos');
        Schema::dropIfExists('mesas_devices');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'acesso_app_mesas')) {
                $table->dropColumn('acesso_app_mesas');
            }
        });
    }
};
