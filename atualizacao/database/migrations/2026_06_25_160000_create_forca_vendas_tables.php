<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forca_vendas_settings', function (Blueprint $table) {
            $table->id();
            $table->string('pairing_secret', 80);
            $table->boolean('pairing_required')->default(true);
            $table->timestamps();
        });

        Schema::create('forca_vendas_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_uuid', 100)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('device_name')->nullable();
            $table->string('platform', 40)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->unsignedBigInteger('current_token_id')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_pull_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('empresa_id');
        });

        Schema::create('forca_vendas_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('device_uuid', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('tipo', 20)->default('orcamento');
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('vendedor_id')->nullable();
            $table->foreignId('orcamento_id')->nullable()->constrained('orcamentos')->nullOnDelete();
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('importado');
            $table->text('erro')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('client_created_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['device_uuid', 'created_at']);
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forca_vendas_orders');
        Schema::dropIfExists('forca_vendas_devices');
        Schema::dropIfExists('forca_vendas_settings');
    }
};
