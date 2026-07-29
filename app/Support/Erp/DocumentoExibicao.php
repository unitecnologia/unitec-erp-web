<?php

namespace App\Support\Erp;

/**
 * Máscaras de documentos para exibição (DANFE/cupom), alinhadas à LGPD.
 * O XML fiscal continua com o CPF completo para a SEFAZ.
 */
final class DocumentoExibicao
{
    /**
     * CPF: mantém 3 primeiros e 2 últimos dígitos (ex.: 045.***.***-01).
     */
    public static function mascararCpf(?string $cpf): string
    {
        $digits = preg_replace('/\D/', '', (string) $cpf) ?? '';

        if (strlen($digits) !== 11) {
            return trim((string) $cpf);
        }

        return substr($digits, 0, 3).'.***.***-'.substr($digits, -2);
    }
}
