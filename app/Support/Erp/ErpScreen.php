<?php

namespace App\Support\Erp;

class ErpScreen
{
    public static function set(string $screen): void
    {
        session(['erp_screen' => $screen]);
    }

    public static function current(): string
    {
        return session('erp_screen', 'Principal');
    }
}
