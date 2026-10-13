<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('nfes', 'natureza_operacao')) {
            Schema::table('nfes', function (Blueprint $table): void {
                $table->string('natureza_operacao', 120)->nullable()->after('cfop');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('nfes', 'natureza_operacao')) {
            Schema::table('nfes', function (Blueprint $table): void {
                $table->dropColumn('natureza_operacao');
            });
        }
    }
};
