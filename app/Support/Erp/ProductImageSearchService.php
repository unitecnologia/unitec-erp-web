<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use App\Support\Erp\License\LicencaHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProductImageSearchService
{
    private const MAX_RESULTS = 10;

    private const DEFAULT_TIMEOUT_SECONDS = 30;

    /**
     * @return array{results: list<array{thumbnail: string, image_url: string, title: string}>, message: ?string}
     */
    public function search(string $query, ?Empresa $empresa = null): array
    {
        $query = trim($query);

        if ($query === '') {
            return $this->empty('Informe a descrição para pesquisar.');
        }

        $config = $this->config();

        if ($config['error'] !== null) {
            return $this->empty($config['error']);
        }

        $primary = $this->normalizeQuery($query);

        if ($primary === '') {
            return $this->empty('Informe a descrição para pesquisar.');
        }

        $first = $this->request($config, $primary);

        if ($first['error'] !== null) {
            return $this->empty($first['error']);
        }

        if ($first['results'] !== []) {
            return [
                'results' => $first['results'],
                'message' => null,
            ];
        }

        $fallback = $this->simplifyQuery($primary);

        if ($fallback === '' || $fallback === $primary) {
            return $this->empty('Nenhuma imagem de produto encontrada. Você pode alterar o texto da pesquisa e tentar novamente.');
        }

        $second = $this->request($config, $fallback);

        if ($second['error'] !== null) {
            return $this->empty($second['error']);
        }

        if ($second['results'] === []) {
            return $this->empty('Nenhuma imagem de produto encontrada. Você pode alterar o texto da pesquisa e tentar novamente.');
        }

        return [
            'results' => $second['results'],
            'message' => null,
        ];
    }

    /**
     * @return array{url: string, key: string, timeout: int, error: ?string}
     */
    private function config(): array
    {
        $url = trim((string) config('unitec.imagens_serper.url', ''));
        $key = trim((string) config('unitec.imagens_serper.key', ''));
        $timeout = (int) config('unitec.imagens_serper.timeout', self::DEFAULT_TIMEOUT_SECONDS);

        if ($url === '' || ! $this->isHttpUrl($url)) {
            return [
                'url' => '',
                'key' => '',
                'timeout' => self::DEFAULT_TIMEOUT_SECONDS,
                'error' => 'A pesquisa de imagens Serper não está configurada no sistema.',
            ];
        }

        if ($key === '') {
            return [
                'url' => $url,
                'key' => '',
                'timeout' => self::DEFAULT_TIMEOUT_SECONDS,
                'error' => 'A API Key Serper não está configurada no sistema.',
            ];
        }

        if ($timeout < 1 || $timeout > 300) {
            $timeout = self::DEFAULT_TIMEOUT_SECONDS;
        }

        return [
            'url' => $url,
            'key' => $key,
            'timeout' => $timeout,
            'error' => null,
        ];
    }

    /**
     * @param  array{url: string, key: string, timeout: int, error: ?string}  $config
     * @return array{results: list<array{thumbnail: string, image_url: string, title: string}>, error: ?string}
     */
    private function request(array $config, string $query): array
    {
        $started = microtime(true);

        try {
            $response = Http::withOptions(LicencaHttpClient::options())
                ->connectTimeout(min(5, $config['timeout']))
                ->timeout($config['timeout'])
                ->withHeaders([
                    'X-API-KEY' => $config['key'],
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->post($config['url'], [
                    'q' => $query,
                    'gl' => 'br',
                    'hl' => 'pt-br',
                    'num' => self::MAX_RESULTS,
                ]);
        } catch (ConnectionException $exception) {
            $this->logConnectionFailure($query, $exception, $started);

            return $this->failed('A pesquisa de imagens demorou demais ou a internet falhou. Tente novamente.');
        } catch (Throwable) {
            return $this->failed('Não foi possível pesquisar imagens agora.');
        }

        return $this->interpret($response);
    }

    /**
     * @return array{results: list<array{thumbnail: string, image_url: string, title: string}>, error: ?string}
     */
    private function interpret(Response $response): array
    {
        $status = $response->status();

        if ($status === 401 || $status === 403) {
            return $this->failed('A API Key Serper foi recusada. Entre em contato com o suporte Unitec.');
        }

        if ($status === 429) {
            return $this->failed('A pesquisa de imagens está temporariamente limitada. Tente novamente em instantes.');
        }

        if (! $response->successful()) {
            return $this->failed('Não foi possível pesquisar imagens agora.');
        }

        $payload = $response->json();

        if (! is_array($payload) || ! array_key_exists('images', $payload) || ! is_array($payload['images'])) {
            return $this->failed('A pesquisa de imagens retornou uma resposta inválida.');
        }

        return [
            'results' => $this->normalizeImages($payload['images']),
            'error' => null,
        ];
    }

    private function normalizeQuery(string $query): string
    {
        $text = mb_strtoupper(trim($query), 'UTF-8');
        $text = preg_replace('/\bC\s*\/\s*GAS\b/u', 'COM GAS', $text) ?? $text;
        $text = preg_replace('/\bS\s*\/\s*GAS\b/u', 'SEM GAS', $text) ?? $text;
        $text = preg_replace(
            '/\b\d+\s*X\s*(\d+(?:[.,]\d+)?)\s*(ML|KG|MG|G|L)\b/u',
            '$1$2',
            $text,
        ) ?? $text;

        return $this->collapse($text);
    }

    private function simplifyQuery(string $normalized): string
    {
        $text = preg_replace('/\b(?:LATA|PET|CX|PCT|FARDO)\b/u', ' ', $normalized) ?? $normalized;

        return $this->collapse($text);
    }

    private function collapse(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return mb_substr($text, 0, 200);
    }

    /**
     * @return list<array{thumbnail: string, image_url: string, title: string}>
     */
    private function normalizeImages(array $rows): array
    {
        $results = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $thumbnail = $row['thumbnailUrl'] ?? null;
            $imageUrl = $row['imageUrl'] ?? null;

            if (! $this->isHttpUrl($thumbnail) || ! $this->isHttpUrl($imageUrl)) {
                continue;
            }

            $title = trim(strip_tags((string) ($row['title'] ?? '')));

            $results[] = [
                'thumbnail' => $thumbnail,
                'image_url' => $imageUrl,
                'title' => mb_substr($title, 0, 120),
            ];

            if (count($results) >= self::MAX_RESULTS) {
                break;
            }
        }

        return $results;
    }

    private function isHttpUrl(mixed $url): bool
    {
        if (! is_string($url) || $url === '' || strlen($url) > 2000) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function logConnectionFailure(string $query, ConnectionException $exception, float $started): void
    {
        $curl = $this->curlError($exception);

        Log::warning('Pesquisa de imagem Serper falhou na conexão.', [
            'termo' => mb_substr($query, 0, 200),
            'tempo_ms' => (int) round((microtime(true) - $started) * 1000),
            'exception' => $exception->getMessage(),
            'curl_errno' => $curl['errno'],
            'curl_error' => $curl['error'],
        ]);
    }

    /**
     * @return array{errno: ?int, error: ?string}
     */
    private function curlError(Throwable $exception): array
    {
        $current = $exception;

        while ($current !== null) {
            if (method_exists($current, 'getHandlerContext')) {
                $context = $current->getHandlerContext();

                if (is_array($context) && (isset($context['errno']) || isset($context['error']))) {
                    return [
                        'errno' => isset($context['errno']) ? (int) $context['errno'] : null,
                        'error' => isset($context['error']) ? (string) $context['error'] : null,
                    ];
                }
            }

            $current = $current->getPrevious();
        }

        if (preg_match('/cURL error (\d+):\s*(.*)/i', $exception->getMessage(), $matches) === 1) {
            return [
                'errno' => (int) $matches[1],
                'error' => trim($matches[2]) !== '' ? trim($matches[2]) : null,
            ];
        }

        return [
            'errno' => null,
            'error' => null,
        ];
    }

    /**
     * @return array{results: list<array{thumbnail: string, image_url: string, title: string}>, message: string}
     */
    private function empty(string $message): array
    {
        return [
            'results' => [],
            'message' => $message,
        ];
    }

    /**
     * @return array{results: list<array{thumbnail: string, image_url: string, title: string}>, error: string}
     */
    private function failed(string $message): array
    {
        return [
            'results' => [],
            'error' => $message,
        ];
    }
}
