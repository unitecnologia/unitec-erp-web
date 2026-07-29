<?php

namespace Unitec\FiscalEngine\Dto;

use Unitec\FiscalEngine\Certificate\Certificate;

final class InutilizarNfceRequest
{
    public function __construct(
        public readonly Certificate $certificate,
        public readonly string $cnpj,
        public readonly int $tpAmb,
        public readonly int $serie,
        public readonly int $numeroInicial,
        public readonly int $numeroFinal,
        public readonly string $justificativa,
        public readonly string $modelo = '65',
        public readonly ?\DateTimeInterface $dataEvento = null,
    ) {}
}
