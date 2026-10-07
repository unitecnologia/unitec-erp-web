<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            if (! Schema::hasColumn('nfses', 'cancelamento_codigo')) {
                $table->string('cancelamento_codigo', 2)->nullable()->after('status');
            }

            if (! Schema::hasColumn('nfses', 'cancelada_em')) {
                $table->dateTime('cancelada_em')->nullable()->after('status');
            }

            if (! Schema::hasColumn('nfses', 'xml_cancelamento')) {
                $table->longText('xml_cancelamento')->nullable()->after('xml_nfse');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            foreach (['cancelamento_codigo', 'cancelada_em', 'xml_cancelamento'] as $coluna) {
                if (Schema::hasColumn('nfses', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
