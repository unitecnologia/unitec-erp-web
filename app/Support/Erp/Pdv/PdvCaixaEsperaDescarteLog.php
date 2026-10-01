<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvCaixaSessao;
use App\Models\PdvVendaEspera;
use App\Support\Erp\ErpTimezone;

/**
 * Registra vendas em espera descartadas na sessão (resumo/fechamento).
 */
final class PdvCaixaEsperaDescarteLog
{
    public function registrar(PdvCaixaSessao $sessao, PdvVendaEspera $espera, string $motivo = 'DESCARTADA'): void
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            $motivo = 'DESCARTADA';
        }

        $decoded = json_decode((string) $espera->snapshot, true);
        $cupomItens = is_array($decoded) && is_array($decoded['cupom_itens'] ?? null)
            ? $decoded['cupom_itens']
            : [];
        $itens = count($cupomItens);
        if ($itens <= 0) {
            $itens = (int) ($espera->qtd_itens ?? 0);
        }

        $lista = is_array($sessao->vendas_espera_descartadas) ? $sessao->vendas_espera_descartadas : [];

        $lista[] = [
            'numero' => str_pad((string) ($espera->sequencia ?: $espera->id), 4, '0', STR_PAD_LEFT),
            'cliente' => mb_strtoupper(trim((string) ($espera->cliente_nome ?: 'CONSUMIDOR FINAL')), 'UTF-8'),
            'itens' => $itens,
            'total' => round((float) $espera->total, 2),
            'motivo' => mb_strtoupper($motivo, 'UTF-8'),
            'em' => ErpTimezone::toLocal()->format('Y-m-d H:i:s'),
        ];

        $sessao->forceFill(['vendas_espera_descartadas' => array_values($lista)])->save();
    }
}
