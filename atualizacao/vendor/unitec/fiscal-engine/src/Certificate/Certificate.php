<?php

namespace Unitec\FiscalEngine\Certificate;

final class Certificate
{
    public function __construct(
        public readonly string $privateKeyPem,
        public readonly string $certificatePem,
        public readonly string $cnpj,
    ) {}
}
