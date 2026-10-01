<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('erp_user_preferences')) {
            return;
        }

        Schema::create('erp_user_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('key', 120);
            $table->string('value', 255);
            $table->timestamps();

            $table->unique(['user_id', 'empresa_id', 'key'], 'erp_user_prefs_user_empresa_key_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_user_preferences');
    }
};
