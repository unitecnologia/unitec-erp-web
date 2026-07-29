<?php

namespace Unitec\FiscalEngine\Dto;

use Unitec\FiscalEngine\Certificate\Certificate;

final class ConsultarNfceRequest
{
    public function __construct(
        public readonly Certificate $certificate,
        public readonly string $chave,
        public readonly int $tpAmb,
    ) {}
}
