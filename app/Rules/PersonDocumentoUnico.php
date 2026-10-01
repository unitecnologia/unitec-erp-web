<?php

namespace App\Rules;

use App\Support\Erp\PersonCpfCnpjUnicidade;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Unicidade global de CPF/CNPJ em people (dígitos). Independente do parâmetro da empresa.
 */
final class PersonDocumentoUnico implements ValidationRule
{
    public function __construct(
        private int|string|null $ignorePersonId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $mensagem = app(PersonCpfCnpjUnicidade::class)
            ->mensagemDuplicado(is_string($value) || is_numeric($value) ? (string) $value : null, $this->ignorePersonId);

        if ($mensagem !== null) {
            $fail($mensagem);
        }
    }
}
