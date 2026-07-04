<?php

namespace App\Support\Erp;

use App\Models\CompraItem;
use App\Models\NfeItem;
use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;

class ProductCardexService
{
    /**
     * @return array{
     *     compras: array<int, array<string, string>>,
     *     vendas: array<int, array<string, string>>,
     *     nfe: array<int, array<string, string>>,
     *     nfce: array<int, array<string, string>>,
     *     totais: array<string, string>
     * }
     */
    public function forProduct(Product $product): array
    {
        $compras = $this->comprasRows($product);
        $vendas = $this->vendasRows($product);
        $nfe = $this->nfeDocumentRows($product, '55');
        $nfce = $this->nfeDocumentRows($product, '65');

        $totalCompras = $this->sumColumn($compras, 'total_raw');
        $totalVendas = $this->sumColumn($vendas, 'total_raw');
        $totalNfe = $this->sumColumn($nfe, 'total_raw');
        $totalNfce = $this->sumColumn($nfce, 'total_raw');

        return [
            'compras' => $this->stripRawKeys($compras),
            'vendas' => $this->stripRawKeys($vendas),
            'nfe' => $this->stripRawKeys($nfe),
            'nfce' => $this->stripRawKeys($nfce),
            'totais' => [
                'compras' => $this->money($totalCompras),
                'vendas' => $this->money($totalVendas),
                'nfe' => $this->money($totalNfe),
                'nfce' => $this->money($totalNfce),
                'total_vendas' => $this->money($totalVendas + $totalNfe + $totalNfce),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function comprasRows(Product $product): array
    {
        $rows = [];

        foreach (
            CompraItem::query()
                ->with(['compra.fornecedor'])
                ->where('product_id', $product->id)
                ->orderByDesc('id')
                ->get() as $item
        ) {
            $compra = $item->compra;
            $dataEntrada = $compra?->data_entrada ?? $compra?->data_emissao;

            $rows[] = [
                'compra' => $compra?->numero ?? '—',
                'data_entrada' => $dataEntrada?->format('d/m/Y') ?? '—',
                'fornecedor' => $compra?->fornecedor?->nome_razao ?? '—',
                'quantidade' => $this->qty((float) $item->quantidade),
                'valor' => $this->money((float) $item->valor_unitario),
                'total' => $this->money((float) $item->total),
                'total_raw' => (float) $item->total,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function nfeDocumentRows(Product $product, string $modelo): array
    {
        $rows = [];
        $documentKey = $modelo === '65' ? 'nfce' : 'nfe';

        foreach (
            NfeItem::query()
                ->with(['nfe.cliente', 'nfe.venda'])
                ->where('product_id', $product->id)
                ->whereHas('nfe', fn ($query) => $query->where('modelo', $modelo))
                ->orderByDesc('id')
                ->get() as $item
        ) {
            $nfe = $item->nfe;

            $rows[] = [
                $documentKey => (string) ($nfe?->id ?? '—'),
                'numero' => $nfe?->numero ?? '—',
                'venda' => $nfe?->venda?->numero ?? ($nfe?->npedido ?? '—'),
                'data_emissao' => $nfe?->data_emissao?->format('d/m/Y') ?? '—',
                'hora_emissao' => $this->hora($nfe?->hora_emissao),
                'cliente' => $nfe?->cliente?->nome_razao ?? '—',
                'quantidade' => $this->qty((float) $item->quantidade),
                'valor' => $this->money((float) $item->valor_unitario),
                'total' => $this->money((float) $item->total),
                'total_raw' => (float) $item->total,
            ];
        }

        return $rows;
    }

    protected function hora(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $value = (string) $value;

        if (preg_match('/^\d{2}:\d{2}/', $value, $matches)) {
            return substr($matches[0], 0, 5);
        }

        return $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function vendasRows(Product $product): array
    {
        $rows = [];

        foreach (
            VendaItem::query()
                ->with(['venda.cliente'])
                ->where('product_id', $product->id)
                ->whereHas(
                    'venda',
                    fn ($query) => $query->where('status', '!=', Venda::STATUS_CANCELADO),
                )
                ->join('vendas', 'venda_itens.venda_id', '=', 'vendas.id')
                ->orderByDesc('vendas.data')
                ->orderByDesc('venda_itens.id')
                ->select('venda_itens.*')
                ->get() as $item
        ) {
            $venda = $item->venda;

            $rows[] = [
                'venda' => $venda?->numero ?? '—',
                'data_emissao' => $venda?->data?->format('d/m/Y') ?? '—',
                'cliente' => $venda?->cliente?->nome_razao ?? '—',
                'quantidade' => $this->qty((float) $item->quantidade),
                'valor' => $this->money((float) $item->valor_item),
                'total' => $this->money((float) $item->total),
                'total_raw' => (float) $item->total,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, string>>
     */
    protected function stripRawKeys(array $rows): array
    {
        return array_map(static function (array $row): array {
            unset($row['total_raw']);

            return $row;
        }, $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function sumColumn(array $rows, string $key): float
    {
        return array_reduce(
            $rows,
            static fn (float $carry, array $row): float => $carry + (float) ($row[$key] ?? 0),
            0.0,
        );
    }

    protected function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    protected function qty(float $value): string
    {
        return number_format($value, 3, ',', '.');
    }
}
