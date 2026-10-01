<?php

declare(strict_types=1);

namespace App\Support\Pix\Ailos;

final readonly class AilosPixResult
{
    public function __construct(
        public string $txid,
        public string $brCode,
        public string $qrCodeBase64,
        public string $expirationDate,
    ) {
    }

    /**
     * @return array{txid: string, brCode: string, qrCodeBase64: string, expirationDate: string}
     */
    public function toArray(): array
    {
        return [
            'txid' => $this->txid,
            'brCode' => $this->brCode,
            'qrCodeBase64' => $this->qrCodeBase64,
            'expirationDate' => $this->expirationDate,
        ];
    }
}
