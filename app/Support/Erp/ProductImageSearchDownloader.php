<?php

namespace App\Support\Erp;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Download isolado da futura pesquisa de imagem.
 * Não altera a foto atual: só cria um arquivo novo depois da validação.
 */
final class ProductImageSearchDownloader
{
    public const MAX_BYTES = 2_097_152;

    public const MAX_SIDE_PX = 2000;

    public const MAX_PIXELS = 3_000_000;

    public const CONNECT_TIMEOUT_SECONDS = 5;

    public const TIMEOUT_SECONDS = 12;

    private const MAX_REDIRECTS = 3;

    /** @var array<string, int> */
    private const IMAGE_TYPES = [
        'jpg' => IMAGETYPE_JPEG,
        'png' => IMAGETYPE_PNG,
        'gif' => IMAGETYPE_GIF,
        'webp' => IMAGETYPE_WEBP,
    ];

    /**
     * @param  null|(callable(string): list<string>)  $resolveHost
     */
    public function __construct(private readonly mixed $resolveHost = null) {}

    /**
     * @return array{path: ?string, message: ?string}
     */
    public function download(string $url): array
    {
        return $this->fetch(trim($url), 0);
    }

    /**
     * @return array{path: ?string, message: ?string}
     */
    private function fetch(string $url, int $hop): array
    {
        if ($hop > self::MAX_REDIRECTS) {
            return $this->reject('Não foi possível baixar a imagem.');
        }

        $target = $this->inspectUrl($url);

        if ($target['ok'] !== true) {
            return $this->reject($target['message']);
        }

        $temp = tempnam(sys_get_temp_dir(), 'erpimg');

        if ($temp === false) {
            return $this->reject('Não foi possível baixar a imagem.');
        }

        $state = [
            'bytes' => 0,
            'tooLarge' => false,
            'streamed' => false,
        ];

        try {
            $response = $this->transfer($target, $temp, $state);

            if ($state['tooLarge']) {
                return $this->reject('Imagem acima de 2 MB.');
            }

            if ($this->isRedirect($response)) {
                $next = $this->resolveLocation($url, (string) $response->header('Location'));

                if ($next === null) {
                    return $this->reject('Não foi possível baixar a imagem.');
                }

                return $this->fetch($next, $hop + 1);
            }

            if (! $response->successful()) {
                return $this->reject('Não foi possível baixar a imagem.');
            }

            if (! $state['streamed']) {
                $this->copyBody($response, $temp, $state);
            }

            if ($state['tooLarge']) {
                return $this->reject('Imagem acima de 2 MB.');
            }

            $stored = $this->storeIfValid($temp);

            if ($stored['path'] === null) {
                return $this->reject($stored['message'] ?? 'A resposta não é uma imagem válida.');
            }

            return [
                'path' => $stored['path'],
                'message' => null,
            ];
        } catch (Throwable) {
            if ($state['tooLarge']) {
                return $this->reject('Imagem acima de 2 MB.');
            }

            return $this->reject('Não foi possível baixar a imagem.');
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    /**
     * @return array{ok: true, url: string, host: string, port: int, pin: ?string}|array{ok: false, message: string}
     */
    private function inspectUrl(string $url): array
    {
        if ($url === '' || preg_match('/[\s\\\\]/', $url) === 1) {
            return $this->invalidUrl();
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return $this->invalidUrl();
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return $this->invalidUrl();
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return $this->invalidUrl();
        }

        $host = rawurldecode((string) ($parts['host'] ?? ''));
        $host = rtrim(strtolower($host), '.');

        if ($host === '' || strlen($host) > 253 || str_contains($host, '%')) {
            return $this->invalidUrl();
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if ($port < 1 || $port > 65535) {
            return $this->invalidUrl();
        }

        $literal = $this->literalAddress($host);

        if ($literal !== null) {
            if (! $this->isPublicAddress($literal)) {
                return $this->blocked();
            }

            return [
                'ok' => true,
                'url' => $url,
                'host' => $host,
                'port' => $port,
                'pin' => null,
            ];
        }

        if ($this->isObfuscatedAddress($host) || $this->isBlockedHostname($host)) {
            return $this->blocked();
        }

        try {
            $ips = $this->lookupHost($host);
        } catch (Throwable) {
            return [
                'ok' => false,
                'message' => 'Não foi possível confirmar o destino da imagem.',
            ];
        }

        if ($ips === []) {
            return [
                'ok' => false,
                'message' => 'Não foi possível confirmar o destino da imagem.',
            ];
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicAddress($ip)) {
                return $this->blocked();
            }
        }

        return [
            'ok' => true,
            'url' => $url,
            'host' => (string) ($parts['host'] ?? $host),
            'port' => $port,
            'pin' => $ips[0],
        ];
    }

    /**
     * @param  array{ok: true, url: string, host: string, port: int, pin: ?string}  $target
     * @param  array{bytes: int, tooLarge: bool, streamed: bool}  $state
     */
    private function transfer(array $target, string $temp, array &$state): Response
    {
        $out = fopen($temp, 'wb');

        if ($out === false) {
            throw new \RuntimeException('Não foi possível criar o arquivo temporário.');
        }

        $write = function ($ch, $data) use (&$state, $out): int {
            $state['streamed'] = true;
            $size = strlen($data);

            if ($size === 0) {
                return 0;
            }

            if (! $this->acceptChunk($data, $state)) {
                return 0;
            }

            $written = fwrite($out, $data);

            if ($written !== $size) {
                return 0;
            }

            return $size;
        };

        $curl = [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_WRITEFUNCTION => $write,
        ];

        if (is_string($target['pin']) && $target['pin'] !== '') {
            $curl[CURLOPT_RESOLVE] = [
                $this->resolveLine($target['host'], $target['port'], $target['pin']),
            ];
        }

        try {
            return Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->withOptions([
                    'allow_redirects' => false,
                    'http_errors' => false,
                    'on_headers' => function ($response) use (&$state): void {
                        $length = $this->contentLength($response);

                        if ($length !== null && $length > self::MAX_BYTES) {
                            $state['tooLarge'] = true;

                            throw new \RuntimeException('imagem acima de 2 MB');
                        }
                    },
                    'curl' => $curl,
                ])
                ->withHeaders([
                    'Accept' => 'image/jpeg,image/png,image/webp,image/gif',
                    'User-Agent' => 'UnitecERP-ImageSearch/1.0',
                ])
                ->get($target['url']);
        } finally {
            fclose($out);
        }
    }

    /**
     * @param  array{bytes: int, tooLarge: bool, streamed: bool}  $state
     */
    private function copyBody(Response $response, string $temp, array &$state): void
    {
        $length = $this->contentLength($response);

        if ($length !== null && $length > self::MAX_BYTES) {
            $state['tooLarge'] = true;

            return;
        }

        $body = $response->toPsrResponse()->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $out = fopen($temp, 'wb');

        if ($out === false) {
            throw new \RuntimeException('Não foi possível criar o arquivo temporário.');
        }

        try {
            while (! $body->eof()) {
                $chunk = $body->read(8192);

                if ($chunk === '') {
                    break;
                }

                if (! $this->acceptChunk($chunk, $state)) {
                    return;
                }

                if (fwrite($out, $chunk) !== strlen($chunk)) {
                    throw new \RuntimeException('Não foi possível gravar o arquivo temporário.');
                }
            }
        } finally {
            fclose($out);
        }
    }

    /**
     * @param  array{bytes: int, tooLarge: bool, streamed: bool}  $state
     */
    private function acceptChunk(string $chunk, array &$state): bool
    {
        $size = strlen($chunk);

        if ($size > self::MAX_BYTES || $state['bytes'] > self::MAX_BYTES - $size) {
            $state['tooLarge'] = true;

            return false;
        }

        $state['bytes'] += $size;

        return true;
    }

    /**
     * @return array{path: ?string, message: ?string}
     */
    private function storeIfValid(string $temp): array
    {
        $size = filesize($temp);

        if ($size === false || $size < 1) {
            return $this->reject('A resposta não é uma imagem válida.');
        }

        if ($size > self::MAX_BYTES) {
            return $this->reject('Imagem acima de 2 MB.');
        }

        $header = $this->readHeader($temp);
        $format = $this->detectFormat($header);

        if ($format === null) {
            return $this->reject('A resposta não é uma imagem válida.');
        }

        $info = @getimagesize($temp);

        if ($info === false) {
            return $this->reject('A resposta não é uma imagem válida.');
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        $type = (int) ($info[2] ?? 0);

        if ($width < 1 || $height < 1 || $type !== self::IMAGE_TYPES[$format]) {
            return $this->reject('A resposta não é uma imagem válida.');
        }

        if ($width > self::MAX_SIDE_PX || $height > self::MAX_SIDE_PX) {
            return $this->reject('Imagem acima de 2000 px.');
        }

        if (($width * $height) > self::MAX_PIXELS) {
            return $this->reject('Imagem acima de 3 megapixels.');
        }

        $relative = 'products-photos/' . Str::uuid()->toString() . '.' . $format;
        $in = fopen($temp, 'rb');

        if ($in === false) {
            return $this->reject('Não foi possível gravar a imagem.');
        }

        try {
            $written = Storage::disk('public')->writeStream($relative, $in);
        } catch (Throwable) {
            Storage::disk('public')->delete($relative);

            return $this->reject('Não foi possível gravar a imagem.');
        } finally {
            if (is_resource($in)) {
                fclose($in);
            }
        }

        if ($written !== true || ! Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);

            return $this->reject('Não foi possível gravar a imagem.');
        }

        return [
            'path' => $relative,
            'message' => null,
        ];
    }

    private function readHeader(string $temp): string
    {
        $handle = fopen($temp, 'rb');

        if ($handle === false) {
            return '';
        }

        $header = (string) fread($handle, 16);
        fclose($handle);

        return $header;
    }

    private function detectFormat(string $header): ?string
    {
        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return 'jpg';
        }

        if (str_starts_with($header, "\x89PNG\x0D\x0A\x1A\x0A")) {
            return 'png';
        }

        if (str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a')) {
            return 'gif';
        }

        if (strlen($header) >= 12 && str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return null;
    }

    private function contentLength(object $response): ?int
    {
        $raw = $response instanceof Response
            ? (string) $response->header('Content-Length')
            : (string) $response->getHeaderLine('Content-Length');

        $raw = trim(explode(',', $raw)[0] ?? '');

        if ($raw === '' || preg_match('/^\d+$/', $raw) !== 1) {
            return null;
        }

        return (int) $raw;
    }

    private function isRedirect(Response $response): bool
    {
        return in_array($response->status(), [301, 302, 303, 307, 308], true);
    }

    private function resolveLocation(string $current, string $location): ?string
    {
        $location = trim($location);

        if ($location === '' || preg_match('/[\r\n\\\\]/', $location) === 1) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($current);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        if (str_starts_with($location, '//')) {
            return $parts['scheme'] . ':' . $location;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $path = (string) ($parts['path'] ?? '/');
        $slash = strrpos($path, '/');
        $dir = $slash === false ? '' : substr($path, 0, $slash);

        return $origin . $dir . '/' . $location;
    }

    /**
     * @return list<string>
     */
    private function lookupHost(string $host): array
    {
        $ips = $this->resolveHost !== null
            ? ($this->resolveHost)($host)
            : $this->dnsLookup($host);

        if (! is_array($ips)) {
            return [];
        }

        $clean = [];

        foreach ($ips as $ip) {
            if (is_string($ip) && $ip !== '') {
                $clean[] = $ip;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @return list<string>
     */
    private function dnsLookup(string $host): array
    {
        $ips = [];
        $v4 = @gethostbynamel($host);

        if (is_array($v4)) {
            $ips = $v4;
        }

        if (defined('DNS_AAAA')) {
            $records = @dns_get_record($host, DNS_AAAA);

            if (is_array($records)) {
                foreach ($records as $record) {
                    if (is_array($record) && isset($record['ipv6']) && is_string($record['ipv6']) && $record['ipv6'] !== '') {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        return $ips;
    }

    private function literalAddress(string $host): ?string
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        return null;
    }

    private function isObfuscatedAddress(string $host): bool
    {
        if (preg_match('/^(0x[0-9a-f]+|\d+)$/i', $host) === 1) {
            return true;
        }

        if (preg_match('/^[0-9.]+$/', $host) === 1) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false;
        }

        return false;
    }

    private function isBlockedHostname(string $host): bool
    {
        if (in_array($host, [
            'localhost',
            'localhost.localdomain',
            'ip6-localhost',
            'ip6-loopback',
            'metadata',
            'metadata.google.internal',
        ], true)) {
            return true;
        }

        return str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal');
    }

    private function isPublicAddress(string $ip): bool
    {
        $ip = strtolower(trim($ip, '[]'));

        if (str_starts_with($ip, '::ffff:')) {
            $mapped = substr($ip, 7);

            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ip = $mapped;
            }
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private function resolveLine(string $host, int $port, string $ip): string
    {
        $address = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;

        return $host . ':' . $port . ':' . $address;
    }

    /**
     * @return array{ok: false, message: string}
     */
    private function invalidUrl(): array
    {
        return [
            'ok' => false,
            'message' => 'URL inválida.',
        ];
    }

    /**
     * @return array{ok: false, message: string}
     */
    private function blocked(): array
    {
        return [
            'ok' => false,
            'message' => 'Endereço de imagem não permitido.',
        ];
    }

    /**
     * @return array{path: null, message: string}
     */
    private function reject(string $message): array
    {
        return [
            'path' => null,
            'message' => $message,
        ];
    }
}
