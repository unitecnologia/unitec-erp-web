<?php

namespace Unitec\FiscalEngine\Exception;

use RuntimeException;

final class FiscalEngineException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $sefazCodigo = null,
        public readonly ?string $sefazMotivo = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
