<?php

namespace App\Support\Erp\Rh;

/**
 * Snapshot leve do formulário de funcionário para detectar alterações não salvas.
 * Sem consulta ao banco.
 */
final class RhFuncionarioFormSnapshot
{
    /**
     * @param  array<string, mixed>  $form
     */
    public static function hash(array $form): string
    {
        return hash(
            'sha256',
            json_encode(self::normalize($form), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: ''
        );
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public static function normalize(array $form): array
    {
        ksort($form);

        foreach ($form as $key => $value) {
            if ($key === 'terminais') {
                $ids = array_values(array_map('intval', is_array($value) ? $value : []));
                sort($ids);
                $form[$key] = $ids;

                continue;
            }

            if (is_bool($value)) {
                $form[$key] = $value ? '1' : '0';

                continue;
            }

            if ($value === null) {
                $form[$key] = '';

                continue;
            }

            if (is_scalar($value)) {
                $form[$key] = trim((string) $value);
            }
        }

        return $form;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    public static function hasChanges(?string $baselineHash, array $form): bool
    {
        if ($baselineHash === null || $baselineHash === '') {
            return false;
        }

        return self::hash($form) !== $baselineHash;
    }
}
