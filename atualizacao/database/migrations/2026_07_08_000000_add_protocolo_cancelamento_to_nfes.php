<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfes') && ! Schema::hasColumn('nfes', 'protocolo_cancelamento')) {
            Schema::table('nfes', function (Blueprint $table): void {
                $table->string('protocolo_cancelamento', 20)->nullable()->after('protocolo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nfes') && Schema::hasColumn('nfes', 'protocolo_cancelamento')) {
            Schema::table('nfes', function (Blueprint $table): void {
                $table->dropColumn('protocolo_cancelamento');
            });
        }
    }
};
