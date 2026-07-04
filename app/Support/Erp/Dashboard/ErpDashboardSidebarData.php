<?php

namespace App\Support\Erp\Dashboard;

use App\Models\ContaReceber;
use App\Models\Product;
use App\Support\Erp\ErpMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ErpDashboardSidebarData
{
    /**
     * @return list<array<string, string>>
     */
    public static function boletosVencidos(int $limit = 4): array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())) {
                return [];
            }

            $rows = ContaReceber::query()
                ->with('cliente:id,nome_razao')
                ->where('saldo', '>', 0)
                ->where('forma', ContaReceber::FORMA_BOLETO)
                ->whereDate('vencimento', '<', Carbon::today()->toDateString())
                ->orderBy('vencimento')
                ->limit($limit)
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            return $rows->map(fn (ContaReceber $conta): array => [
                'cliente' => $conta->cliente?->nome_razao ?? 'Cliente',
                'valor' => 'R$ ' . ErpMoney::formatBr($conta->saldo),
                'vencimento' => $conta->vencimento?->format('d/m/Y') ?? '—',
            ])->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, string>>
     */
    public static function estoqueMinimo(int $limit = 4): array
    {
        try {
            if (! Schema::hasTable((new Product)->getTable())) {
                return [];
            }

            $rows = Product::query()
                ->estoqueCritico()
                ->orderBy('estoque')
                ->limit($limit)
                ->get(['descricao', 'estoque', 'estoque_minimo']);

            if ($rows->isEmpty()) {
                return [];
            }

            return $rows->map(fn (Product $product): array => [
                'produto' => $product->descricao,
                'atual' => ErpMoney::formatBr($product->estoque, 0),
                'minimo' => ErpMoney::formatBr($product->estoque_minimo, 0),
            ])->all();
        } catch (Throwable) {
            return [];
        }
    }
}
