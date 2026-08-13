<?php

namespace Unitec\FiscalEngine\Dto;

final class DestinatarioDto
{
    public function __construct(
        public readonly ?string $cpf = null,
        public readonly ?string $cnpj = null,
        public readonly ?string $nome = null,
        public readonly ?string $logradouro = null,
        public readonly ?string $numero = null,
        public readonly ?string $bairro = null,
        public readonly ?string $codigoMunicipio = null,
        public readonly ?string $municipio = null,
        public readonly ?string $uf = null,
        public readonly ?string $cep = null,
        public readonly ?string $telefone = null,
        public readonly ?string $email = null,
    ) {}

    public function hasEndereco(): bool
    {
        $cMun = preg_replace('/\D/', '', (string) $this->codigoMunicipio) ?? '';
        $cep = preg_replace('/\D/', '', (string) $this->cep) ?? '';

        return trim((string) $this->logradouro) !== ''
            && trim((string) $this->bairro) !== ''
            && strlen($cMun) === 7
            && trim((string) $this->municipio) !== ''
            && trim((string) $this->uf) !== ''
            && strlen($cep) === 8;
    }
}
