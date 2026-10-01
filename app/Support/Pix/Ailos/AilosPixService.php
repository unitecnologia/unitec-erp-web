<?php

declare(strict_types=1);

namespace App\Support\Pix\Ailos;

use App\Support\Erp\License\LicencaHttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Conector Pix Ailos (OAuth2 + mTLS/PKCS#12) adaptado do kit Portal/Replit.
 */
final class AilosPixService
{
    public function __construct(private readonly AilosPixConfig $config)
    {
    }

    /**
     * @param  array{
     *   amount: int|float|string,
     *   description: string,
     *   reference?: string|int,
     *   txid?: string,
     *   debtorName?: string,
     *   debtorDocument?: string|null
     * }  $input
     */
    public function createCharge(array $input): AilosPixResult
    {
        $amount = number_format((float) ($input['amount'] ?? 0), 2, '.', '');
        if ((float) $amount < 0.01) {
            throw new AilosPixException('O valor da cobrança deve ser maior ou igual a R$ 0,01.');
        }

        $description = trim((string) ($input['description'] ?? 'Cobrança Pix'));
        if ($description === '') {
            throw new AilosPixException('Informe a descrição da cobrança.');
        }

        $txid = isset($input['txid']) && trim((string) $input['txid']) !== ''
            ? trim((string) $input['txid'])
            : self::buildTxid(
                (string) ($input['reference'] ?? bin2hex(random_bytes(8))),
                $amount,
                $description,
                (string) ($input['debtorDocument'] ?? ''),
            );

        $existing = $this->findExistingCharge($txid);
        if ($existing !== null) {
            return $this->getQr($txid, $this->expirationDateFromCharge($existing));
        }

        $document = preg_replace('/\D+/', '', (string) ($input['debtorDocument'] ?? '')) ?: '';
        $debtor = match (strlen($document)) {
            11 => ['nome' => mb_substr((string) ($input['debtorName'] ?? 'Cliente'), 0, 200), 'cpf' => $document],
            14 => ['nome' => mb_substr((string) ($input['debtorName'] ?? 'Cliente'), 0, 200), 'cnpj' => $document],
            default => null,
        };

        $body = [
            'calendario' => ['expiracao' => $this->config->expirationSeconds],
            'valor' => ['original' => $amount],
            'chave' => $this->config->pixKey,
            'solicitacaoPagador' => mb_substr($description, 0, 140),
        ];
        if ($debtor !== null) {
            $body['devedor'] = $debtor;
        }

        try {
            $this->request('PUT', "cob/{$txid}", $body);
        } catch (AilosPixException $exception) {
            if ($exception->status !== 409) {
                throw $exception;
            }
        }

        return $this->getQr(
            $txid,
            now()->addSeconds($this->config->expirationSeconds)->toIso8601String()
        );
    }

    public function getQr(string $txid, ?string $expirationDate = null): AilosPixResult
    {
        $response = $this->request('GET', 'qrcode/consulta/'.rawurlencode($txid));
        $rawBrCode = $response['copiaCola']
            ?? $response['copiaECola']
            ?? $response['copiaecola']
            ?? $response['pixCopiaECola']
            ?? null;
        $brCode = AilosBrCode::normalize($rawBrCode);

        return new AilosPixResult(
            txid: $txid,
            brCode: $brCode,
            qrCodeBase64: AilosPixQrPng::toBase64($brCode),
            expirationDate: $expirationDate
                ?? now()->addSeconds($this->config->expirationSeconds)->toIso8601String(),
        );
    }

    public function getPaymentStatus(string $txid): string
    {
        $response = $this->request('GET', 'cob/'.rawurlencode($txid));
        $status = strtoupper((string) ($response['status'] ?? ''));

        return match ($status) {
            'CONCLUIDA' => 'approved',
            'ATIVA', 'CRIADA' => 'pending',
            'REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP' => 'cancelled',
            default => 'unknown',
        };
    }

    public function configureWebhook(string $webhookUrl): void
    {
        if (! filter_var($webhookUrl, FILTER_VALIDATE_URL) || ! str_starts_with(strtolower($webhookUrl), 'https://')) {
            throw new AilosPixException('Informe uma URL HTTPS válida para o webhook.');
        }

        $this->request('PUT', 'webhook/'.rawurlencode($this->config->pixKey), [
            'webHookUrl' => $webhookUrl,
        ]);
    }

    public function expirationSeconds(): int
    {
        return $this->config->expirationSeconds;
    }

    public static function buildTxid(
        string $reference,
        string $amount,
        string $description,
        string $debtorDocument = '',
    ): string {
        $normalizedReference = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $reference) ?: 'REF');
        $normalizedReference = substr($normalizedReference, 0, 8);
        $fingerprint = strtoupper(substr(hash('sha256', implode("\0", [
            $normalizedReference,
            $amount,
            $description,
            preg_replace('/\D+/', '', $debtorDocument) ?: '',
        ])), 0, 20));

        return 'AILOS'.str_pad($normalizedReference, 8, '0', STR_PAD_LEFT).$fingerprint;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findExistingCharge(string $txid): ?array
    {
        try {
            return $this->request('GET', 'cob/'.rawurlencode($txid));
        } catch (AilosPixException $exception) {
            if ($exception->status === 404) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->send($method, $this->config->baseUrl.'/'.ltrim($path, '/'), $body === null ? [] : ['json' => $body]);

        return $this->parseResponse($response);
    }

    private function accessToken(): string
    {
        $cacheKey = 'ailos.pix.token.'.hash('sha256', implode('|', [
            $this->config->environment,
            $this->config->clientId,
            $this->config->baseUrl,
        ]));
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['expires_at'] ?? 0) > now()->timestamp + 30) {
            return (string) $cached['access_token'];
        }

        $response = $this->http()
            ->asForm()
            ->post($this->config->baseUrl.'/client/connect/token', [
                'Client_Id' => $this->config->clientId,
                'Client_Secret' => $this->config->clientSecret,
                'scope' => $this->config->tokenScopes,
            ]);
        $payload = $this->parseResponse($response);
        $token = (string) ($payload['access_token'] ?? '');
        if ($token === '') {
            throw new AilosPixException('Ailos não retornou o token de acesso.');
        }

        $expiresIn = max(60, (int) ($payload['expires_in'] ?? 300));
        Cache::put($cacheKey, [
            'access_token' => $token,
            'expires_at' => now()->timestamp + $expiresIn,
        ], now()->addSeconds($expiresIn));

        return $token;
    }

    private function http(): PendingRequest
    {
        $pfxPath = $this->pfxPath();

        return Http::acceptJson()
            ->timeout($this->config->timeout)
            ->withOptions(LicencaHttpClient::options([
                'curl' => [
                    CURLOPT_SSLCERT => $pfxPath,
                    CURLOPT_SSLCERTTYPE => 'P12',
                    CURLOPT_SSLCERTPASSWD => $this->config->pfxPassword,
                ],
            ]));
    }

    private function pfxPath(): string
    {
        if ($this->config->pfxPath !== null) {
            if (! is_file($this->config->pfxPath)) {
                throw new AilosPixException('Arquivo PFX da Ailos não encontrado.');
            }

            return $this->config->pfxPath;
        }

        $base64 = preg_replace('/\s+/', '', (string) $this->config->pfxBase64) ?? '';
        $binary = base64_decode($base64, true);
        if ($binary === false || strlen($binary) < 100) {
            throw new AilosPixException('O certificado PFX em Base64 é inválido.');
        }

        $directory = storage_path('app/private/ailos');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new AilosPixException('Não foi possível preparar o diretório privado do certificado Ailos.');
        }

        $path = $directory.'/'.hash('sha256', $base64).'.p12';
        if (! is_file($path) && file_put_contents($path, $binary, LOCK_EX) === false) {
            throw new AilosPixException('Não foi possível armazenar o certificado Ailos com segurança.');
        }
        @chmod($path, 0600);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(Response $response): array
    {
        $payload = $response->json();
        if (! $response->successful()) {
            $payload = is_array($payload) ? $payload : [];
            $summary = $payload['detail'] ?? $payload['message'] ?? $payload['title'] ?? ('HTTP '.$response->status());
            $violations = [];
            foreach (($payload['violacoes'] ?? []) as $violation) {
                if (is_array($violation) && isset($violation['razao'])) {
                    $violations[] = (string) $violation['razao'];
                }
            }

            throw new AilosPixException(
                'Ailos: '.$this->sanitizeError(implode(' ', array_merge([(string) $summary], $violations))),
                $response->status(),
                $payload,
            );
        }

        if ($payload === null && $response->successful()) {
            return [];
        }

        if (! is_array($payload)) {
            throw new AilosPixException('Ailos retornou uma resposta inválida.', $response->status());
        }

        return $payload;
    }

    private function sanitizeError(string $message): string
    {
        foreach ([
            $this->config->clientId,
            $this->config->clientSecret,
            $this->config->pixKey,
            $this->config->pfxPassword,
        ] as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[DADO SENSÍVEL OCULTADO]', $message);
            }
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    private function expirationDateFromCharge(array $charge): ?string
    {
        $seconds = $charge['calendario']['expiracao'] ?? null;
        $created = $charge['calendario']['criacao'] ?? null;
        if (is_numeric($seconds) && is_string($created)) {
            try {
                return \Carbon\Carbon::parse($created)->addSeconds((int) $seconds)->toIso8601String();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
