<?php

namespace App\Support\Erp\Ccg;

use RuntimeException;

class CcgConsultaException extends RuntimeException
{
    public const REJEICAO = 'rejeicao';

    public const CERTIFICADO = 'certificado';

    public const TIMEOUT = 'timeout';

    public const INDISPONIVEL = 'indisponivel';

    public function __construct(
        string $message,
        public readonly string $tipo = self::INDISPONIVEL,
    ) {
        parent::__construct($message);
    }
}
