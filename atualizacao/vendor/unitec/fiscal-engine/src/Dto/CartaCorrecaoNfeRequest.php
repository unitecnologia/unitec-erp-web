<?php

namespace Unitec\FiscalEngine\Dto;

use Unitec\FiscalEngine\Certificate\Certificate;

final class CartaCorrecaoNfeRequest
{
    public function __construct(
        public readonly Certificate $certificate,
        public readonly string $cnpj,
        public readonly string $chave,
        public readonly string $correcao,
        public readonly int $tpAmb,
        public readonly int $nSeqEvento = 1,
        public readonly ?\DateTimeInterface $dataEvento = null,
    ) {}
}
