<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contas_receber')) {
            return;
        }

        Schema::table('contas_receber', function (Blueprint $table): void {
            if (! Schema::hasColumn('contas_receber', 'numero_cheque')) {
                $table->string('numero_cheque', 40)->nullable()->after('forma');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contas_receber') || ! Schema::hasColumn('contas_receber', 'numero_cheque')) {
            return;
        }

        Schema::table('contas_receber', function (Blueprint $table): void {
            $table->dropColumn('numero_cheque');
        });
    }
};
