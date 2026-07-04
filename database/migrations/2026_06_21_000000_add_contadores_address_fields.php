<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contadores', function (Blueprint $table): void {
            if (! Schema::hasColumn('contadores', 'cnpj_cpf')) {
                $table->string('cnpj_cpf', 20)->nullable()->after('nome');
            }

            if (! Schema::hasColumn('contadores', 'crc')) {
                $table->string('crc', 30)->nullable()->after('cnpj_cpf');
            }

            if (! Schema::hasColumn('contadores', 'cep')) {
                $table->string('cep', 10)->nullable()->after('crc');
            }

            if (! Schema::hasColumn('contadores', 'endereco')) {
                $table->string('endereco', 120)->nullable()->after('cep');
            }

            if (! Schema::hasColumn('contadores', 'numero')) {
                $table->string('numero', 20)->nullable()->after('endereco');
            }

            if (! Schema::hasColumn('contadores', 'bairro')) {
                $table->string('bairro', 80)->nullable()->after('numero');
            }

            if (! Schema::hasColumn('contadores', 'cidade')) {
                $table->string('cidade', 80)->nullable()->after('bairro');
            }

            if (! Schema::hasColumn('contadores', 'uf')) {
                $table->string('uf', 2)->nullable()->default('SC')->after('cidade');
            }

            if (! Schema::hasColumn('contadores', 'email')) {
                $table->string('email', 120)->nullable()->after('uf');
            }

            if (! Schema::hasColumn('contadores', 'fone')) {
                $table->string('fone', 20)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contadores', function (Blueprint $table): void {
            $table->dropColumn([
                'cnpj_cpf',
                'crc',
                'cep',
                'endereco',
                'numero',
                'bairro',
                'cidade',
                'uf',
                'email',
                'fone',
            ]);
        });
    }
};
