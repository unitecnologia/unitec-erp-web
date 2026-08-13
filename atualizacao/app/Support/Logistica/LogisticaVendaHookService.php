<?php

namespace App\Support\Logistica;

use App\Models\Entrega;
use App\Models\Venda;

final class LogisticaVendaHookService
{
    public function onVendaFechada(Venda $venda, string $origem): ?Entrega
    {
        return (new ExpedicaoService())->criarAPartirDaVenda($venda, $origem);
    }

    public function onVendaCancelada(Venda $venda, ?string $motivo = null): void
    {
        (new ExpedicaoService())->cancelarPorVenda($venda, $motivo);
    }
}
