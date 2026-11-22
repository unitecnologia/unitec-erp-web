<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Legacy: não cria mais SUPERTEF DEBITO no migrate.
 * Formas SuperTEF passam a ser criadas/ativadas só ao habilitar o SuperTEF no terminal
 * ({@see \App\Support\Erp\Tef\SuperTefFormasPagamento}).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intencionalmente vazio — evita cadastro automático em instalação/importação.
    }

    public function down(): void
    {
        //
    }
};
