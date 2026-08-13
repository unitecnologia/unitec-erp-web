<?php

namespace Unitec\FiscalEngine\Dto;

final class ConsultarNfceResponse
{
    public function __construct(
        public readonly string $chave,
        public readonly string $statusCodigo,
        public readonly string $statusMotivo,
        public readonly string $protocolo,
        public readonly string $xml,
        public readonly bool $autorizada,
        public readonly bool $cancelada,
        public readonly bool $denegada,
    ) {}
}
