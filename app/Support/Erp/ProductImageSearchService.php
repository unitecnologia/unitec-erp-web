<?php

namespace App\Support\Erp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProductImageSearchService
{
    private const ENDPOINT = 'https://world.openfoodfacts.org/cgi/search.pl';

    private const MAX_RESULTS = 8;

    private const FETCH_SIZE = 20;

    private const TIMEOUT_SECONDS = 12;

    /**
     * @return array{results: list<array{thumbnail: string, image_url: string, title: string}>, message: ?string}
     */
    public function search(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return $this->empty('Informe a descrição para pesquisar.');
        }

        $started = microtime(true);

        try {
            $response = Http::connectTimeout(5)
                ->timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'UnitecERP/1.0 (product image search)',
                ])
                ->acceptJson()
                ->get(self::ENDPOINT, [
                    'search_terms' => $this->searchTerms($query),
                    'search_simple' => 1,
                    'action' => 'process',
                    'json' => 1,
                    'page_size' => self::FETCH_SIZE,
                    'fields' => 'product_name,image_front_small_url,image_front_url,image_url',
                ]);
        } catch (ConnectionException $exception) {
            $this->logConnectionFailure($query, $exception, $started);

            return $this->empty('A pesquisa de imagens demorou demais ou a internet falhou. Tente novamente.');
        } catch (Throwable) {
            return $this->empty('Não foi possível pesquisar imagens agora.');
        }

        if ($response->status() === 429) {
            return $this->empty('A pesquisa de imagens está temporariamente limitada. Tente novamente em instantes.');
        }

        if (! $response->successful()) {
            return $this->empty('Não foi possível pesquisar imagens agora.');
        }

        $results = $this->normalize($response->json('products'));

        if ($results === []) {
            return $this->empty('Nenhuma imagem de produto encontrada. Você pode alterar o texto da pesquisa e tentar novamente.');
        }

        return [
            'results' => $results,
            'message' => null,
        ];
    }

    private function logConnectionFailure(string $query, ConnectionException $exception, float $started): void
    {
        $curl = $this->curlError($exception);

        Log::warning('Pesquisa de imagem Open Food Facts falhou na conexão.', [
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

    private function searchTerms(string $query): string
    {
        $text = mb_strtoupper(trim($query), 'UTF-8');
        $text = preg_replace('/\bC\/\d+\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b(CX|PCT|LT)\b/u', ' ', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($text === '') {
            return mb_substr(trim($query), 0, 200);
        }

        return mb_substr($text, 0, 200);
    }

    /**
     * @return list<array{thumbnail: string, image_url: string, title: string}>
     */
    private function normalize(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $results = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $thumbnail = $row['image_front_small_url'] ?? null;
            $imageUrl = $row['image_url'] ?? $row['image_front_url'] ?? null;

            if (! $this->isHttpUrl($thumbnail) || ! $this->isHttpUrl($imageUrl)) {
                continue;
            }

            $title = trim(strip_tags((string) ($row['product_name'] ?? '')));

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
}
