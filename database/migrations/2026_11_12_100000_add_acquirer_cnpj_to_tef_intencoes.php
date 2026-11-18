<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CNPJ da instituição de pagamento (payment_data.acquirer_cnpj) para NFC-e tpIntegra=1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tef_intencoes')) {
            return;
        }

        Schema::table('tef_intencoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('tef_intencoes', 'acquirer_cnpj')) {
                $table->string('acquirer_cnpj', 14)->nullable()->after('bandeira');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tef_intencoes') || ! Schema::hasColumn('tef_intencoes', 'acquirer_cnpj')) {
            return;
        }

        Schema::table('tef_intencoes', function (Blueprint $table): void {
            $table->dropColumn('acquirer_cnpj');
        });
    }
};
