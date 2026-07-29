<?php

namespace App\Support\Erp\NotaFornecedor;

use App\Models\Person;
use App\Models\Product;
use App\Models\ProdutoFornecedor;
use App\Support\Erp\BrDecimal;
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
 * Quando encontra por EAN/código seguro, persiste o vínculo ProdutoFornecedor.
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

        return array_values(array_map(function (array $item) use ($fornecedor, $vinculos): array {
            $match = $this->findExistingProduct($item, $fornecedor, $vinculos);

            if ($match && $fornecedor) {
                $this->vincularProduto($match, $fornecedor, (string) ($item['codigo'] ?? $match->codigo));
            }

            $item['vinculado'] = $match !== null;
            $item['product_id'] = $match?->id;
            $item['produto_codigo'] = $match?->codigo;
            $item['produto_descricao'] = $match?->descricao;
            $item['grupo'] = filled($match?->grupo) ? (string) $match->grupo : (string) ($item['grupo'] ?? '');
            $item['pr_venda'] = $match && $match->preco_venda !== null
                ? number_format((float) $match->preco_venda, 3, ',', '.')
                : number_format(
                    BrDecimal::parse((string) ($item['pr_venda'] ?? $item['prc_unitario'] ?? '0'), 3),
                    3,
                    ',',
                    '.'
                );

            return $item;
        }, $itens));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function findExistingForItem(array $item, ?string $cnpjFornecedor): ?Product
    {
        $fornecedor = $this->resolveFornecedor($cnpjFornecedor);
        $vinculos = $fornecedor
            ? $this->loadVinculosPorCodigo((int) $fornecedor->id)
            : collect();

        return $this->findExistingProduct($item, $fornecedor, $vinculos);
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
        $codigoFornecedor = trim((string) ($item['codigo'] ?? ''));
        $ean = preg_replace('/\D/', '', (string) ($item['ean'] ?? '')) ?? '';

        if ($codigoFornecedor !== '' && $codigoFornecedor !== '—' && $vinculos->has($codigoFornecedor)) {
            $product = $vinculos->get($codigoFornecedor)?->product;

            if ($product instanceof Product) {
                return $product;
            }
        }

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
}
