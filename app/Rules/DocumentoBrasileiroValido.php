<?php

namespace App\Rules;

use App\Support\Erp\DocumentoBrasileiroValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class DocumentoBrasileiroValido implements ValidationRule
{
    public function __construct(
        private ?string $pessoaTipo = null,
        private bool $cpfOnly = false,
        private bool $cnpjOnly = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = match (true) {
            $this->cpfOnly => DocumentoBrasileiroValidator::mensagemCpf((string) $value),
            $this->cnpjOnly => DocumentoBrasileiroValidator::mensagemCnpj((string) $value),
            default => DocumentoBrasileiroValidator::mensagemCpfCnpj((string) $value, $this->pessoaTipo),
        };

        if ($message !== null) {
            $fail($message);
        }
    }
}
