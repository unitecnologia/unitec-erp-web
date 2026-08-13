<?php

namespace App\Rules;

use App\Support\Erp\WhatsApp\WhatsAppPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class CelularBrasileiroValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = WhatsAppPhone::mensagemCelular(is_string($value) ? $value : (string) $value);

        if ($message !== null) {
            $fail($message);
        }
    }
}
