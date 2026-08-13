<?php

namespace App\Support\Erp\Financeiro;

use App\Models\ContaReceber;
use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use App\Models\PdvVenda;
use App\Models\Venda;

final class ContaReceberExclusaoService
{
    public function podeExcluir(ContaReceber $conta): bool
    {
        return $this->motivoBloqueio($conta) === null;
    }

    public function motivoBloqueio(ContaReceber $conta): ?string
    {
        $origem = $this->origemVinculo($conta);

        if ($origem === null) {
            return null;
        }

        return match ($origem) {
            'forca_vendas' => 'Conta vinculada a pedido da Força de Vendas.',
            'pdv' => 'Conta vinculada a venda do PDV.',
            'venda' => 'Conta vinculada a pedido de venda.',
            'orcamento' => 'Conta vinculada a orçamento.',
            default => 'Conta vinculada a outro lançamento do sistema.',
        };
    }

    private function origemVinculo(ContaReceber $conta): ?string
    {
        $documento = trim((string) ($conta->documento ?? ''));

        if ($documento !== '') {
            if (preg_match('/^FV-(\d+)/', $documento, $matches)) {
                return ForcaVendasOrder::query()->whereKey((int) $matches[1])->exists()
                    ? 'forca_vendas'
                    : null;
            }

            if (preg_match('/^PDV-(\d+)$/', $documento, $matches)) {
                return PdvVenda::query()->where('numero', (int) $matches[1])->exists()
                    ? 'pdv'
                    : null;
            }

            if (preg_match('/^(?:VD|VENDA)\s*0*(\d+)/i', $documento, $matches)) {
                return Venda::query()
                    ->where('numero', str_pad($matches[1], 6, '0', STR_PAD_LEFT))
                    ->exists()
                    ? 'venda'
                    : null;
            }

            if (preg_match('/^(?:ORC|ORÇAMENTO)\s*0*(\d+)/iu', $documento, $matches)) {
                return Orcamento::query()
                    ->where('numero', str_pad($matches[1], 6, '0', STR_PAD_LEFT))
                    ->exists()
                    ? 'orcamento'
                    : null;
            }
        }

        $historico = mb_strtoupper(trim((string) ($conta->historico ?? '')), 'UTF-8');

        if (preg_match('/VENDA\s+PDV\s*#?\s*(\d+)/', $historico, $matches)) {
            return PdvVenda::query()->where('numero', (int) $matches[1])->exists()
                ? 'pdv'
                : null;
        }

        return null;
    }
}
