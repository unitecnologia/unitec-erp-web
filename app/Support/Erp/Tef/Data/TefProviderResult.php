<?php

namespace App\Support\Erp\Tef\Data;

use App\Models\TefIntencao;

/**
 * Resultado normalizado de operação TEF (qualquer provedor).
 *
 * @phpstan-type Meta array<string, mixed>
 */
final class TefProviderResult
{
    /**
     * @param  Meta  $meta
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $message,
        public readonly ?TefIntencao $intention = null,
        public readonly bool $transportError = false,
        public readonly bool $duplicatePrevented = false,
        public readonly bool $operational = true,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  Meta  $meta
     */
    public static function failure(
        string $message,
        ?TefIntencao $intention = null,
        bool $transportError = false,
        array $meta = [],
    ): self {
        return new self(
            ok: false,
            message: $message,
            intention: $intention,
            transportError: $transportError,
            meta: $meta,
        );
    }

    public static function notOperational(string $message): self
    {
        return new self(
            ok: false,
            message: $message,
            operational: false,
        );
    }

    /**
     * @param  Meta  $meta
     */
    public static function success(string $message, ?TefIntencao $intention = null, array $meta = []): self
    {
        return new self(
            ok: true,
            message: $message,
            intention: $intention,
            meta: $meta,
        );
    }
}
