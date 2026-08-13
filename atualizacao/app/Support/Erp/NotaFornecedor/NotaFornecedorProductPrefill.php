<?php

namespace App\Support\Erp\NotaFornecedor;

/**
 * Prefill do cadastro de produto a partir do item do XML (Importar XML).
 */
final class NotaFornecedorProductPrefill
{
    public const SESSION_KEY = 'erp_nf_forn_product_prefill';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function store(array $payload): void
    {
        session([self::SESSION_KEY => $payload]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function peek(): ?array
    {
        $payload = session(self::SESSION_KEY);

        return is_array($payload) ? $payload : null;
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
