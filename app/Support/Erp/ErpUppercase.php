<?php

namespace App\Support\Erp;

final class ErpUppercase
{
    /** @var list<string> */
    private const LOWERCASE_FIELDS = [
        'tipo_atividade',
        'pessoa_tipo',
        'regime_tributario',
        'logo_path',
        'foto_path',
        'param_pix_ambiente',
        'param_whatsapp_status',
    ];

    /** @var list<string> */
    private const PRESERVE_CASE_FIELDS = [
        'param_whatsapp_interno_chave',
        'password',
        'password_confirmation',
        'senha',
        'senha_atual',
        'senha_nova',
        'senha_confirmacao',
        'access_token',
        'refresh_token',
        'client_secret',
        'api_key',
        'api_secret',
        'token',
        'param_pix_mp_access_token',
        'param_pix_mp_client_secret',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = self::normalizeFormData($value);

                continue;
            }

            $normalized[$key] = self::normalizeFieldValue((string) $key, $value);
        }

        return $normalized;
    }

    public static function normalizeFieldValue(string $field, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (self::shouldLowercase($field)) {
            return mb_strtolower(trim($value), 'UTF-8');
        }

        if (self::shouldPreserveCase($field)) {
            return trim($value);
        }

        return self::uppercase($value);
    }

    public static function uppercase(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return mb_strtoupper($value, 'UTF-8');
    }

    private static function shouldLowercase(string $field): bool
    {
        return in_array(strtolower($field), self::LOWERCASE_FIELDS, true);
    }

    private static function shouldPreserveCase(string $field): bool
    {
        return in_array(strtolower($field), self::PRESERVE_CASE_FIELDS, true);
    }
}
