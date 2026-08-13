<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pdv_venda_nfce') && ! Schema::hasColumn('pdv_venda_nfce', 'protocolo_cancelamento')) {
            Schema::table('pdv_venda_nfce', function (Blueprint $table): void {
                $table->string('protocolo_cancelamento', 20)->nullable()->after('protocolo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pdv_venda_nfce') && Schema::hasColumn('pdv_venda_nfce', 'protocolo_cancelamento')) {
            Schema::table('pdv_venda_nfce', function (Blueprint $table): void {
                $table->dropColumn('protocolo_cancelamento');
            });
        }
    }
};
