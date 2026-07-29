<?php

namespace Unitec\FiscalEngine\Dto;

final class EmitenteDto
{
    public function __construct(
        public readonly string $cnpj,
        public readonly string $razaoSocial,
        public readonly string $nomeFantasia,
        public readonly string $ie,
        public readonly int $crt,
        public readonly string $logradouro,
        public readonly string $numero,
        public readonly string $bairro,
        public readonly string $codigoMunicipio,
        public readonly string $municipio,
        public readonly string $uf,
        public readonly string $cep,
        public readonly string $telefone = '',
    ) {}
}
