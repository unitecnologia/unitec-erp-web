<?php

namespace Unitec\PdvUi\Support;

final class PdvFormas
{
    public static function isFormaCrediario(string $forma): bool
    {
        $forma = mb_strtoupper(trim($forma), 'UTF-8');

        return str_contains($forma, 'CREDIÁRIO') || str_contains($forma, 'CREDIARIO');
    }
}
