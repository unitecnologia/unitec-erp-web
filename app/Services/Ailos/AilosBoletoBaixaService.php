<?php

namespace App\Services\Ailos;

use App\Models\Boleto;
use App\Models\Empresa;
use App\Support\Erp\Boleto\Api\BoletoApi;
use App\Support\Erp\Boleto\Api\Drivers\AilosBoletoDriver;

/**
 * Compat: baixa Ailos. Preferir {@see BoletoApi} nos pontos de negócio.
 */
final class AilosBoletoBaixaService
{
    public function __construct(
        private readonly AilosBoletoDriver $driver,
        private readonly BoletoApi $boletoApi,
    ) {
    }

    /**
     * @return list<int>
     */
    public function baixarAbertosDaContaReceber(int $contaReceberId, ?Empresa $empresa = null): array
    {
        return $this->boletoApi->baixarAbertosDaContaReceber($contaReceberId, $empresa);
    }

    public function baixarBoleto(Boleto $boleto, ?Empresa $empresa = null): bool
    {
        return $this->driver->baixar($boleto, $empresa);
    }
}
