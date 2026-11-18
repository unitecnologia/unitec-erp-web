<?php

namespace App\Support\Erp\Tef\Data;

/**
 * Parâmetros opcionais de cobrança (sem dados de cartão).
 * Campos extras do provedor ficam em $meta — nunca PAN/CVV.
 */
final class TefChargeCommand
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly ?string $description = null,
        public readonly array $meta = [],
    ) {}
}
