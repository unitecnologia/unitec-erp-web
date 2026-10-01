<?php

declare(strict_types=1);

namespace Tests\Unit\Pix;

use App\Support\Pix\Ailos\AilosBrCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AilosBrCodeTest extends TestCase
{
    public function test_decodes_ailos_base64_payload(): void
    {
        $payload = $this->validPayload();

        self::assertSame($payload, AilosBrCode::normalize(base64_encode($payload)));
    }

    public function test_keeps_decoded_payload(): void
    {
        $payload = $this->validPayload();

        self::assertSame($payload, AilosBrCode::normalize($payload));
    }

    public function test_rejects_invalid_payload(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AilosBrCode::normalize(base64_encode('000201 código inválido'));
    }

    private function validPayload(): string
    {
        $withoutCrc =
            '000201'
            .'010212'
            .'26330014BR.GOV.BCB.PIX0111example.com'
            .'52040000'
            .'5303986'
            .'54041.00'
            .'5802BR'
            .'5904LOJA'
            .'6009SAO PAULO'
            .'62070503***'
            .'6304';

        $crc = 0xFFFF;
        for ($index = 0; $index < strlen($withoutCrc); $index++) {
            $crc ^= ord($withoutCrc[$index]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0
                    ? (($crc << 1) ^ 0x1021) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return $withoutCrc.strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
