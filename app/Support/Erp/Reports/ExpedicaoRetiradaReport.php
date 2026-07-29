<?php

namespace App\Support\Erp\Reports;

use App\Models\Entrega;
use App\Models\EntregaItem;

final class ExpedicaoRetiradaReport
{
    /**
     * @return list<array{codigo: string, descricao: string, quantidade: float}>
     */
    public static function buildLinhas(Entrega $entrega): array
    {
        if (! $entrega->relationLoaded('itens')) {
            $entrega->loadMissing('itens');
        }

        $linhas = [];

        foreach ($entrega->itens as $item) {
            $quantidade = (float) $item->quantidade_expedida;

            if ($quantidade <= 0) {
                continue;
            }

            $linhas[] = [
                'codigo' => (string) ($item->codigo ?? '—'),
                'descricao' => (string) $item->descricao,
                'quantidade' => $quantidade,
            ];
        }

        return $linhas;
    }

    public static function formatNumeroPedido(Entrega $entrega): string
    {
        $numero = ltrim((string) ($entrega->venda?->numero ?? $entrega->numero), '0');

        return $numero !== '' ? $numero : '0';
    }

    public static function formatQuantidade(float $quantidade): string
    {
        return fmod($quantidade, 1.0) === 0.0
            ? number_format($quantidade, 0, ',', '.')
            : number_format($quantidade, 2, ',', '.');
    }

    public static function totalQuantidade(Entrega $entrega): float
    {
        return $entrega->itens->sum(
            fn (EntregaItem $item): float => (float) $item->quantidade_expedida,
        );
    }
}
