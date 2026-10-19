<?php

namespace App\Support\Erp\Pdv;

use App\Models\Product;
use App\Support\Erp\ProductSalesQty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Consulta + ranking PDV: candidatos leves, ordenação em PHP.
 *
 * Termos curtos: só prefixo (índice). Parcial/multi: códigos por prefixo +
 * descrição com AND de %token% em query separada (evita OR que anula índice).
 * Ranking: exato → início da descrição → qtd saída (90d) → descrição.
 */
final class PdvProductSearchRanking
{
    public const DIAS_SAIDA = 90;

    public const CANDIDATE_LIMIT = 200;

    /** Abaixo disso não usa %token% (evita varredura ampla). */
    public const MIN_PARTIAL_LEN = 3;

    /** Em multi-termo, token menor que isso é ignorado no AND da descrição. */
    public const MIN_TOKEN_LEN = 2;

    /**
     * Busca candidatos sem ORDER BY (LIMIT pode encerrar cedo; ranking é em PHP).
     *
     * @return Collection<int, Product>
     */
    public static function fetchCandidates(string $term, int $limit = self::CANDIDATE_LIMIT): Collection
    {
        $tokens = self::tokens($term);

        if ($tokens === [] || $limit < 1) {
            return collect();
        }

        if (! self::allowsPartial($tokens)) {
            $prefix = $tokens[0];

            return Product::query()
                ->where('ativo', true)
                ->where(function (Builder $q) use ($prefix): void {
                    self::whereCodePrefix($q, $prefix);
                    $q->orWhere('descricao', 'like', $prefix.'%');
                })
                ->limit($limit)
                ->get();
        }

        $codePrefix = $tokens[0];
        $descTokens = array_values(array_filter(
            $tokens,
            static fn (string $t): bool => mb_strlen($t, 'UTF-8') >= self::MIN_TOKEN_LEN,
        ));

        if ($descTokens === []) {
            $descTokens = [$tokens[0]];
        }

        $byCode = Product::query()
            ->where('ativo', true)
            ->where(function (Builder $q) use ($codePrefix): void {
                self::whereCodePrefix($q, $codePrefix);
            })
            ->limit($limit)
            ->get();

        $byDesc = Product::query()
            ->where('ativo', true)
            ->where(function (Builder $q) use ($descTokens): void {
                foreach ($descTokens as $token) {
                    $q->where('descricao', 'like', '%'.$token.'%');
                }
            })
            ->limit($limit)
            ->get();

        return $byCode
            ->concat($byDesc)
            ->unique('id')
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  list<string>  $matchAttributes  Atributos para exato / começa-com (códigos etc.)
     * @return Collection<int, Product>
     */
    public static function rankProducts(
        Collection $products,
        string $term,
        array $matchAttributes,
        ?int $empresaId = null,
        int $limit = 100,
    ): Collection {
        if ($products->isEmpty()) {
            return $products;
        }

        $saidas = self::qtdSaidaByProductIds(
            $products->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $empresaId,
        );

        $foldedTerm = self::fold($term);
        $tokens = self::tokens($term);
        $firstToken = $tokens[0] ?? $foldedTerm;

        return $products
            ->sort(function (Product $a, Product $b) use ($foldedTerm, $firstToken, $matchAttributes, $saidas): int {
                $aTier = self::matchTier($a, $foldedTerm, $firstToken, $matchAttributes);
                $bTier = self::matchTier($b, $foldedTerm, $firstToken, $matchAttributes);

                if ($aTier !== $bTier) {
                    return $aTier <=> $bTier;
                }

                $aQty = $saidas[(int) $a->id] ?? 0.0;
                $bQty = $saidas[(int) $b->id] ?? 0.0;

                if ($aQty !== $bQty) {
                    return $bQty <=> $aQty;
                }

                return strcmp(
                    self::fold((string) $a->descricao),
                    self::fold((string) $b->descricao),
                );
            })
            ->values()
            ->take($limit)
            ->values();
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, float> product_id => qtd
     */
    public static function qtdSaidaByProductIds(array $productIds, ?int $empresaId = null): array
    {
        return ProductSalesQty::byProductIds($productIds, $empresaId, self::DIAS_SAIDA);
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $term): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $term) ?? '');

        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', self::fold($normalized), -1, PREG_SPLIT_NO_EMPTY);

        return array_values($parts ?: []);
    }

    /**
     * @param  list<string>  $tokens
     */
    public static function allowsPartial(array $tokens): bool
    {
        if ($tokens === []) {
            return false;
        }

        $max = 0;
        foreach ($tokens as $token) {
            $max = max($max, mb_strlen($token, 'UTF-8'));
        }

        return $max >= self::MIN_PARTIAL_LEN;
    }

    public static function fold(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');

        return strtr($value, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ]);
    }

    /**
     * @param  Builder<Product>  $query
     */
    private static function whereCodePrefix(Builder $query, string $prefix): void
    {
        $like = $prefix.'%';
        $query->where('codigo', 'like', $like)
            ->orWhere('codigo_barras', 'like', $like)
            ->orWhere('codigo_barras_caixa', 'like', $like)
            ->orWhere('referencia', 'like', $like);
    }

    /**
     * Menor = melhor. 0 exato, 1 descrição começa com termo, 2 descrição começa com 1º token, 3 outro começa-com.
     *
     * @param  list<string>  $matchAttributes
     */
    private static function matchTier(
        Product $product,
        string $foldedTerm,
        string $firstToken,
        array $matchAttributes,
    ): int {
        if ($foldedTerm !== '' && self::equalsAny($product, $foldedTerm, $matchAttributes)) {
            return 0;
        }

        $desc = self::fold((string) ($product->descricao ?? ''));

        if ($foldedTerm !== '' && $desc !== '' && str_starts_with($desc, $foldedTerm)) {
            return 1;
        }

        if ($firstToken !== '' && $desc !== '' && str_starts_with($desc, $firstToken)) {
            return 2;
        }

        if ($foldedTerm !== '' && self::startsWithAny($product, $foldedTerm, $matchAttributes)) {
            return 3;
        }

        return 4;
    }

    /**
     * @param  list<string>  $attributes
     */
    private static function equalsAny(Product $product, string $foldedTerm, array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            $value = self::fold((string) ($product->{$attribute} ?? ''));

            if ($value !== '' && $value === $foldedTerm) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $attributes
     */
    private static function startsWithAny(Product $product, string $prefix, array $attributes): bool
    {
        if ($prefix === '') {
            return false;
        }

        foreach ($attributes as $attribute) {
            $value = self::fold((string) ($product->{$attribute} ?? ''));

            if ($value !== '' && str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
