<?php

namespace Unitec\FiscalEngine\Dto;

final class NfeDestinatarioDto
{
    public function __construct(
        public readonly ?string $cpf,
        public readonly ?string $cnpj,
        public readonly string $nome,
        public readonly string $logradouro,
        public readonly string $numero,
        public readonly string $bairro,
        public readonly string $codigoMunicipio,
        public readonly string $municipio,
        public readonly string $uf,
        public readonly string $cep,
        public readonly int $indIeDest,
        public readonly ?string $ie = null,
        public readonly string $email = '',
        public readonly string $telefone = '',
    ) {}
}
