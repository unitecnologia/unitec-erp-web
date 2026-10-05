<?php

namespace App\Support\Erp;

/**
 * Termo digitado na grade de produtos.
 * A pesquisa grava aqui para impressão, abas e troca de campo
 * lerem o texto sem um request extra na página Filament.
 */
final class ProductListLiveSearch
{
    public const SESSION_KEY = 'erp_produtos_list_q';

    public static function put(string $term): void
    {
        session([self::SESSION_KEY => $term]);
    }

    public static function get(): ?string
    {
        if (! session()->has(self::SESSION_KEY)) {
            return null;
        }

        $value = session(self::SESSION_KEY);

        return is_string($value) ? $value : null;
    }
}
