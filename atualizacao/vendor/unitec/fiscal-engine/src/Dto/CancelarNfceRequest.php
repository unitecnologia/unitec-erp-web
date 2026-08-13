<?php

namespace Unitec\FiscalEngine\Dto;

use Unitec\FiscalEngine\Certificate\Certificate;

final class CancelarNfceRequest
{
    public function __construct(
        public readonly Certificate $certificate,
        public readonly string $cnpj,
        public readonly string $chave,
        public readonly string $protocoloAutorizacao,
        public readonly string $justificativa,
        public readonly int $tpAmb,
        public readonly ?\DateTimeInterface $dataEvento = null,
    ) {}
}
