<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('terminais') || ! Schema::hasColumn('terminais', 'meia_folha')) {
            return;
        }

        Schema::table('terminais', function (Blueprint $table): void {
            $table->dropColumn('meia_folha');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('terminais') || Schema::hasColumn('terminais', 'meia_folha')) {
            return;
        }

        Schema::table('terminais', function (Blueprint $table): void {
            $table->boolean('meia_folha')->default(false);
        });
    }
};
