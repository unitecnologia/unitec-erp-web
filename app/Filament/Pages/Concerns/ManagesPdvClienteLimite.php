<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Person;
use App\Support\Erp\Pdv\PdvClienteLimiteService;

trait ManagesPdvClienteLimite
{
    public function getPdvChecarLimiteClienteProperty(): bool
    {
        return $this->pdvConfig()->checarLimiteCliente();
    }

    /**
     * @return array{limite: string, aberto: string, disponivel: string, venda: string}|null
     */
    public function getFinalizarLimiteClienteResumoProperty(): ?array
    {
        if (! $this->pdvChecarLimiteCliente || ! $this->finalizarClienteId) {
            return null;
        }

        return (new PdvClienteLimiteService())->resumo(
            $this->finalizarClienteId,
            $this->finalizarTotalVendaValor(),
        );
    }

    protected function validaLimiteClienteFinalizar(float $totalVenda): ?string
    {
        if (! $this->pdvConfig()->checarLimiteCliente() || ! $this->finalizarClienteId) {
            return null;
        }

        $person = Person::query()->find($this->finalizarClienteId);

        if (! $person) {
            return null;
        }

        return (new PdvClienteLimiteService())->valida($person, $totalVenda);
    }
}
