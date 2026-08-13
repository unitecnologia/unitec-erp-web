<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('menu_id')->nullable()->after('tipo_restaurante');
            $table->string('tipo_alimento', 1)->nullable()->after('menu_id');
            $table->unsignedInteger('qtd_sabores')->default(0)->after('tipo_alimento');
            $table->decimal('valor_pequena', 12, 4)->default(0)->after('qtd_sabores');
            $table->decimal('valor_media', 12, 4)->default(0)->after('valor_pequena');
            $table->decimal('valor_grande', 12, 4)->default(0)->after('valor_media');
        });

        Schema::create('product_imeis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('imei', 250);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'imei']);
            $table->index('imei');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imeis');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'menu_id',
                'tipo_alimento',
                'qtd_sabores',
                'valor_pequena',
                'valor_media',
                'valor_grande',
            ]);
        });
    }
};
