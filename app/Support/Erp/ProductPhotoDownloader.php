<?php

namespace App\Support\Erp;

use App\Support\Erp\License\LicencaHttpClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ProductPhotoDownloader
{

    /**
     * @return array{path: ?string, message: ?string}
     */
    public function download(string $url): array
    {
        $url = trim($url);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'path' => null,
                'message' => 'URL da foto inválida.',
            ];
        }

        $lastStatus = null;

        foreach ($this->requestHeaderSets() as $headers) {
            try {
                $response = Http::withOptions(LicencaHttpClient::options())
                    ->timeout(25)
                    ->withHeaders($headers)
                    ->get($url);
            } catch (\Throwable $exception) {
                report($exception);

                continue;
            }

            $lastStatus = $response->status();

            if (! $response->successful()) {
                continue;
            }

            $stored = $this->storeResponseBody($response, $url);

            if ($stored !== null) {
                return [
                    'path' => $stored,
                    'message' => null,
                ];
            }
        }

        if ($lastStatus !== null) {
            report(new RuntimeException(sprintf(
                'Download da foto do produto falhou (HTTP %s): %s',
                $lastStatus,
                $url,
            )));

            return [
                'path' => null,
                'message' => 'Não foi possível baixar a imagem (HTTP ' . $lastStatus . ').',
            ];
        }

        return [
            'path' => null,
            'message' => 'Erro ao baixar a imagem. Verifique a conexão do servidor.',
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function requestHeaderSets(): array
    {
        return [[
            'User-Agent' => 'Mozilla/5.0 (compatible; UnitecERP/1.0)',
            'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        ]];
    }

    private function storeResponseBody(Response $response, string $sourceUrl): ?string
    {
        $body = $response->body();

        if ($body === '' || strlen($body) < 128) {
            report(new RuntimeException('Download da foto do produto retornou conteúdo vazio ou inválido: ' . $sourceUrl));

            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $extension = $this->resolveExtension($contentType, $body);
        $filename = 'products-photos/' . Str::uuid() . '.' . $extension;

        if (Storage::disk('public')->put($filename, $body) !== true) {
            report(new RuntimeException('Não foi possível gravar a foto do produto em storage: ' . $filename));

            return null;
        }

        if (! Storage::disk('public')->exists($filename)) {
            report(new RuntimeException('Arquivo de foto não encontrado após gravação: ' . $filename));

            return null;
        }

        return $filename;
    }

    private function resolveExtension(string $contentType, string $body): string
    {
        if (str_contains($contentType, 'png')) {
            return 'png';
        }

        if (str_contains($contentType, 'webp')) {
            return 'webp';
        }

        if (str_contains($contentType, 'gif')) {
            return 'gif';
        }

        if (str_starts_with($body, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }

        if (str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a')) {
            return 'gif';
        }

        if (strlen($body) >= 12 && str_starts_with($body, 'RIFF') && substr($body, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return 'jpg';
    }
}
