<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendas_internas_device_resets')) {
            Schema::create('vendas_internas_device_resets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('vendas_internas_device_id')->nullable();
                $table->string('device_uuid', 100);
                $table->string('status', 20)->default('pendente');
                $table->unsignedBigInteger('authorized_by')->nullable();
                $table->timestamp('authorized_at');
                $table->timestamp('completed_at')->nullable();
                $table->string('completed_app_version', 40)->nullable();
                $table->timestamps();

                $table->index(['device_uuid', 'status'], 'vi_device_resets_device_status_idx');
            });
        }

        // Nomes curtos: prefixo unitec_ + nome automático estoura o limite MySQL (64).
        if (! $this->hasForeignKey('vi_dev_resets_device_fk')) {
            Schema::table('vendas_internas_device_resets', function (Blueprint $table): void {
                $table->foreign('vendas_internas_device_id', 'vi_dev_resets_device_fk')
                    ->references('id')->on('vendas_internas_devices')->nullOnDelete();
            });
        }

        if (! $this->hasForeignKey('vi_dev_resets_auth_fk')) {
            Schema::table('vendas_internas_device_resets', function (Blueprint $table): void {
                $table->foreign('authorized_by', 'vi_dev_resets_auth_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas_internas_device_resets');
    }

    private function hasForeignKey(string $name): bool
    {
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS name
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_TYPE = \'FOREIGN KEY\'
               AND CONSTRAINT_NAME = ?
             LIMIT 1',
            [
                config('database.connections.mysql.prefix').'vendas_internas_device_resets',
                $name,
            ],
        );

        return $row !== null;
    }
};
