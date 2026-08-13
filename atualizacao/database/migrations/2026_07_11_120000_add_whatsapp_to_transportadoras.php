<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transportadoras')) {
            return;
        }

        Schema::table('transportadoras', function (Blueprint $table): void {
            if (! Schema::hasColumn('transportadoras', 'whatsapp')) {
                $table->string('whatsapp', 20)->nullable()->after('apelido');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transportadoras')) {
            return;
        }

        Schema::table('transportadoras', function (Blueprint $table): void {
            if (Schema::hasColumn('transportadoras', 'whatsapp')) {
                $table->dropColumn('whatsapp');
            }
        });
    }
};
