<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ncms', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 8)->unique();
            $table->string('descricao');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_restaurante')->default(false)->after('prefixo_balanca');
            $table->string('tipo_restaurante', 30)->nullable()->after('is_restaurante');
            $table->text('complemento')->nullable()->after('tipo_restaurante');
            $table->unsignedInteger('tempo_espera')->default(0)->after('complemento');
            $table->boolean('is_remedio')->default(false)->after('tempo_espera');
            $table->text('aplicacao')->nullable()->after('is_remedio');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_restaurante',
                'tipo_restaurante',
                'complemento',
                'tempo_espera',
                'is_remedio',
                'aplicacao',
            ]);
        });

        Schema::dropIfExists('ncms');
    }
};
