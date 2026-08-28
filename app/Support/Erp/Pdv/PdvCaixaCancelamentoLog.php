<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvCaixaSessao;
use App\Support\Erp\ErpTimezone;

/**
 * Registra itens removidos do cupom na sessão de caixa (para resumo/fechamento).
 */
final class PdvCaixaCancelamentoLog
{
    /**
     * @param  array<string, mixed>  $item  Linha do cupom (codigo, descricao, quantidade, total, …)
     */
    public function registrarItem(PdvCaixaSessao $sessao, array $item): void
    {
        $this->registrarItens($sessao, [$item]);
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     */
    public function registrarItens(PdvCaixaSessao $sessao, array $itens): void
    {
        if ($itens === []) {
            return;
        }

        $lista = is_array($sessao->itens_cancelados) ? $sessao->itens_cancelados : [];
        $agora = ErpTimezone::toLocal()->format('Y-m-d H:i:s');

        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }

            $lista[] = [
                'codigo' => (string) ($item['codigo'] ?? ''),
                'descricao' => (string) ($item['descricao'] ?? ''),
                'qtd' => round((float) ($item['quantidade'] ?? $item['qtd'] ?? 0), 3),
                'total' => round((float) ($item['total'] ?? 0), 2),
                'em' => $agora,
            ];
        }

        $sessao->forceFill(['itens_cancelados' => array_values($lista)])->save();
    }
}
