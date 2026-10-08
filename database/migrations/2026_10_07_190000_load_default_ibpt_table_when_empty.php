<?php

use App\Support\Erp\Fiscal\IbptTabelaPadrao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suíte de testes migra SQLite :memory: a cada processo; carregar ~12k linhas lá só deixaria tudo lento.
        if (! Schema::hasTable('fiscal_ibpt_itens') || app()->runningUnitTests()) {
            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        try {
            IbptTabelaPadrao::carregarSeVazia();
        } catch (\Throwable $e) {
            // Atualização do cliente não pode parar por causa da tabela padrão; importação manual continua disponível.
            report($e);
        }
    }

    public function down(): void
    {
        // Dados fiscais do cliente: nunca removidos em rollback.
    }
};
