<?php

namespace Unitec\FiscalEngine\Dto;

final class CartaCorrecaoNfeResponse
{
    public function __construct(
        public readonly bool $registrada,
        public readonly string $chave,
        public readonly int $nSeqEvento,
        public readonly string $protocoloEvento,
        public readonly string $xml,
        public readonly string $statusCodigo,
        public readonly string $statusMotivo,
    ) {}
}
