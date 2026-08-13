<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            $table->string('resp_tecnico_cnpj', 14)->nullable()->after('email_tls');
            $table->string('resp_tecnico_contato', 60)->nullable()->after('resp_tecnico_cnpj');
            $table->string('resp_tecnico_email', 60)->nullable()->after('resp_tecnico_contato');
            $table->string('resp_tecnico_fone', 20)->nullable()->after('resp_tecnico_email');
            $table->string('resp_tecnico_id_csrt', 6)->nullable()->after('resp_tecnico_fone');
            $table->string('resp_tecnico_csrt', 100)->nullable()->after('resp_tecnico_id_csrt');
        });
    }

    public function down(): void
    {
        Schema::table('vendas_parametros', function (Blueprint $table): void {
            $table->dropColumn([
                'resp_tecnico_cnpj',
                'resp_tecnico_contato',
                'resp_tecnico_email',
                'resp_tecnico_fone',
                'resp_tecnico_id_csrt',
                'resp_tecnico_csrt',
            ]);
        });
    }
};
