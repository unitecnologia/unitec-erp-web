<?php

namespace App\Support\Erp\Product;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Support\Facades\Auth;

/**
 * Registra alteração de preços no histórico do produto (digitação / compra).
 * `ultimo_preco` = varejo; também grava custo, atacado e especial.
 */
final class ProductPriceHistoryRecorder
{
    public const FORMA_DIGITACAO = 'digitacao';

    public const FORMA_COMPRA = 'compra';

    /**
     * @return array<string, string>
     */
    public static function formaLabels(): array
    {
        return [
            self::FORMA_DIGITACAO => 'Digitação',
            self::FORMA_COMPRA => 'Compra',
        ];
    }

    public static function formaLabel(?string $forma): string
    {
        if ($forma === null || $forma === '') {
            return '—';
        }

        return self::formaLabels()[$forma] ?? $forma;
    }

    /**
     * Grava histórico se custo ou algum preço de venda mudou.
     */
    public function recordSalePricesIfChanged(
        Product $product,
        string $forma,
        ?float $varejoAnterior = null,
        ?float $atacadoAnterior = null,
        ?float $especialAnterior = null,
        ?float $custoAnterior = null,
        ?float $varejoNovo = null,
        ?float $atacadoNovo = null,
        ?float $especialNovo = null,
        ?float $custoNovo = null,
        ?string $usuario = null,
    ): void {
        $varejo = round($varejoNovo ?? (float) $product->preco_venda, 2);
        $atacado = round($atacadoNovo ?? (float) $product->preco_atacado, 2);
        $especial = round($especialNovo ?? (float) $product->preco_especial, 2);
        $custo = round($custoNovo ?? (float) $product->preco_custo, 2);

        if ($varejo <= 0 && $atacado <= 0 && $especial <= 0 && $custo <= 0) {
            return;
        }

        $prevVarejo = $varejoAnterior !== null ? round($varejoAnterior, 2) : null;
        $prevAtacado = $atacadoAnterior !== null ? round($atacadoAnterior, 2) : null;
        $prevEspecial = $especialAnterior !== null ? round($especialAnterior, 2) : null;
        $prevCusto = $custoAnterior !== null ? round($custoAnterior, 2) : null;

        $mudou = false;

        if ($prevVarejo === null || $prevVarejo !== $varejo) {
            $mudou = true;
        }
        if ($prevAtacado === null || $prevAtacado !== $atacado) {
            $mudou = true;
        }
        if ($prevEspecial === null || $prevEspecial !== $especial) {
            $mudou = true;
        }
        if ($prevCusto === null || $prevCusto !== $custo) {
            $mudou = true;
        }

        // Se todos os anteriores foram informados e nada mudou, não grava.
        if (
            $prevVarejo !== null
            && $prevAtacado !== null
            && $prevEspecial !== null
            && $prevCusto !== null
            && $prevVarejo === $varejo
            && $prevAtacado === $atacado
            && $prevEspecial === $especial
            && $prevCusto === $custo
        ) {
            return;
        }

        // Compat: só varejo anterior informado e igual → não grava (fluxo antigo).
        if (
            $prevVarejo !== null
            && $prevAtacado === null
            && $prevEspecial === null
            && $prevCusto === null
            && $prevVarejo === $varejo
        ) {
            return;
        }

        if (! $mudou && $prevVarejo !== null) {
            return;
        }

        ProductPriceHistory::query()->create([
            'product_id' => (int) $product->id,
            'ultimo_preco' => $varejo,
            'preco_custo' => $custo,
            'preco_atacado' => $atacado,
            'preco_especial' => $especial,
            'registrado_em' => now()->toDateString(),
            'usuario' => $usuario ?? (Auth::user()?->name ?? 'Sistema'),
            'forma_alteracao' => $forma,
        ]);
    }

    /**
     * @deprecated Use recordSalePricesIfChanged — mantido para chamadas antigas (só varejo).
     */
    public function recordIfChanged(
        Product $product,
        float $novoPreco,
        string $forma,
        ?float $precoAnterior = null,
        ?string $usuario = null,
    ): void {
        $this->recordSalePricesIfChanged(
            product: $product,
            forma: $forma,
            varejoAnterior: $precoAnterior,
            varejoNovo: $novoPreco,
            atacadoNovo: (float) $product->preco_atacado,
            especialNovo: (float) $product->preco_especial,
            custoNovo: (float) $product->preco_custo,
            usuario: $usuario,
        );
    }
}
