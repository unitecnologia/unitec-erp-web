<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forca_vendas_cliente_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->bigInteger('local_id')->nullable();
            $table->string('device_uuid', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forca_vendas_cliente_imports');
    }
};
