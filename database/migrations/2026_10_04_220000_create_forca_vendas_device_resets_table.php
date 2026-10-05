<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forca_vendas_device_resets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('forca_vendas_device_id')->nullable()
                ->constrained('forca_vendas_devices')->nullOnDelete();
            $table->string('device_uuid', 100);
            $table->string('status', 20)->default('pendente');
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('completed_app_version', 40)->nullable();
            $table->timestamps();

            $table->index(['device_uuid', 'status'], 'fv_device_resets_device_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forca_vendas_device_resets');
    }
};
