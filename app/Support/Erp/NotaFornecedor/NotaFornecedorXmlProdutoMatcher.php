<?php

namespace App\Support\Erp\NotaFornecedor;

use App\Models\Person;
use App\Models\Product;
use App\Models\ProdutoFornecedor;
use Illuminate\Support\Collection;

/**
 * Resolve vínculo/cadastro de itens do XML com o catálogo de produtos.
 *
 * Prioridade segura:
 * 1) vínculo produto↔fornecedor (cProd)
 * 2) código de barras (EAN)
 * 3) código/referência somente se o produto já for do mesmo fornecedor
 *
 * Não faz auto-match só por descrição (risco alto de falso positivo).
 * O vínculo ProdutoFornecedor é gravado só no Finalizar da importação.
 */
final class NotaFornecedorXmlProdutoMatcher
{
    /**
     * @param  list<array<string, mixed>>  $itens
     * @return list<array<string, mixed>>
     */
    public function matchItens(array $itens, ?string $cnpjFornecedor): array
    {
        $fornecedor = $this->resolveFornecedor($cnpjFornecedor);
        $vinculos = $fornecedor
            ? $this->loadVinculosPorCodigo((int) $fornecedor->id)
            : collect();
        $unidadesCadastradas = Product::unidades();

        return array_values(array_map(function (array $item) use ($fornecedor, $vinculos, $unidadesCadastradas): array {
            $match = $this->findExistingProduct($item, $fornecedor, $vinculos);

            $item['vinculado'] = $match !== null;
            $item['product_id'] = $match?->id;
            $item['produto_codigo'] = $match?->codigo;
            $item['produto_descricao'] = $match?->descricao;
            $item['grupo'] = filled($match?->grupo) ? (string) $match->grupo : (string) ($item['grupo'] ?? '');
            // Pr. Venda inicia zerado na importação; o usuário define depois (custo unitário ≠ preço de venda).
            $item['pr_venda'] = '0,000';

            if ($match) {
                $unidadeProduto = mb_strtoupper(trim((string) ($match->unidade ?? '')), 'UTF-8');
                if ($unidadeProduto !== '' && array_key_exists($unidadeProduto, $unidadesCadastradas)) {
                    $item['und'] = $unidadeProduto;
                } else {
                    $undXml = mb_strtoupper(trim((string) ($item['und'] ?? '')), 'UTF-8');
                    $item['und'] = ($undXml !== '' && array_key_exists($undXml, $unidadesCadastradas))
                        ? $undXml
                        : '';
                }
            } else {
                $undXml = mb_strtoupper(trim((string) ($item['und'] ?? '')), 'UTF-8');
                $item['und'] = ($undXml !== '' && array_key_exists($undXml, $unidadesCadastradas))
                    ? $undXml
                    : '';
            }

            return $item;
        }, $itens));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function findExistingForItem(array $item, ?string $cnpjFornecedor): ?Product
    {
        $found = $this->findExistingForIndices([$item], [0], $cnpjFornecedor);

        return $found[0] ?? null;
    }

    /**
     * Mesma prioridade de findExistingForItem, com fornecedor, vínculos,
     * códigos de barras e referências carregados uma vez para a fila inteira.
     *
     * @param  list<array<string, mixed>>  $itens
     * @param  list<int>  $indices
     * @return array<int, Product>
     */
    public function findExistingForIndices(array $itens, array $indices, ?string $cnpjFornecedor): array
    {
        $fornecedor = $this->resolveFornecedor($cnpjFornecedor);
        $codigos = [];

        foreach ($indices as $index) {
            $item = $itens[$index] ?? null;

            if (! is_array($item)) {
                continue;
            }

            $codigo = trim((string) ($item['codigo'] ?? ''));

            if ($codigo !== '' && $codigo !== '—') {
                $codigos[] = $codigo;
            }
        }

        $vinculos = $fornecedor
            ? $this->loadVinculosPorCodigos((int) $fornecedor->id, $codigos)
            : collect();

        $found = [];
        $pendentes = [];

        foreach ($indices as $index) {
            $item = $itens[$index] ?? null;

            if (! is_array($item)) {
                continue;
            }

            $porVinculo = $this->productFromVinculo($item, $vinculos);

            if ($porVinculo instanceof Product) {
                $found[(int) $index] = $porVinculo;

                continue;
            }

            $pendentes[] = (int) $index;
        }

        if ($pendentes === []) {
            return $found;
        }

        $eans = [];

        foreach ($pendentes as $index) {
            $ean = preg_replace('/\D/', '', (string) ($itens[$index]['ean'] ?? '')) ?? '';

            if (strlen($ean) >= 8) {
                $eans[$index] = $ean;
            }
        }

        $porCodigoBarras = $this->productsByBarcode(array_values(array_unique($eans)));
        $ainda = [];

        foreach ($pendentes as $index) {
            $ean = $eans[$index] ?? '';

            if ($ean !== '' && isset($porCodigoBarras[$ean])) {
                $found[$index] = $porCodigoBarras[$ean];

                continue;
            }

            $ainda[] = $index;
        }

        if ($ainda === [] || ! $fornecedor) {
            return $found;
        }

        $codigosRestantes = [];

        foreach ($ainda as $index) {
            $codigo = trim((string) ($itens[$index]['codigo'] ?? ''));

            if ($codigo !== '' && $codigo !== '—') {
                $codigosRestantes[$index] = $codigo;
            }
        }

        $porCodigo = $this->productsByFornecedorCodigo((int) $fornecedor->id, array_values($codigosRestantes));

        foreach ($codigosRestantes as $index => $codigo) {
            if (isset($porCodigo[$codigo])) {
                $found[$index] = $porCodigo[$codigo];
            }
        }

        return $found;
    }

    public function resolveFornecedorByCnpj(?string $cnpj): ?Person
    {
        return $this->resolveFornecedor($cnpj);
    }

    public function vincularProduto(Product $product, Person $fornecedor, string $codigoFornecedor): ProdutoFornecedor
    {
        $codigo = trim($codigoFornecedor);

        $vinculo = ProdutoFornecedor::query()->firstOrNew([
            'person_id' => $fornecedor->id,
            'codigo_fornecedor' => $codigo !== '' && $codigo !== '—' ? $codigo : (string) $product->codigo,
        ]);

        $vinculo->product_id = $product->id;
        $vinculo->save();

        if ((int) ($product->ult_fornecedor_id ?? 0) !== (int) $fornecedor->id) {
            $product->forceFill(['ult_fornecedor_id' => $fornecedor->id])->saveQuietly();
        }

        return $vinculo;
    }

    public function desvincularProduto(Person $fornecedor, string $codigoFornecedor, ?int $productId = null): void
    {
        $codigo = trim($codigoFornecedor);

        $query = ProdutoFornecedor::query()->where('person_id', $fornecedor->id);

        if ($codigo !== '' && $codigo !== '—') {
            $query->where('codigo_fornecedor', $codigo);
        } elseif ($productId) {
            $query->where('product_id', $productId);
        } else {
            return;
        }

        $query->delete();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<string, ProdutoFornecedor>  $vinculos
     */
    private function findExistingProduct(array $item, ?Person $fornecedor, Collection $vinculos): ?Product
    {
        $porVinculo = $this->productFromVinculo($item, $vinculos);

        if ($porVinculo instanceof Product) {
            return $porVinculo;
        }

        $codigoFornecedor = trim((string) ($item['codigo'] ?? ''));
        $ean = preg_replace('/\D/', '', (string) ($item['ean'] ?? '')) ?? '';

        if (strlen($ean) >= 8) {
            $byBarcode = Product::query()
                ->where(function ($query) use ($ean): void {
                    $query->where('codigo_barras', $ean)
                        ->orWhere('codigo_barras_caixa', $ean);
                })
                ->orderByDesc('ativo')
                ->first();

            if ($byBarcode) {
                return $byBarcode;
            }
        }

        // Código/referência só com contexto do mesmo fornecedor (evita falso positivo).
        if ($fornecedor && $codigoFornecedor !== '' && $codigoFornecedor !== '—') {
            $byCode = Product::query()
                ->where('ult_fornecedor_id', $fornecedor->id)
                ->where(function ($query) use ($codigoFornecedor): void {
                    $query->where('codigo', $codigoFornecedor)
                        ->orWhere('referencia', $codigoFornecedor);
                })
                ->orderByDesc('ativo')
                ->first();

            if ($byCode) {
                return $byCode;
            }
        }

        return null;
    }

    private function resolveFornecedor(?string $cnpj): ?Person
    {
        $digits = preg_replace('/\D/', '', (string) $cnpj) ?? '';

        if (strlen($digits) < 11) {
            return null;
        }

        return Person::query()
            ->where('is_fornecedor', true)
            ->where(function ($query) use ($digits): void {
                $query->where('cpf_cnpj', $digits)
                    ->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '/', ''), '-', ''), ' ', '') = ?",
                        [$digits],
                    );
            })
            ->orderByDesc('ativo')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<string, ProdutoFornecedor>  $vinculos
     */
    private function productFromVinculo(array $item, Collection $vinculos): ?Product
    {
        $codigoFornecedor = trim((string) ($item['codigo'] ?? ''));

        if ($codigoFornecedor === '' || $codigoFornecedor === '—' || ! $vinculos->has($codigoFornecedor)) {
            return null;
        }

        $product = $vinculos->get($codigoFornecedor)?->product;

        return $product instanceof Product ? $product : null;
    }

    /**
     * @param  list<string>  $eans
     * @return array<string, Product>
     */
    private function productsByBarcode(array $eans): array
    {
        $eans = array_values(array_unique(array_filter(
            $eans,
            static fn (string $ean): bool => strlen($ean) >= 8,
        )));

        if ($eans === []) {
            return [];
        }

        $wanted = array_fill_keys($eans, true);
        $products = collect();

        foreach (array_chunk($eans, 400) as $chunk) {
            $products = $products->concat(
                Product::query()
                    ->where(function ($query) use ($chunk): void {
                        $query->whereIn('codigo_barras', $chunk)
                            ->orWhereIn('codigo_barras_caixa', $chunk);
                    })
                    ->get(['id', 'codigo', 'descricao', 'grupo', 'unidade', 'ativo', 'codigo_barras', 'codigo_barras_caixa']),
            );
        }

        $map = [];

        foreach ($this->preferirProdutoAtivo($products) as $product) {
            foreach (['codigo_barras', 'codigo_barras_caixa'] as $field) {
                $code = trim((string) ($product->{$field} ?? ''));

                if ($code !== '' && isset($wanted[$code]) && ! isset($map[$code])) {
                    $map[$code] = $product;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $codigos
     * @return array<string, Product>
     */
    private function productsByFornecedorCodigo(int $fornecedorId, array $codigos): array
    {
        $codigos = array_values(array_unique(array_filter(
            array_map(static fn (string $codigo): string => trim($codigo), $codigos),
            static fn (string $codigo): bool => $codigo !== '' && $codigo !== '—',
        )));

        if ($codigos === []) {
            return [];
        }

        $wanted = array_fill_keys($codigos, true);
        $products = collect();

        foreach (array_chunk($codigos, 400) as $chunk) {
            $products = $products->concat(
                Product::query()
                    ->where('ult_fornecedor_id', $fornecedorId)
                    ->where(function ($query) use ($chunk): void {
                        $query->whereIn('codigo', $chunk)
                            ->orWhereIn('referencia', $chunk);
                    })
                    ->get(['id', 'codigo', 'descricao', 'grupo', 'unidade', 'ativo', 'referencia']),
            );
        }

        $map = [];

        foreach ($this->preferirProdutoAtivo($products) as $product) {
            foreach (['codigo', 'referencia'] as $field) {
                $value = trim((string) ($product->{$field} ?? ''));

                if ($value !== '' && isset($wanted[$value]) && ! isset($map[$value])) {
                    $map[$value] = $product;
                }
            }
        }

        return $map;
    }

    /**
     * Ativo primeiro, id menor no empate — equivalente ao orderByDesc(ativo)->first().
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function preferirProdutoAtivo(Collection $products): Collection
    {
        return $products
            ->unique('id')
            ->sortBy([
                ['ativo', 'desc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<string, ProdutoFornecedor>
     */
    private function loadVinculosPorCodigo(int $personId): Collection
    {
        return ProdutoFornecedor::query()
            ->with('product')
            ->where('person_id', $personId)
            ->get()
            ->keyBy(fn (ProdutoFornecedor $vinculo): string => (string) $vinculo->codigo_fornecedor);
    }

    /**
     * @param  list<string>  $codigos
     * @return Collection<string, ProdutoFornecedor>
     */
    private function loadVinculosPorCodigos(int $personId, array $codigos): Collection
    {
        $codigos = array_values(array_unique(array_filter(
            array_map(static fn (string $codigo): string => trim($codigo), $codigos),
            static fn (string $codigo): bool => $codigo !== '' && $codigo !== '—',
        )));

        if ($codigos === []) {
            return collect();
        }

        $rows = collect();

        foreach (array_chunk($codigos, 400) as $chunk) {
            $rows = $rows->concat(
                ProdutoFornecedor::query()
                    ->with('product')
                    ->where('person_id', $personId)
                    ->whereIn('codigo_fornecedor', $chunk)
                    ->get(),
            );
        }

        return $rows->keyBy(fn (ProdutoFornecedor $vinculo): string => (string) $vinculo->codigo_fornecedor);
    }
}
