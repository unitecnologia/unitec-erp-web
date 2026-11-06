<?php

namespace App\Support\ForcaVendas;

use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use App\Models\OrdemServico;
use App\Support\Erp\Orcamento\OrcamentoFaturamentoGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Vínculo Tela de Venda ↔ orçamento via forca_vendas_orders.orcamento_id
 * (coluna já existente — sem migration).
 *
 * Ciclo:
 * - F2: só carrega na venda (status permanece FECHADO no banco).
 * - F3 / Sair com gravação / F10: valida elegibilidade, grava orcamento_id e marca IMPORTADO.
 * - Cancelamento de DAV pendente: devolve FECHADO se não houver outra utilização válida.
 * - Faturamento: mantém IMPORTADO.
 */
final class ForcaVendasOrcamentoVinculo
{
    /**
     * Gravação do DAV: revalida elegibilidade com lock e marca IMPORTADO.
     * Deve rodar dentro da mesma transação que cria o forca_vendas_orders.
     *
     * @throws RuntimeException se o orçamento não puder ser vinculado
     */
    public function vincularAoGravarDav(int $orcamentoId, ?int $ignorarOrderId = null): void
    {
        if ($orcamentoId <= 0) {
            return;
        }

        $orcamento = Orcamento::query()->whereKey($orcamentoId)->lockForUpdate()->first();

        if (! $orcamento) {
            throw new RuntimeException('Orçamento não encontrado para vínculo com o DAV.');
        }

        $motivoFaturado = OrcamentoFaturamentoGuard::motivoFaturado($orcamentoId);

        if ($motivoFaturado !== null) {
            throw new RuntimeException('Orçamento indisponível para importação. '.$motivoFaturado);
        }

        if ($this->temUtilizacaoValida($orcamentoId, $ignorarOrderId)) {
            throw new RuntimeException(
                'Este orçamento já está vinculado a outro documento e não pode ser importado.'
            );
        }

        $statusOk = in_array($orcamento->status, [
            Orcamento::STATUS_FECHADO,
            Orcamento::STATUS_IMPORTADO,
        ], true);

        if (! $statusOk) {
            throw new RuntimeException('Orçamento indisponível para importação.');
        }

        // IMPORTADO sem utilização válida (órfão legado) pode ser reaproveitado.
        if ($orcamento->status !== Orcamento::STATUS_IMPORTADO) {
            $orcamento->update(['status' => Orcamento::STATUS_IMPORTADO]);
        }
    }

    /**
     * Compat: alias do vínculo na gravação do DAV.
     *
     * @throws RuntimeException
     */
    public function garantirImportadoAoGravarDav(int $orcamentoId, ?int $ignorarOrderId = null): void
    {
        $this->vincularAoGravarDav($orcamentoId, $ignorarOrderId);
    }

    /**
     * Limpeza defensiva de IMPORTADO órfão (legado F2 precoce).
     * No fluxo atual o F2 não altera status — abandôno sem gravação já deixa FECHADO.
     */
    public function devolverSeImportacaoNaoGravada(int $orcamentoId): void
    {
        if ($orcamentoId <= 0) {
            return;
        }

        $this->devolverParaFechadoSeLivre($orcamentoId, ignorarOrderId: null);
    }

    /**
     * Cancelamento de DAV: devolve FECHADO se não houver outra utilização válida.
     */
    public function devolverAposCancelarDav(int $orcamentoId, int $orderIdCancelado): void
    {
        if ($orcamentoId <= 0 || $orderIdCancelado <= 0) {
            return;
        }

        $this->devolverParaFechadoSeLivre($orcamentoId, ignorarOrderId: $orderIdCancelado);
    }

    private function devolverParaFechadoSeLivre(int $orcamentoId, ?int $ignorarOrderId): void
    {
        DB::transaction(function () use ($orcamentoId, $ignorarOrderId): void {
            $orcamento = Orcamento::query()->whereKey($orcamentoId)->lockForUpdate()->first();

            if (! $orcamento || $orcamento->status !== Orcamento::STATUS_IMPORTADO) {
                return;
            }

            if ($this->temUtilizacaoValida($orcamentoId, $ignorarOrderId)) {
                return;
            }

            $orcamento->update(['status' => Orcamento::STATUS_FECHADO]);
        });
    }

    /**
     * Outro DAV FV ativo, PDV, OS ou NF-e faturada — sem inferir por cliente/valor/itens.
     */
    private function temUtilizacaoValida(int $orcamentoId, ?int $ignorarOrderId): bool
    {
        $fvAtivo = ForcaVendasOrder::query()
            ->where('orcamento_id', $orcamentoId)
            ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO)
            ->where('situacao', '!=', ForcaVendasOrder::SITUACAO_CANCELADO)
            ->when(
                $ignorarOrderId !== null && $ignorarOrderId > 0,
                fn ($q) => $q->where('id', '!=', $ignorarOrderId)
            )
            ->exists();

        if ($fvAtivo) {
            return true;
        }

        if (Schema::hasColumn('pdv_vendas', 'orcamento_id')) {
            $pdv = DB::table('pdv_vendas')
                ->where('orcamento_id', $orcamentoId)
                ->where(fn ($q) => $q->whereNull('situacao')->orWhere('situacao', '!=', 'C'))
                ->exists();

            if ($pdv) {
                return true;
            }
        }

        if (Schema::hasColumn('ordens_servico', 'orcamento_id')) {
            $os = DB::table('ordens_servico')
                ->where('orcamento_id', $orcamentoId)
                ->where(fn ($q) => $q->whereNull('situacao')->orWhere('situacao', '!=', OrdemServico::SITUACAO_CANCELADA))
                ->exists();

            if ($os) {
                return true;
            }
        }

        if (OrcamentoFaturamentoGuard::temVinculoNfe()) {
            $nfe = DB::table('nfe_orcamentos')
                ->join('nfes', 'nfes.id', '=', 'nfe_orcamentos.nfe_id')
                ->where('nfe_orcamentos.orcamento_id', $orcamentoId)
                ->whereIn('nfes.status', OrcamentoFaturamentoGuard::NFE_STATUS_FATURADO)
                ->exists();

            if ($nfe) {
                return true;
            }
        }

        return false;
    }
}
