<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('nfses', 'cancelada_por')) {
            return;
        }

        Schema::table('nfses', function (Blueprint $table): void {
            $table->string('cancelada_por', 120)->nullable()->after('cancelada_em');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('nfses', 'cancelada_por')) {
            return;
        }

        Schema::table('nfses', function (Blueprint $table): void {
            $table->dropColumn('cancelada_por');
        });
    }
};
