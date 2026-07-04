<?php



use Illuminate\Database\Migrations\Migration;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;



return new class extends Migration

{

    public function up(): void

    {

        Schema::create('vendas', function (Blueprint $table) {

            $table->id();

            $table->string('numero', 20)->unique();

            $table->date('data');

            $table->time('hora')->nullable();

            $table->foreignId('cliente_id')->constrained('people')->cascadeOnDelete();

            $table->foreignId('vendedor_id')->nullable()->constrained('people')->nullOnDelete();

            $table->decimal('total', 15, 2)->default(0);

            $table->string('status', 20)->default('aberto');

            $table->string('tipo', 20)->default('pedido');

            $table->timestamps();



            $table->index('data');

            $table->index('status');

            $table->index('tipo');

        });

    }



    public function down(): void

    {

        Schema::dropIfExists('vendas');

    }

};

