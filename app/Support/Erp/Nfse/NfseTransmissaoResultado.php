<?php

namespace App\Support\Erp\Nfse;

use App\Models\Nfse;

final class NfseTransmissaoResultado
{
    /**
     * @param  list<array{codigo: string, descricao: string}>  $erros
     * @param  list<mixed>  $alertas
     */
    public function __construct(
        public readonly Nfse $nfse,
        public readonly bool $autorizada,
        public readonly array $erros,
        public readonly array $alertas,
        public readonly bool $modoTeste = false,
    ) {}

    public function mensagemErros(): string
    {
        $linhas = [];

        foreach ($this->erros as $erro) {
            $codigo = trim((string) ($erro['codigo'] ?? ''));
            $descricao = trim((string) ($erro['descricao'] ?? ''));
            $linhas[] = $codigo !== '' && $descricao !== ''
                ? $codigo.' — '.$descricao
                : ($codigo !== '' ? $codigo : $descricao);
        }

        return implode("\n", array_values(array_filter($linhas, fn (string $linha): bool => $linha !== '')));
    }
}
