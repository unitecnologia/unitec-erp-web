<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contador_cloud_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo_documento', 40);
            $table->string('evento', 20);
            $table->string('chave', 44)->nullable();
            $table->string('referencia_type', 40)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->text('response_body')->nullable();
            $table->longText('payload_json')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
            $table->index(['chave', 'evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contador_cloud_sync_logs');
    }
};
