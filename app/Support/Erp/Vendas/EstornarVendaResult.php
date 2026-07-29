<?php

namespace App\Support\Erp\Vendas;

final class EstornarVendaResult
{
    public function __construct(
        public readonly int $vendaId,
        public readonly ?int $pdvVendaId = null,
        public readonly ?string $protocoloCancelamento = null,
        public readonly bool $alreadyCancelled = false,
        public readonly string $plataforma = 'erp',
    ) {}
}
