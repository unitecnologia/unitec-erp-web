<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableAlreadyExists('entregas_devices')) {
            return;
        }

        try {
            Schema::create('entregas_devices', function (Blueprint $table): void {
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
        } catch (\Throwable $e) {
            if ($this->isTableAlreadyExistsError($e)) {
                return;
            }

            throw $e;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas_devices');
    }

    private function tableAlreadyExists(string $table): bool
    {
        if (Schema::hasTable($table)) {
            return true;
        }

        $prefix = (string) Schema::getConnection()->getTablePrefix();
        $full = $prefix.$table;
        $db = Schema::getConnection()->getDatabaseName();

        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1',
            [$db, $full]
        );

        return $row !== null;
    }

    private function isTableAlreadyExistsError(\Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, '42S01')
            || str_contains($msg, 'already exists')
            || str_contains($msg, '1050');
    }
};
