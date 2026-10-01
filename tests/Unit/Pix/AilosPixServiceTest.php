<?php

declare(strict_types=1);

namespace Tests\Unit\Pix;

use App\Support\Pix\Ailos\AilosPixConfig;
use App\Support\Pix\Ailos\AilosPixService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AilosPixServiceTest extends TestCase
{
    public function test_reuses_existing_charge_without_put(): void
    {
        Cache::flush();

        $payload = $this->validBrCode();
        $txid = 'AILOSREF00001ABCDEF1234567890AB';

        Http::fake([
            '*/client/connect/token' => Http::response([
                'access_token' => 'token-teste',
                'expires_in' => 300,
            ], 200),
            '*/cob/'.$txid => Http::response([
                'status' => 'ATIVA',
                'calendario' => [
                    'criacao' => now()->toIso8601String(),
                    'expiracao' => 3600,
                ],
            ], 200),
            '*/qrcode/consulta/'.$txid => Http::response([
                'copiaCola' => base64_encode($payload),
            ], 200),
        ]);

        $service = new AilosPixService($this->config());
        $result = $service->createCharge([
            'amount' => '1.00',
            'description' => 'Teste',
            'txid' => $txid,
        ]);

        self::assertSame($txid, $result->txid);
        self::assertSame($payload, $result->brCode);
        self::assertNotSame('', $result->qrCodeBase64);

        Http::assertSentCount(3); // token + GET cob + GET qr (sem PUT)
    }

    public function test_payment_status_mapping(): void
    {
        Cache::flush();

        Http::fake([
            '*/client/connect/token' => Http::response([
                'access_token' => 'token-teste',
                'expires_in' => 300,
            ], 200),
            '*/cob/TX1' => Http::response(['status' => 'CONCLUIDA'], 200),
        ]);

        $service = new AilosPixService($this->config());

        self::assertSame('approved', $service->getPaymentStatus('TX1'));
    }

    private function config(): AilosPixConfig
    {
        $dummyPfx = base64_encode(str_repeat('A', 200));

        return new AilosPixConfig(
            environment: AilosPixConfig::ENV_HOMOLOGATION,
            baseUrl: 'https://pixcobranca-h.ailos.coop.br/qa/ailos/pix-cobranca/api/v1',
            clientId: 'client',
            clientSecret: 'secret',
            pixKey: 'chave-pix',
            pfxPath: null,
            pfxBase64: $dummyPfx,
            pfxPassword: 'senha',
            timeout: 10,
            expirationSeconds: 3600,
            tokenScopes: 'cob.read cob.write',
        );
    }

    private function validBrCode(): string
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
