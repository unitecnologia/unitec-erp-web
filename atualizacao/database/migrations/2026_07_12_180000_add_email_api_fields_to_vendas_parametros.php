<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendas_parametros', 'email_modo')) {
                $table->string('email_modo', 10)->nullable()->default('smtp')->after('email_tls');
            }

            if (! Schema::hasColumn('vendas_parametros', 'email_api_provedor')) {
                $table->string('email_api_provedor', 20)->nullable()->default('brevo')->after('email_modo');
            }

            if (! Schema::hasColumn('vendas_parametros', 'email_api_key')) {
                $table->text('email_api_key')->nullable()->after('email_api_provedor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            foreach (['email_api_key', 'email_api_provedor', 'email_modo'] as $column) {
                if (Schema::hasColumn('vendas_parametros', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
