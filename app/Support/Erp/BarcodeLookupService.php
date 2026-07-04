<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BarcodeLookupService
{
    private const CACHE_TTL_DAYS = 30;

    private const DAILY_API_LIMIT = 10;

    private const COSMOS_DAILY_LIMIT = 25;

    private const HTTP_USER_AGENT = 'UnitecERP/1.0 (barcode-lookup)';

    private const COSMOS_USER_AGENT = 'Cosmos-API-Request';

    private const COSMOS_GTIN_URL = 'https://api.cosmos.bluesoft.com.br/gtins/%s.json';

    /**
     * @return array<string, mixed>
     */
    public function fetch(string $barcode, ?int $excludeProductId = null): array
    {
        $barcode = preg_replace('/\D/', '', $barcode);

        if (strlen($barcode) < 8 || strlen($barcode) > 14) {
            throw new RuntimeException('Informe um código de barras válido (8 a 14 dígitos).');
        }

        $internal = $this->fetchFromInternal($barcode, $excludeProductId);

        if ($internal !== null) {
            return $internal;
        }

        return Cache::remember(
            "erp.barcode.lookup.{$barcode}",
            now()->addDays(self::CACHE_TTL_DAYS),
            fn (): array => $this->fetchFromExternal($barcode),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchFromInternal(string $barcode, ?int $excludeProductId): ?array
    {
        $product = Product::query()
            ->where('codigo_barras', $barcode)
            ->when($excludeProductId, fn ($query) => $query->where('id', '!=', $excludeProductId))
            ->orderByDesc('updated_at')
            ->first();

        if (! $product) {
            return null;
        }

        return [
            'source' => 'internal',
            'existing_product_id' => $product->getKey(),
            'descricao' => $product->descricao,
            'marca' => $product->marca,
            'grupo' => $product->grupo,
            'unidade' => $product->unidade,
            'referencia' => $product->referencia,
            'ncm' => $product->ncm,
            'ncm_descricao' => $product->ncm_descricao,
            'cest' => $product->cest,
            'peso_kg' => $product->peso_kg,
            'foto_url' => $product->fotoUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchFromExternal(string $barcode): array
    {
        $networkError = false;

        if ($this->resolveCosmosToken() === null && $this->isBrazilianBarcode($barcode)) {
            throw new RuntimeException('Configure o token Cosmos (Bluesoft) em Configurações » Empresa » API de Serviços.');
        }

        $result = $this->tryFetchFromCosmos($barcode, $networkError);

        if ($result !== null) {
            return $this->enrichNcmDescription($result);
        }

        if ($this->isBrazilianBarcode($barcode)) {
            if ($networkError) {
                throw new RuntimeException('Não foi possível consultar o Cosmos (Bluesoft). Verifique a conexão e tente novamente.');
            }

            throw new RuntimeException('Código de barras não encontrado no Cosmos (Bluesoft).');
        }

        $this->assertDailyApiQuota();
        $this->incrementDailyApiQuota();

        $result = $this->tryFetchFromUpcItemDb($barcode, $networkError);

        if ($result !== null) {
            return $result;
        }

        $result = $this->tryFetchFromOpenFoodFacts($barcode, $networkError);

        if ($result !== null) {
            return $result;
        }

        if ($networkError) {
            throw new RuntimeException('Não foi possível consultar o código de barras. Verifique a conexão e tente novamente.');
        }

        throw new RuntimeException('Código de barras não encontrado nas fontes consultadas.');
    }

    protected function isBrazilianBarcode(string $barcode): bool
    {
        return str_starts_with($barcode, '789');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function enrichNcmDescription(array $result): array
    {
        $ncm = preg_replace('/\D/', '', (string) ($result['ncm'] ?? ''));

        if (strlen($ncm) === 8 && blank($result['ncm_descricao'] ?? null)) {
            $descricao = \App\Models\Ncm::query()->where('codigo', $ncm)->value('descricao');

            if ($descricao) {
                $result['ncm_descricao'] = $descricao;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function tryFetchFromCosmos(string $barcode, bool &$networkError): ?array
    {
        $token = $this->resolveCosmosToken();

        if ($token === null) {
            return null;
        }

        $this->assertCosmosDailyQuota();

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->withHeaders([
                    'X-Cosmos-Token' => $token,
                    'User-Agent' => self::COSMOS_USER_AGENT,
                ])
                ->get(sprintf(self::COSMOS_GTIN_URL, $barcode));
        } catch (ConnectionException) {
            $networkError = true;

            return null;
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 401) {
                throw new RuntimeException('Cosmos retornou 401. Verifique o token em Configurações » Empresa » API de Serviços.');
            }

            if ($exception->response?->status() === 429) {
                throw new RuntimeException('Limite diário da API Cosmos (Bluesoft) atingido (' . self::COSMOS_DAILY_LIMIT . ' consultas). Tente amanhã ou cadastre manualmente.');
            }

            return null;
        }

        $this->incrementCosmosDailyQuota();

        if ($response->status() === 401) {
            throw new RuntimeException('Cosmos retornou 401. Verifique o token em Configurações » Empresa » API de Serviços.');
        }

        if ($response->status() === 429) {
            throw new RuntimeException('Limite diário da API Cosmos (Bluesoft) atingido (' . self::COSMOS_DAILY_LIMIT . ' consultas). Tente amanhã ou cadastre manualmente.');
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        $descricao = trim((string) ($payload['description'] ?? ''));

        if ($descricao === '') {
            return null;
        }

        $ncm = '';
        $ncmDescricao = '';
        if (is_array($payload['ncm'] ?? null)) {
            $ncm = preg_replace('/\D/', '', (string) ($payload['ncm']['code'] ?? ''));
            $ncmDescricao = trim((string) ($payload['ncm']['full_description'] ?? $payload['ncm']['description'] ?? ''));
        }

        $cest = '';
        if (is_array($payload['cest'] ?? null)) {
            $cest = preg_replace('/\D/', '', (string) ($payload['cest']['code'] ?? ''));
        }

        $marca = '';
        if (is_array($payload['brand'] ?? null)) {
            $marca = trim((string) ($payload['brand']['name'] ?? ''));
        }

        $pesoKg = $this->resolveCosmosWeightKg($payload);

        $precoVenda = 0.0;
        if (isset($payload['avg_price'])) {
            $precoVenda = (float) $payload['avg_price'];
        } elseif (isset($payload['max_price'])) {
            $precoVenda = (float) $payload['max_price'];
        } elseif (isset($payload['price'])) {
            $precoVenda = $this->parseCosmosPrice((string) $payload['price']);
        }

        $thumb = trim((string) ($payload['thumbnail'] ?? ''));
        $fotoUrl = $thumb !== '' ? $thumb : null;

        $result = [
            'source' => 'cosmos',
            'descricao' => Str::upper(Str::limit($descricao, 120, '')),
            'ncm' => strlen($ncm) === 8 ? $ncm : null,
            'cest' => strlen($cest) >= 7 ? substr($cest, 0, 7) : null,
            'foto_url' => $fotoUrl,
        ];

        if ($pesoKg !== null && $pesoKg > 0) {
            $result['peso_kg'] = $pesoKg;
        }

        if ($ncmDescricao !== '') {
            $result['ncm_descricao'] = Str::upper(Str::limit($ncmDescricao, 120, ''));
        }

        if ($marca !== '') {
            $result['marca'] = Str::upper(Str::limit($marca, 60, ''));
        }

        if ($precoVenda > 0) {
            $result['preco_venda'] = $precoVenda;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveCosmosWeightKg(array $payload): ?float
    {
        foreach (['net_weight', 'gross_weight'] as $field) {
            if (! isset($payload[$field])) {
                continue;
            }

            $gramas = (float) $payload[$field];

            if ($gramas <= 0) {
                continue;
            }

            return round($gramas / 1000, 3);
        }

        return null;
    }

    protected function parseCosmosPrice(string $value): float
    {
        $txt = trim($value);
        $txt = str_replace(['R$', ' '], '', $txt);
        $txt = str_replace('.', '', $txt);
        $txt = str_replace(',', '.', $txt);

        return is_numeric($txt) ? (float) $txt : 0.0;
    }

    protected function resolveCosmosToken(): ?string
    {
        $empresaId = session('erp_empresa_id', auth()->user()?->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;
        $token = trim((string) ($empresa?->param_api_servicos_token ?? ''));

        return $token !== '' ? $token : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function tryFetchFromUpcItemDb(string $barcode, bool &$networkError): ?array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate',
                    'User-Agent' => self::HTTP_USER_AGENT,
                ])
                ->post('https://api.upcitemdb.com/prod/trial/lookup', [
                    'upc' => $barcode,
                ]);
        } catch (ConnectionException) {
            $networkError = true;

            return null;
        } catch (RequestException) {
            return null;
        }

        if ($response->status() === 429) {
            throw new RuntimeException('Limite diário de consultas externas atingido. Tente amanhã ou cadastre manualmente.');
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $items */
        $items = $response->json('items') ?? [];

        if ($items === []) {
            return null;
        }

        $item = $items[0];
        $title = trim((string) ($item['title'] ?? $item['description'] ?? ''));

        if ($title === '') {
            return null;
        }

        /** @var array<int, string>|null $images */
        $images = $item['images'] ?? null;
        $category = trim((string) ($item['category'] ?? ''));

        return [
            'source' => 'upcitemdb',
            'descricao' => Str::upper(Str::limit($title, 120, '')),
            'marca' => filled($item['brand'] ?? null) ? Str::upper((string) $item['brand']) : null,
            'grupo' => $category !== '' ? Str::upper(Str::limit($category, 60, '')) : null,
            'foto_url' => is_array($images) && filled($images[0] ?? null) ? (string) $images[0] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function tryFetchFromOpenFoodFacts(string $barcode, bool &$networkError): ?array
    {
        foreach ($this->openFoodFactsHosts($barcode) as $host) {
            $result = $this->requestOpenFoodFacts($host, $barcode, $networkError);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function openFoodFactsHosts(string $barcode): array
    {
        if (str_starts_with($barcode, '789')) {
            return ['br.openfoodfacts.org', 'world.openfoodfacts.org'];
        }

        return ['world.openfoodfacts.org', 'br.openfoodfacts.org'];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function requestOpenFoodFacts(string $host, string $barcode, bool &$networkError): ?array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => self::HTTP_USER_AGENT,
                ])
                ->get("https://{$host}/api/v2/product/{$barcode}.json");
        } catch (ConnectionException) {
            $networkError = true;

            return null;
        } catch (RequestException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        if ((int) $response->json('status') !== 1) {
            return null;
        }

        /** @var array<string, mixed>|null $product */
        $product = $response->json('product');

        if (! is_array($product)) {
            return null;
        }

        $title = $this->resolveOpenFoodFactsTitle($product);

        if ($title === '') {
            return null;
        }

        $brand = trim((string) ($product['brands'] ?? ''));
        $brand = Str::before($brand, ',');

        $grupo = trim((string) ($product['pnns_groups_2'] ?? ''));
        if ($grupo === '') {
            $grupo = trim((string) ($product['categories'] ?? ''));
            $grupo = Str::before($grupo, ',');
        }

        $fotoUrl = trim((string) ($product['image_url'] ?? $product['image_front_url'] ?? ''));

        return [
            'source' => 'openfoodfacts',
            'descricao' => Str::upper(Str::limit($title, 120, '')),
            'marca' => $brand !== '' ? Str::upper(Str::limit($brand, 60, '')) : null,
            'grupo' => $grupo !== '' ? Str::upper(Str::limit($grupo, 60, '')) : null,
            'foto_url' => $fotoUrl !== '' ? $fotoUrl : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function resolveOpenFoodFactsTitle(array $product): string
    {
        foreach (['product_name_pt', 'product_name', 'product_name_en'] as $field) {
            $value = trim((string) ($product[$field] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function assertCosmosDailyQuota(): void
    {
        if ($this->cosmosDailyApiCalls() >= self::COSMOS_DAILY_LIMIT) {
            throw new RuntimeException('Limite diário da API Cosmos (Bluesoft) atingido (' . self::COSMOS_DAILY_LIMIT . ' consultas). Tente amanhã ou cadastre manualmente.');
        }
    }

    protected function incrementCosmosDailyQuota(): void
    {
        $key = $this->cosmosDailyApiCacheKey();
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->endOfDay());
    }

    protected function cosmosDailyApiCalls(): int
    {
        return (int) Cache::get($this->cosmosDailyApiCacheKey(), 0);
    }

    protected function cosmosDailyApiCacheKey(): string
    {
        return 'erp.barcode.cosmos_api_calls.' . now()->toDateString();
    }

    protected function assertDailyApiQuota(): void
    {
        if ($this->dailyApiCalls() >= self::DAILY_API_LIMIT) {
            throw new RuntimeException('Limite diário de consultas externas atingido (' . self::DAILY_API_LIMIT . '). Tente amanhã ou cadastre manualmente.');
        }
    }

    protected function incrementDailyApiQuota(): void
    {
        $key = $this->dailyApiCacheKey();
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->endOfDay());
    }

    protected function dailyApiCalls(): int
    {
        return (int) Cache::get($this->dailyApiCacheKey(), 0);
    }

    protected function dailyApiCacheKey(): string
    {
        return 'erp.barcode.api_calls.' . now()->toDateString();
    }
}
