<?php

namespace Unitec\FiscalEngine\Dto;

final class CancelarNfceResponse
{
    public function __construct(
        public readonly bool $cancelada,
        public readonly string $chave,
        public readonly string $protocoloEvento,
        public readonly string $xml,
        public readonly string $statusCodigo,
        public readonly string $statusMotivo,
    ) {}
}
