<?php

namespace App\Support\Erp\Printing;

interface PrintDocument
{
    public function key(): string;

    /** URL HTML do documento (preview / QZ HTML / fallback navegador). */
    public function htmlUrl(bool $autoPrint = false, int $copies = 1): string;

    /**
     * Payload enviado ao JS (ErpPrint).
     *
     * @return array<string, mixed>
     */
    public function clientPayload(PrintTarget $target): array;
}
