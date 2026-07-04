<?php

namespace App\Support\VendasInternas;

use App\Models\VendasInternasOrder;

final class VendasInternasPdvHookService
{
    public function onOrcamentoImportado(int $orcamentoId): void
    {
        VendasInternasOrder::query()
            ->where('orcamento_id', $orcamentoId)
            ->where('situacao', VendasInternasOrder::SITUACAO_AGUARDANDO)
            ->update(['situacao' => VendasInternasOrder::SITUACAO_NO_CAIXA]);
    }

    public function onVendaPdvFinalizada(?int $orcamentoId, int $vendaId): void
    {
        if ($orcamentoId === null || $orcamentoId <= 0) {
            return;
        }

        VendasInternasOrder::query()
            ->where('orcamento_id', $orcamentoId)
            ->whereIn('situacao', [
                VendasInternasOrder::SITUACAO_AGUARDANDO,
                VendasInternasOrder::SITUACAO_NO_CAIXA,
            ])
            ->update([
                'venda_id' => $vendaId,
                'situacao' => VendasInternasOrder::SITUACAO_PAGO,
                'pago_at' => now(),
            ]);
    }
}
