<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pix_cobrancas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();

            // Origem da cobrança: 'pedido' (app, venda ainda não existe) ou
            // 'titulo' (conta a receber já existente no ERP).
            $table->string('origem', 20);
            $table->string('order_uuid', 100)->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('conta_receber_id')->nullable();
            $table->unsignedBigInteger('venda_id')->nullable();

            $table->string('provedor', 30)->default('mercadopago');
            $table->string('txid', 100)->nullable();
            $table->string('provider_ref', 100)->nullable()->index();

            $table->decimal('valor', 12, 2)->default(0);
            $table->string('status', 20)->default('pendente')->index();

            $table->text('qr_copia_cola')->nullable();
            $table->longText('qr_imagem_base64')->nullable();
            $table->string('payer_email', 150)->nullable();

            $table->timestamp('expira_em')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->json('raw')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pix_cobrancas');
    }
};
