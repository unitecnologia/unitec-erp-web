<?php

use App\Support\Erp\Tef\SuperTefFormasPagamento;
use Illuminate\Database\Migrations\Migration;

/**
 * Limpeza: desativa SUPERTEF DEBITO/CREDITO seedadas sem terminal SuperTEF habilitado.
 * Não exclui registros; clientes com SuperTEF ativo no terminal permanecem intactos.
 */
return new class extends Migration
{
    public function up(): void
    {
        SuperTefFormasPagamento::desativarSeSemSuperTef();
    }

    public function down(): void
    {
        // Não reativa automaticamente — reativação ocorre ao habilitar SuperTEF no terminal.
    }
};
