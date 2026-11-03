<?php

namespace App\Support\Fiscal;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * CEST no XML: 7 dígitos (zeros à esquerda preservados/restaurados).
 * Não calcula ST. Campo vazio = não emitir; preenchido inválido = erro explícito.
 */
final class CestCodigo
{
    /**
     * @return non-empty-string|null  null se não informado
     */
    public static function normalizar(?string $raw, string $contexto = 'produto'): ?string
    {
        $trimmed = trim((string) $raw);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $trimmed) ?? '';
        if ($digits === '') {
            throw new FiscalEngineException(
                'CEST do '.$contexto.' inválido ("'.$trimmed.'"). Informe 7 dígitos ou deixe em branco.',
            );
        }

        if (strlen($digits) > 7) {
            throw new FiscalEngineException(
                'CEST do '.$contexto.' deve ter 7 dígitos (informado: '.$digits.').',
            );
        }

        // Restaura zeros à esquerda perdidos em importação/cadastro numérico.
        return str_pad($digits, 7, '0', STR_PAD_LEFT);
    }
}
