<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendas_internas_devices', function (Blueprint $table): void {
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
            $table->timestamp('last_pull_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('empresa_id');
        });

        Schema::create('vendas_internas_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('device_uuid', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('vendedor_id')->nullable();
            $table->foreignId('orcamento_id')->nullable()->constrained('orcamentos')->nullOnDelete();
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();
            $table->decimal('total', 14, 2)->default(0);
            $table->string('status', 20)->default('importado');
            $table->string('situacao', 20)->default('aguardando');
            $table->text('erro')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('client_created_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('pago_at')->nullable();
            $table->timestamps();

            $table->index(['device_uuid', 'created_at']);
            $table->index(['vendedor_id', 'situacao']);
            $table->index('orcamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas_internas_orders');
        Schema::dropIfExists('vendas_internas_devices');
    }
};
