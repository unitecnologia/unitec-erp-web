<?php

namespace App\Support\Erp;

use App\Models\Estoque;
use App\Models\Vendedor;

/**
 * Estoque operacional do vendedor — mesma regra da Tela de Venda
 * ({@see \App\Filament\Pages\ForcaVendasTelaVendaPage::carregarEstoqueDoVendedor}).
 */
final class VendedorEstoque
{
    /**
     * @return array{id: int|null, label: string}
     */
    public static function fromVendedor(Vendedor $vendedor): array
    {
        $estoque = $vendedor->estoque_id
            ? ($vendedor->relationLoaded('estoqueCadastro')
                ? $vendedor->estoqueCadastro
                : Estoque::query()->find($vendedor->estoque_id))
            : null;

        if (! $estoque) {
            return ['id' => null, 'label' => ''];
        }

        return [
            'id' => (int) $estoque->id,
            'label' => $estoque->label(),
        ];
    }

    /**
     * @return array{id: int|null, label: string}
     */
    public static function fromVendedorId(?int $vendedorId): array
    {
        if ($vendedorId === null || $vendedorId <= 0) {
            return ['id' => null, 'label' => ''];
        }

        $vendedor = Vendedor::query()->find($vendedorId, ['id', 'estoque_id']);

        if (! $vendedor) {
            return ['id' => null, 'label' => ''];
        }

        return self::fromVendedor($vendedor);
    }
}
