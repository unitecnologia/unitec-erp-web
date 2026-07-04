<?php

namespace App\Support\Erp\Reports;

use App\Models\Product;
use Illuminate\Support\Carbon;

class ProductListagemReport
{
    /**
     * @return array<string, string>
     */
    public static function columnDefinitions(): array
    {
        return [
            'codigo' => 'CÓDIGO',
            'codigo_barras' => 'CÓD.BARRA',
            'referencia' => 'REFERÊNCIA',
            'descricao' => 'DESCRIÇÃO',
            'grupo' => 'GRUPO',
            'unidade' => 'UND',
            'preco_venda' => 'Preço',
            'preco_compra' => 'CUSTO COMPRA',
            'estoque' => 'ESTOQUE',
            'estoque_minimo' => 'EST. MÍNIMO',
            'falta' => 'FALTA',
            'validade' => 'VALIDADE',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return [
            'codigo',
            'codigo_barras',
            'referencia',
            'descricao',
            'grupo',
            'unidade',
            'preco_venda',
            'preco_compra',
            'estoque',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultColumnsCritico(): array
    {
        return [
            'codigo',
            'descricao',
            'grupo',
            'unidade',
            'estoque',
            'estoque_minimo',
            'falta',
        ];
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    public static function resolveColumns(?array $requested): array
    {
        $allowed = array_keys(static::columnDefinitions());

        if ($requested === null || $requested === []) {
            return static::defaultColumns();
        }

        $columns = [];

        foreach ($requested as $column) {
            if (! in_array($column, $allowed, true)) {
                continue;
            }

            $columns[] = $column;
        }

        return $columns !== [] ? $columns : static::defaultColumns();
    }

    public static function cellValue(Product $product, string $column): string
    {
        return match ($column) {
            'codigo' => (string) ($product->codigo ?? ''),
            'codigo_barras' => (string) ($product->codigo_barras ?? ''),
            'referencia' => (string) ($product->referencia ?? ''),
            'descricao' => (string) ($product->descricao ?? ''),
            'grupo' => (string) ($product->grupo ?? ''),
            'unidade' => mb_strtoupper((string) ($product->unidade ?: 'UN'), 'UTF-8'),
            'preco_venda' => static::formatMoney((float) $product->preco_venda),
            'preco_compra' => static::formatMoney((float) $product->preco_compra),
            'estoque' => static::formatQuantity((float) $product->estoque),
            'estoque_minimo' => static::formatQuantity((float) $product->estoque_minimo),
            'falta' => static::formatQuantity(static::faltaEstoque($product)),
            'validade' => static::formatValidade($product),
            default => '',
        };
    }

    public static function isNumericColumn(string $column): bool
    {
        return in_array($column, ['preco_venda', 'preco_compra', 'estoque', 'estoque_minimo', 'falta'], true);
    }

    public static function isSummableColumn(string $column): bool
    {
        return static::isNumericColumn($column);
    }

    public static function columnRawValue(Product $product, string $column): ?float
    {
        return match ($column) {
            'preco_venda' => (float) $product->preco_venda,
            'preco_compra' => (float) $product->preco_compra,
            'estoque' => (float) $product->estoque,
            'estoque_minimo' => (float) $product->estoque_minimo,
            'falta' => static::faltaEstoque($product),
            default => null,
        };
    }

    /**
     * @param  iterable<int, Product>  $products
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    public static function columnTotals(iterable $products, array $columns): array
    {
        $sums = array_fill_keys($columns, 0.0);

        foreach ($products as $product) {
            foreach ($columns as $column) {
                $raw = static::columnRawValue($product, $column);

                if ($raw !== null) {
                    $sums[$column] += $raw;
                }
            }
        }

        $totals = [];
        $labelPlaced = false;

        foreach ($columns as $column) {
            if (! static::isSummableColumn($column)) {
                $totals[$column] = $labelPlaced ? '' : 'TOTAL';
                $labelPlaced = true;

                continue;
            }

            $totals[$column] = static::formatColumnTotal($column, $sums[$column]);
        }

        return $totals;
    }

    public static function formatColumnTotal(string $column, float $value): string
    {
        return match ($column) {
            'estoque', 'estoque_minimo', 'falta' => static::formatQuantity($value),
            default => static::formatMoney($value),
        };
    }

    public static function faltaEstoque(Product $product): float
    {
        return max(0, (float) $product->estoque_minimo - (float) $product->estoque);
    }

    public static function formatValidade(Product $product): string
    {
        $attributes = $product->getAttributes();
        $value = $attributes['validade'] ?? null;

        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        return Carbon::parse((string) $value)->format('d/m/Y');
    }

    public static function validadeVencida(Product $product): bool
    {
        return $product->validadeVencida();
    }

    public static function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public static function formatQuantity(float $value): string
    {
        if (fmod($value, 1.0) === 0.0) {
            return number_format($value, 0, ',', '.');
        }

        return number_format($value, 3, ',', '.');
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'ativos' => 'Ativos',
            'inativos' => 'Inativos',
            'todos' => 'Todos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function orderLabels(): array
    {
        return [
            'descricao' => 'Descrição',
            'codigo' => 'Código',
            'grupo' => 'Grupo',
            'preco_venda' => 'Preço',
            'estoque' => 'Estoque',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function estoqueFilterLabels(): array
    {
        return [
            'todos' => 'Todos',
            'positivo' => 'Estoque positivo',
            'negativo' => 'Estoque negativo',
            'zero' => 'Estoque zerado',
            'critico' => 'Abaixo do mínimo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function searchFieldLabels(): array
    {
        return [
            'codigo' => 'Código',
            'referencia' => 'Referência',
            'codigo_barras' => 'Cód. Barras',
            'descricao' => 'Descrição',
            'grupo' => 'Grupo',
            'preco_venda' => 'Preço venda',
            'estoque' => 'Qtd atual',
            'localizacao' => 'Localização',
        ];
    }
}
