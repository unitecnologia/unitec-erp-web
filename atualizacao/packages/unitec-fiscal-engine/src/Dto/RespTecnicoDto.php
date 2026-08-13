<?php

namespace Unitec\FiscalEngine\Dto;

use Unitec\FiscalEngine\Util\NumberFormatter;

final class RespTecnicoDto
{
    public function __construct(
        public readonly string $cnpj,
        public readonly string $contato,
        public readonly string $email,
        public readonly string $fone,
        public readonly ?string $idCsrt = null,
        public readonly ?string $hashCsrt = null,
        public readonly ?string $csrtToken = null,
    ) {}

    public function isComplete(): bool
    {
        return strlen(NumberFormatter::onlyDigits($this->cnpj)) === 14
            && trim($this->contato) !== ''
            && trim($this->email) !== ''
            && strlen(NumberFormatter::onlyDigits($this->fone)) >= 6;
    }
}
