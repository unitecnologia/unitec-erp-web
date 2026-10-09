<?php

namespace App\Support\Erp\Hotfix;

use RuntimeException;

/**
 * Pacote ou instalação não aceitos. Definitivo = o mesmo pacote nunca será tentado de novo.
 */
final class HotfixRecusadoException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $definitivo)
    {
        parent::__construct($message);
    }
}
