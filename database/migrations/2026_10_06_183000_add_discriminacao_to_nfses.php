<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            $table->text('discriminacao')->nullable()->after('aliquota_iss');
        });
    }

    public function down(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            $table->dropColumn('discriminacao');
        });
    }
};
