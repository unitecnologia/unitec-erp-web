<?php

namespace App\Support\Erp\Expedicao;

use App\Models\Entrega;

final class ExpedicaoConfig
{
    public static function make(?object $empresa = null): self
    {
        return new self();
    }

    public function ativa(): bool
    {
        return true;
    }

    public function pedirQuantidade(): bool
    {
        return true;
    }

    public function maxPedidosControle(): int
    {
        return 5;
    }

    public function origemHabilitada(string $origem): bool
    {
        return match ($origem) {
            Entrega::ORIGEM_PDV,
            Entrega::ORIGEM_MONITOR,
            Entrega::ORIGEM_VI,
            Entrega::ORIGEM_ERP => true,
            default => false,
        };
    }
}
