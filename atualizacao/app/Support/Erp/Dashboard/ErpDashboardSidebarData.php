<?php

namespace App\Support\Erp\Dashboard;

use App\Models\ContaPagar;
use App\Models\Product;
use App\Support\Erp\ErpMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ErpDashboardSidebarData
{
    /**
     * Títulos a pagar vencidos (saldo > 0 e vencimento &lt; hoje).
     *
     * @return array{total: int, items: list<array<string, string>>}
     */
    public static function contasPagarVencidas(int $limit = 50): array
    {
        try {
            if (! Schema::hasTable((new ContaPagar)->getTable())) {
                return ['total' => 0, 'items' => []];
            }

            $hoje = Carbon::today()->toDateString();

            $base = ContaPagar::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<', $hoje);

            $total = (int) (clone $base)->count();

            if ($total === 0) {
                return ['total' => 0, 'items' => []];
            }

            $rows = (clone $base)
                ->with('fornecedor:id,nome_razao')
                ->orderBy('vencimento')
                ->limit($limit)
                ->get();

            return [
                'total' => $total,
                'items' => $rows->map(fn (ContaPagar $conta): array => [
                    'fornecedor' => $conta->fornecedor?->nome_razao ?? 'Fornecedor',
                    'valor' => 'R$ ' . ErpMoney::formatBr($conta->saldo),
                    'vencimento' => $conta->vencimento?->format('d/m/Y') ?? '—',
                ])->all(),
            ];
        } catch (Throwable) {
            return ['total' => 0, 'items' => []];
        }
    }

    /**
     * Produtos abaixo do estoque mínimo.
     *
     * @return array{total: int, items: list<array<string, string>>}
     */
    public static function estoqueMinimo(int $limit = 50): array
    {
        try {
            if (! Schema::hasTable((new Product)->getTable())) {
                return ['total' => 0, 'items' => []];
            }

            $base = Product::query()->estoqueCritico();

            $total = (int) (clone $base)->count();

            if ($total === 0) {
                return ['total' => 0, 'items' => []];
            }

            $rows = (clone $base)
                ->orderBy('estoque')
                ->limit($limit)
                ->get(['descricao', 'estoque', 'estoque_minimo']);

            return [
                'total' => $total,
                'items' => $rows->map(fn (Product $product): array => [
                    'produto' => $product->descricao,
                    'atual' => ErpMoney::formatBr($product->estoque, 0),
                    'minimo' => ErpMoney::formatBr($product->estoque_minimo, 0),
                ])->all(),
            ];
        } catch (Throwable) {
            return ['total' => 0, 'items' => []];
        }
    }
}
