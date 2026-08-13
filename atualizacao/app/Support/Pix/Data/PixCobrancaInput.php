<?php

namespace App\Support\Pix\Data;

use Illuminate\Support\Carbon;

/**
 * Dados de entrada para criar uma cobrança Pix em qualquer provedor.
 */
final class PixCobrancaInput
{
    public function __construct(
        public readonly float $valor,
        public readonly string $descricao,
        public readonly string $txid,
        public readonly Carbon $expiraEm,
        public readonly ?string $payerEmail = null,
        public readonly ?string $externalReference = null,
        public readonly ?string $notificationUrl = null,
    ) {
    }
}
