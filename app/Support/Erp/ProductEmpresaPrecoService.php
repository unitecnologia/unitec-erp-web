<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use App\Models\Product;
use App\Models\ProductEmpresaPreco;

final class ProductEmpresaPrecoService
{
    /** @var list<string> */
    public const FIELDS = [
        'preco_compra',
        'pct_custos',
        'preco_custo',
        'pct_lucro',
        'preco_venda',
        'preco_atacado',
        'preco_especial',
    ];

    /**
     * @return array<string, float>
     */
    public function extractFromProduct(Product $product): array
    {
        $out = [];

        foreach (self::FIELDS as $field) {
            $out[$field] = round((float) ($product->{$field} ?? 0), 2);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, float>
     */
    public function extractFromFormData(array $data): array
    {
        $out = [];

        foreach (self::FIELDS as $field) {
            $out[$field] = round(BrDecimal::parse($data[$field] ?? 0, 2), 2);
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    public function resolve(Product $product, ?int $empresaId = null): array
    {
        $empresaId = $empresaId ?: (int) (session('erp_empresa_id') ?? 0);

        if ($empresaId > 0) {
            $row = $this->overlayForEmpresa($product, $empresaId);

            if ($row) {
                return $this->fillZerosFromProduct(
                    $this->extractFromRow($row),
                    $this->extractFromProduct($product)
                );
            }
        }

        return $this->extractFromProduct($product);
    }

    public function resolvePrecoVenda(Product $product, ?int $empresaId = null): float
    {
        return $this->resolve($product, $empresaId)['preco_venda'];
    }

    public function resolvePrecoAtacado(Product $product, ?int $empresaId = null): float
    {
        return $this->resolve($product, $empresaId)['preco_atacado'];
    }

    /**
     * @param  array<string, float|int|string>  $prices
     */
    public function upsert(Product $product, int $empresaId, array $prices): ProductEmpresaPreco
    {
        $payload = ['product_id' => $product->id, 'empresa_id' => $empresaId];

        foreach (self::FIELDS as $field) {
            $payload[$field] = round((float) ($prices[$field] ?? 0), 2);
        }

        return ProductEmpresaPreco::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'empresa_id' => $empresaId,
            ],
            $payload
        );
    }

    /**
     * @param  array<string, float|int|string>  $prices
     * @param  list<int>  $empresaIds
     */
    public function replicate(Product $product, array $prices, array $empresaIds): int
    {
        $count = 0;

        foreach (array_unique(array_map('intval', $empresaIds)) as $empresaId) {
            if ($empresaId <= 0) {
                continue;
            }

            $this->upsert($product, $empresaId, $prices);
            $count++;
        }

        return $count;
    }

    /**
     * Garante linha da empresa atual a partir do cadastro global (primeira vez).
     */
    public function ensureForEmpresa(Product $product, int $empresaId): ProductEmpresaPreco
    {
        $existing = ProductEmpresaPreco::query()
            ->where('product_id', $product->id)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->upsert($product, $empresaId, $this->extractFromProduct($product));
    }

    /**
     * Overlay 0 + cadastro > 0 → usa o cadastro naquele campo (importação vazia / loja sem preço).
     *
     * @param  array<string, float>  $overlay
     * @param  array<string, float>  $cadastro
     * @return array<string, float>
     */
    protected function fillZerosFromProduct(array $overlay, array $cadastro): array
    {
        foreach (self::FIELDS as $field) {
            if (($overlay[$field] ?? 0.0) == 0.0 && ($cadastro[$field] ?? 0.0) != 0.0) {
                $overlay[$field] = $cadastro[$field];
            }
        }

        return $overlay;
    }

    protected function overlayForEmpresa(Product $product, int $empresaId): ?ProductEmpresaPreco
    {
        if ($product->relationLoaded('empresaPrecos')) {
            $row = $product->empresaPrecos->firstWhere('empresa_id', $empresaId);

            return $row instanceof ProductEmpresaPreco ? $row : null;
        }

        return ProductEmpresaPreco::query()
            ->where('product_id', $product->id)
            ->where('empresa_id', $empresaId)
            ->first();
    }

    /**
     * @return array<string, float>
     */
    protected function extractFromRow(ProductEmpresaPreco $row): array
    {
        $out = [];

        foreach (self::FIELDS as $field) {
            $out[$field] = round((float) ($row->{$field} ?? 0), 2);
        }

        return $out;
    }

    /**
     * Empresas ativas para replicação (exceto a atual), respeitando acesso do usuário.
     *
     * @return \Illuminate\Support\Collection<int, Empresa>
     */
    public function empresasParaReplicacao(int $excludeEmpresaId, ?\App\Models\User $user = null)
    {
        return $this->empresasAcessiveis($user)
            ->where('id', '!=', $excludeEmpresaId)
            ->values();
    }

    /**
     * Empresas ativas que o usuário pode ver/editar preços (painel Ajuste de Preço).
     *
     * @return \Illuminate\Support\Collection<int, Empresa>
     */
    public function empresasParaPainelPrecos(?\App\Models\User $user = null)
    {
        return $this->empresasAcessiveis($user);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Empresa>
     */
    protected function empresasAcessiveis(?\App\Models\User $user = null)
    {
        $query = Empresa::query()
            ->where('ativo', true)
            ->orderBy('nome');

        if ($user && ! $user->is_admin) {
            $ids = $user->accessibleEmpresaIds();
            $query->whereIn('id', $ids !== [] ? $ids : [0]);
        }

        return $query->get(['id', 'nome', 'codigo']);
    }
}
