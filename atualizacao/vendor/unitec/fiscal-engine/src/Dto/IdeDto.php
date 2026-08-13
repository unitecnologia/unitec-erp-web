<?php

namespace Unitec\FiscalEngine\Dto;

final class IdeDto
{
    public function __construct(
        public readonly int $serie,
        public readonly int $numero,
        public readonly int $cNf,
        public readonly int $tpAmb,
        public readonly int $tpEmis,
        public readonly string $natOp,
        public readonly string $codigoMunicipioFg,
        public readonly \DateTimeInterface $dataEmissao,
        public readonly ?string $justificativaContingencia = null,
        public readonly ?\DateTimeInterface $dataContingencia = null,
    ) {}
}
