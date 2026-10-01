<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            if (! Schema::hasColumn('nfses', 'id_dps')) {
                $table->string('id_dps', 80)->nullable()->after('chave');
            }

            if (! Schema::hasColumn('nfses', 'chave_acesso')) {
                $table->string('chave_acesso', 60)->nullable()->after('id_dps');
            }

            if (! Schema::hasColumn('nfses', 'tipo_ambiente')) {
                $table->string('tipo_ambiente', 4)->nullable()->after('chave_acesso');
            }

            if (! Schema::hasColumn('nfses', 'versao_aplicativo')) {
                $table->string('versao_aplicativo', 40)->nullable()->after('tipo_ambiente');
            }

            if (! Schema::hasColumn('nfses', 'data_hora_processamento')) {
                $table->string('data_hora_processamento', 64)->nullable()->after('versao_aplicativo');
            }

            if (! Schema::hasColumn('nfses', 'xml_nfse')) {
                $table->longText('xml_nfse')->nullable()->after('data_hora_processamento');
            }

            if (! Schema::hasColumn('nfses', 'xml_dps')) {
                $table->longText('xml_dps')->nullable()->after('xml_nfse');
            }

            if (! Schema::hasColumn('nfses', 'alertas')) {
                $table->json('alertas')->nullable()->after('xml_dps');
            }

            if (! Schema::hasColumn('nfses', 'transmitindo_em')) {
                $table->timestamp('transmitindo_em')->nullable()->after('alertas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            foreach ([
                'transmitindo_em',
                'alertas',
                'xml_dps',
                'xml_nfse',
                'data_hora_processamento',
                'versao_aplicativo',
                'tipo_ambiente',
                'chave_acesso',
                'id_dps',
            ] as $coluna) {
                if (Schema::hasColumn('nfses', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
