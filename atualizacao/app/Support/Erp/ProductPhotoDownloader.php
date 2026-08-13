<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ProductPhotoDownloader
{
    private const COSMOS_USER_AGENT = 'Cosmos-API-Request';

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

        foreach ($this->requestHeaderSets($url) as $headers) {
            try {
                $response = Http::timeout(25)
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
    private function requestHeaderSets(string $url): array
    {
        $sets = [];

        if ($this->isCosmosHost($url)) {
            $token = $this->resolveCosmosToken();

            if ($token !== null) {
                $sets[] = [
                    'X-Cosmos-Token' => $token,
                    'User-Agent' => self::COSMOS_USER_AGENT,
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    'Referer' => 'https://cosmos.bluesoft.com.br/',
                    'Origin' => 'https://cosmos.bluesoft.com.br',
                ];
            }

            $sets[] = [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Referer' => 'https://cosmos.bluesoft.com.br/',
                'Origin' => 'https://cosmos.bluesoft.com.br',
            ];
        }

        $sets[] = [
            'User-Agent' => 'Mozilla/5.0 (compatible; UnitecERP/1.0)',
            'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        ];

        return $sets;
    }

    private function isCosmosHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_contains($host, 'bluesoft.com.br');
    }

    private function resolveCosmosToken(): ?string
    {
        $empresaId = session('erp_empresa_id', auth()->user()?->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;
        $token = trim((string) ($empresa?->param_api_servicos_token ?? ''));

        return $token !== '' ? $token : null;
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
