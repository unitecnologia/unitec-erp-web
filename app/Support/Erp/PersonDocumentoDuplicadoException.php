<?php

namespace App\Support\Erp;

use App\Models\Person;
use RuntimeException;

final class PersonDocumentoDuplicadoException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?Person $existente = null,
    ) {
        parent::__construct($message);
    }
}
