<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use App\Models\Product;
use App\Support\Erp\Ccg\CcgCertificadoResolver;
use App\Support\Erp\Ccg\CcgConsGtinClient;
use App\Support\Erp\Ccg\CcgConsultaException;
use App\Support\Erp\Ccg\Gtin;
use App\Support\Erp\License\LicencaHttpClient;
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

    private const HTTP_USER_AGENT = 'UnitecERP/1.0 (barcode-lookup)';

    public function __construct(
        private readonly CcgConsGtinClient $ccg = new CcgConsGtinClient(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fetch(string $barcode, ?int $excludeProductId = null): array
    {
        $barcode = Gtin::digits($barcode);

        if (strlen($barcode) < 8 || strlen($barcode) > 14) {
            throw new RuntimeException('Informe um código de barras válido (8 a 14 dígitos).');
        }

        $internal = $this->fetchFromInternal($barcode, $excludeProductId);

        if ($internal !== null) {
            return $internal;
        }

        if (! Gtin::isAcceptedLength($barcode)) {
            throw new RuntimeException('Informe um GTIN de 8, 12, 13 ou 14 dígitos.');
        }

        if (! Gtin::hasValidCheckDigit($barcode)) {
            throw new CcgConsultaException(
                '9491 – Rejeição: GTIN com dígito verificador inválido. A consulta não foi enviada à SVRS.',
                CcgConsultaException::REJEICAO,
            );
        }

        if (Gtin::isPrefixBrasil($barcode)) {
            return Cache::remember(
                'erp.barcode.ccg.' . $barcode,
                now()->addDays(self::CACHE_TTL_DAYS),
                fn (): array => $this->fetchFromCcg($barcode),
            );
        }

        return Cache::remember(
            'erp.barcode.lookup.v2.' . $barcode,
            now()->addDays(self::CACHE_TTL_DAYS),
            fn (): array => $this->fetchFromInternational($barcode),
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
     * Consulta síncrona ccgConsGTIN. Só entra aqui depois que o código foi confirmado na busca.
     *
     * @return array<string, mixed>
     */
    protected function fetchFromCcg(string $barcode): array
    {
        $empresa = $this->currentEmpresa();

        if (! (bool) $empresa->param_api_servicos_habilitar) {
            throw new CcgConsultaException(
                'Busca Produto Auto está desabilitada. Ative em Configurações » Empresa » Busca Produto Auto.',
                CcgConsultaException::INDISPONIVEL,
            );
        }

        $certificate = CcgCertificadoResolver::resolve($empresa);
        $consulta = $this->ccg->consultar(
            $barcode,
            $certificate,
            EmpresaParametros::normalizeCcgConsGtinUrl($empresa->param_api_servicos_url ?? null),
            $this->timeoutSeconds($empresa),
        );

        $ncm = $consulta->ncm;
        $cest = $consulta->cests[0] ?? null;
        $descricao = trim((string) $consulta->xProd);

        $result = [
            'source' => 'ccg',
            'gtin' => $consulta->gtin ?? $barcode,
            'tp_gtin' => $consulta->tpGtin,
            'descricao' => $descricao !== '' ? Str::upper(Str::limit($descricao, 255, '')) : null,
            'ncm' => $ncm,
            'cest' => $cest,
            'cests' => $consulta->cests,
        ];

        return $this->enrichNcmDescription($result);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchFromInternational(string $barcode): array
    {
        $networkError = false;

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

    protected function currentEmpresa(): Empresa
    {
        $empresaId = (int) session('erp_empresa_id', auth()->user()?->empresa_id);
        $empresa = $empresaId > 0 ? Empresa::query()->find($empresaId) : null;

        if (! $empresa instanceof Empresa) {
            throw new CcgConsultaException(
                'Selecione a empresa antes de consultar o GTIN.',
                CcgConsultaException::INDISPONIVEL,
            );
        }

        return $empresa;
    }

    protected function timeoutSeconds(Empresa $empresa): int
    {
        $timeout = (int) ($empresa->param_api_servicos_timeout ?? 30);

        if ($timeout < 1) {
            return 30;
        }

        return min($timeout, 300);
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
    protected function tryFetchFromUpcItemDb(string $barcode, bool &$networkError): ?array
    {
        try {
            $response = Http::withOptions(LicencaHttpClient::options())
                ->timeout(20)
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
            $response = Http::withOptions(LicencaHttpClient::options())
                ->timeout(20)
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
