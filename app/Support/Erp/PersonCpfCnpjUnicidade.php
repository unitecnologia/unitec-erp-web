<?php

namespace App\Support\Erp;

use App\Models\Person;

/**
 * Unicidade global obrigatória de CPF/CNPJ em people (somente dígitos).
 * Usa coluna indexada people.cpf_cnpj_digits (sem REPLACE).
 */
final class PersonCpfCnpjUnicidade
{
    public function digits(?string $value): string
    {
        return DocumentoBrasileiroValidator::digits($value);
    }

    /**
     * Localiza outra pessoa com o mesmo documento (máscara irrelevante).
     * Pessoa inativa também conta. Ignore o próprio ID na edição.
     */
    public function encontrar(?string $cpfCnpj, int|string|null $ignorePersonId = null): ?Person
    {
        $digits = $this->digits($cpfCnpj);

        if ($digits === '' || ! in_array(strlen($digits), [11, 14], true)) {
            return null;
        }

        $query = Person::query()->where('cpf_cnpj_digits', $digits);

        if ($ignorePersonId !== null && (int) $ignorePersonId > 0) {
            $query->whereKeyNot((int) $ignorePersonId);
        }

        return $query
            ->orderByDesc('ativo')
            ->orderBy('id')
            ->first();
    }

    /**
     * Null = documento livre (ou vazio). String = mensagem de bloqueio.
     */
    public function mensagemDuplicado(?string $cpfCnpj, int|string|null $ignorePersonId = null): ?string
    {
        $existente = $this->encontrar($cpfCnpj, $ignorePersonId);

        if (! $existente) {
            return null;
        }

        return $this->formatMensagem($cpfCnpj, $existente);
    }

    /**
     * @throws PersonDocumentoDuplicadoException
     */
    public function assertDisponivel(?string $cpfCnpj, int|string|null $ignorePersonId = null): void
    {
        $existente = $this->encontrar($cpfCnpj, $ignorePersonId);

        if (! $existente) {
            return;
        }

        throw new PersonDocumentoDuplicadoException(
            $this->formatMensagem($cpfCnpj, $existente),
            $existente,
        );
    }

    private function formatMensagem(?string $cpfCnpj, Person $existente): string
    {
        $digits = $this->digits($cpfCnpj);
        $tipo = strlen($digits) === 14 ? 'CNPJ' : 'CPF';
        $base = "Já existe um cadastro com este {$tipo}.";

        $codigo = trim((string) ($existente->codigo ?? ''));
        $nome = trim((string) ($existente->nome_razao ?? ''));

        if ($codigo === '' && $nome === '') {
            return $base;
        }

        $ref = $codigo !== '' ? 'código '.$codigo : '';
        if ($nome !== '') {
            $ref = $ref !== '' ? $ref.' — '.$nome : $nome;
        }

        return $base.' ('.$ref.').';
    }
}
