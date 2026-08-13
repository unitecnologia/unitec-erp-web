<?php

namespace App\Support\Pix\Data;

/**
 * Resultado normalizado da criação de uma cobrança Pix.
 *
 * O $status já vem normalizado para os status do model PixCobranca
 * ('pendente' | 'pago' | 'expirado' | 'cancelado').
 */
final class PixCobrancaResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $providerRef,
        public readonly string $qrCopiaCola,
        public readonly ?string $qrImagemBase64,
        public readonly string $status,
        public readonly array $raw = [],
    ) {
    }
}
