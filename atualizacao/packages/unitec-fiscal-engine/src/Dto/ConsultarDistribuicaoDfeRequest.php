<?php

namespace Unitec\FiscalEngine\Dto;

use Unitec\FiscalEngine\Certificate\Certificate;

final class ConsultarDistribuicaoDfeRequest
{
    public function __construct(
        public readonly Certificate $certificate,
        public readonly string $cnpj,
        public readonly string $cUfAutor,
        public readonly int $tpAmb,
        public readonly string $ultNsu = '000000000000000',
        public readonly ?string $chave = null,
    ) {}
}
