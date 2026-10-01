<?php

declare(strict_types=1);

namespace App\Support\Pix\Ailos;

use RuntimeException;

final class AilosPixException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly mixed $providerBody = null,
    ) {
        parent::__construct($message, $status ?? 0);
    }
}
