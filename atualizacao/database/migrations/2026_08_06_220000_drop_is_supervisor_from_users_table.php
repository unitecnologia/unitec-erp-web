<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_supervisor')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_supervisor');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_supervisor')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_supervisor')
                ->default(false)
                ->after('is_admin');
        });
    }
};
