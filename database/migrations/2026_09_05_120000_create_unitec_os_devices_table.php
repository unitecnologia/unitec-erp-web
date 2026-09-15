<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unitec_os_devices', function (Blueprint $table): void {
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

    public function down(): void
    {
        Schema::dropIfExists('unitec_os_devices');
    }
};
