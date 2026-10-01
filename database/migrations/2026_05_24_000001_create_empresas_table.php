<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('logo_path')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Evita row size 1118 quando muitas colunas param_* forem adicionadas depois.
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $table = Schema::getConnection()->getTablePrefix().'empresas';
            try {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE `{$table}` ROW_FORMAT=DYNAMIC");
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
