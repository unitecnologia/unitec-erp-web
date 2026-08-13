<?php

namespace App\Support\Erp\Expedicao;

use App\Models\Empresa;
use App\Models\Entrega;
use Illuminate\Support\Facades\Auth;

final class ExpedicaoConfig
{
    private ?Empresa $empresa;

    public function __construct(?Empresa $empresa = null)
    {
        $this->empresa = $empresa ?? $this->resolveEmpresa();
    }

    public static function make(?Empresa $empresa = null): self
    {
        return new self($empresa);
    }

    public function ativa(): bool
    {
        return (bool) ($this->empresa?->param_expedicao_ativar ?? false);
    }

    public function pedirQuantidade(): bool
    {
        return (bool) ($this->empresa?->param_expedicao_pedir_quantidade ?? false);
    }

    public function maxPedidosControle(): int
    {
        $max = (int) ($this->empresa?->param_expedicao_max_pedidos_controle ?? 5);

        return max(1, $max);
    }

    public function origemHabilitada(string $origem): bool
    {
        if (! $this->ativa()) {
            return false;
        }

        return match ($origem) {
            Entrega::ORIGEM_PDV => (bool) ($this->empresa?->param_expedicao_origem_pdv ?? true),
            Entrega::ORIGEM_MONITOR => (bool) ($this->empresa?->param_expedicao_origem_monitor ?? true),
            Entrega::ORIGEM_VI => (bool) ($this->empresa?->param_expedicao_origem_vi ?? true),
            Entrega::ORIGEM_ERP => (bool) ($this->empresa?->param_expedicao_origem_erp ?? true),
            default => false,
        };
    }

    private function resolveEmpresa(): ?Empresa
    {
        $user = Auth::user();
        $empresaId = session('erp_empresa_id', $user?->empresa_id);

        if ($empresaId) {
            return Empresa::query()->find($empresaId);
        }

        return $user?->empresa;
    }
}
