<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $colunas = [
            'nfse_provedor' => fn (Blueprint $table) => $table->string('nfse_provedor', 20)->default('nacional')->after('nfse_serie_dps'),
            'nfse_serie_rps' => fn (Blueprint $table) => $table->string('nfse_serie_rps', 5)->nullable()->after('nfse_provedor'),
            'nfse_proximo_rps' => fn (Blueprint $table) => $table->unsignedBigInteger('nfse_proximo_rps')->nullable()->after('nfse_serie_rps'),
            'nfse_tipo_rps' => fn (Blueprint $table) => $table->string('nfse_tipo_rps', 1)->nullable()->after('nfse_proximo_rps'),
            'nfse_ws_usuario' => fn (Blueprint $table) => $table->string('nfse_ws_usuario', 20)->nullable()->after('nfse_tipo_rps'),
            'nfse_ws_senha' => fn (Blueprint $table) => $table->string('nfse_ws_senha', 120)->nullable()->after('nfse_ws_usuario'),
            'nfse_url_producao' => fn (Blueprint $table) => $table->string('nfse_url_producao', 255)->nullable()->after('nfse_ws_senha'),
            'nfse_url_homologacao' => fn (Blueprint $table) => $table->string('nfse_url_homologacao', 255)->nullable()->after('nfse_url_producao'),
        ];

        foreach ($colunas as $nome => $adicionar) {
            if (Schema::hasColumn('empresas', $nome)) {
                continue;
            }

            Schema::table('empresas', function (Blueprint $table) use ($adicionar): void {
                $adicionar($table);
            });
        }
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            foreach ([
                'nfse_url_homologacao',
                'nfse_url_producao',
                'nfse_ws_senha',
                'nfse_ws_usuario',
                'nfse_tipo_rps',
                'nfse_proximo_rps',
                'nfse_serie_rps',
                'nfse_provedor',
            ] as $coluna) {
                if (Schema::hasColumn('empresas', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
