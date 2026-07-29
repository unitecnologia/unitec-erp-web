<?php

namespace Unitec\FiscalEngine\Dto;

final class DfeResumoNfe
{
    public function __construct(
        public readonly string $nsu,
        public readonly string $schema,
        public readonly string $chave,
        public readonly string $cnpj,
        public readonly string $nome,
        public readonly string $numero,
        public readonly \DateTimeInterface $dataEmissao,
        public readonly ?\DateTimeInterface $dataRecebimento,
        public readonly float $total,
        public readonly string $xml,
    ) {}
}
