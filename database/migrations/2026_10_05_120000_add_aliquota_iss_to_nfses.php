<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            if (! Schema::hasColumn('nfses', 'aliquota_iss')) {
                $table->decimal('aliquota_iss', 5, 2)->nullable()->after('tp_ret_issqn');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            if (Schema::hasColumn('nfses', 'aliquota_iss')) {
                $table->dropColumn('aliquota_iss');
            }
        });
    }
};
