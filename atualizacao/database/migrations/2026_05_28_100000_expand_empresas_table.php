<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->unsignedInteger('codigo')->nullable()->after('id');
            $table->string('fantasia')->nullable()->after('codigo');
            $table->string('razao_social')->nullable()->after('fantasia');
            $table->string('cidade')->nullable()->after('razao_social');
            $table->string('cnpj', 14)->nullable()->after('cidade');
            $table->string('ie', 20)->nullable()->after('cnpj');
        });

        foreach (DB::table('empresas')->orderBy('id')->get() as $empresa) {
            DB::table('empresas')->where('id', $empresa->id)->update([
                'codigo' => (int) $empresa->id,
                'fantasia' => $empresa->nome,
                'razao_social' => $empresa->nome,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'fantasia', 'razao_social', 'cidade', 'cnpj', 'ie']);
        });
    }
};
