<?php

namespace Unitec\FiscalEngine\Dto;

final class EmitirNfeResponse
{
    public function __construct(
        public readonly bool $autorizada,
        public readonly string $chave,
        public readonly string $protocolo,
        public readonly string $xml,
        public readonly string $statusCodigo,
        public readonly string $statusMotivo,
        public readonly int $numero,
        public readonly int $serie,
        public readonly int $cNf,
    ) {}
}
