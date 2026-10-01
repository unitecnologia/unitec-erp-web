<?php

namespace App\Support\Erp\Nfse;

final class NfseSefinRequisicao
{
    /**
     * @param  array<string, string>  $headers
     * @param  array{certificado: string, chave_privada: string}  $mtls
     */
    public function __construct(
        public readonly string $url,
        public readonly string $metodo,
        public readonly array $headers,
        public readonly string $corpo,
        public readonly array $mtls,
    ) {}
}
