<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos mínimos para Ailos BolePix (API Cobrança V2).
 *
 * - empresas: senha da API do cooperado + callback de autenticação (JWT).
 * - boletos: código de barras, PIX híbrido, id externo e liquidação.
 *
 * Credenciais sensíveis seguem o mesmo padrão de param_boleto_client_secret (text nullable, sem encrypt cast).
 * Webhook de eventos/liquidação NÃO é este callback — será endpoint separado depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_boleto_senha_api')) {
                // Mesmo tipo de param_boleto_client_secret (text nullable).
                $table->text('param_boleto_senha_api')->nullable();
            }

            if (! Schema::hasColumn('empresas', 'param_boleto_callback_url')) {
                // Callback de autenticação Ailos (x-ailos-authentication), não webhook de boleto.
                $table->string('param_boleto_callback_url')->nullable();
            }
        });

        Schema::table('boletos', function (Blueprint $table): void {
            if (! Schema::hasColumn('boletos', 'codigo_barras')) {
                $table->string('codigo_barras', 100)->nullable()->after('linha_digitavel');
            }

            if (! Schema::hasColumn('boletos', 'pix_qr_base64')) {
                // Mesmo padrão de pix_cobrancas.qr_imagem_base64.
                $table->longText('pix_qr_base64')->nullable()->after('path_pdf');
            }

            if (! Schema::hasColumn('boletos', 'pix_copia_cola')) {
                // Mesmo padrão de pix_cobrancas.qr_copia_cola.
                $table->text('pix_copia_cola')->nullable()->after('pix_qr_base64');
            }

            if (! Schema::hasColumn('boletos', 'id_externo')) {
                $table->string('id_externo', 100)->nullable()->index()->after('codigo_legado');
            }

            if (! Schema::hasColumn('boletos', 'pago_em')) {
                $table->timestamp('pago_em')->nullable()->after('id_externo');
            }

            if (! Schema::hasColumn('boletos', 'valor_pago')) {
                // Mesma precisão de boletos.valor (decimal 15,2).
                $table->decimal('valor_pago', 15, 2)->nullable()->after('pago_em');
            }
        });
    }

    public function down(): void
    {
        Schema::table('boletos', function (Blueprint $table): void {
            $cols = array_values(array_filter(
                ['codigo_barras', 'pix_qr_base64', 'pix_copia_cola', 'id_externo', 'pago_em', 'valor_pago'],
                fn (string $col): bool => Schema::hasColumn('boletos', $col),
            ));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('empresas', function (Blueprint $table): void {
            $cols = array_values(array_filter(
                ['param_boleto_senha_api', 'param_boleto_callback_url'],
                fn (string $col): bool => Schema::hasColumn('empresas', $col),
            ));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
