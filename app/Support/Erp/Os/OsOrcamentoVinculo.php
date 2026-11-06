<?php

namespace App\Support\Erp\Os;

use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use App\Models\OrdemServico;
use App\Support\Erp\Orcamento\OrcamentoFaturamentoGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Vínculo OS ↔ orçamento via ordens_servico.orcamento_id (coluna existente).
 *
 * Ciclo (independente da Tela de Venda):
 * - Importar na tela: status permanece FECHADO.
 * - Gravar OS nova com vínculo: valida elegibilidade e marca IMPORTADO na mesma TX.
 * - Cancelar OS: devolve FECHADO se não houver outra utilização válida.
 * - Edição com o mesmo orçamento: não reprocessa importação.
 */
final class OsOrcamentoVinculo
{
    /**
     * Nova vinculação (OS nova ou troca de orçamento).
     * Deve rodar dentro da TX que persiste a OS.
     *
     * @param  int|null  $ignorarOsId  OS atual (edição) — não conta como “outro uso”
     *
     * @throws RuntimeException
     */
    public function vincularAoGravarOs(int $orcamentoId, ?int $ignorarOsId = null): void
    {
        if ($orcamentoId <= 0) {
            return;
        }

        $orcamento = Orcamento::query()->whereKey($orcamentoId)->lockForUpdate()->first();

        if (! $orcamento) {
            throw new RuntimeException('Orçamento não encontrado para vínculo com a OS.');
        }

        if ($orcamento->status === Orcamento::STATUS_CANCELADO) {
            throw new RuntimeException('Orçamento cancelado não pode ser importado.');
        }

        if ($this->temUtilizacaoValida($orcamentoId, $ignorarOsId)) {
            throw new RuntimeException(
                'Este orçamento já está vinculado a outro documento e não pode ser importado.'
            );
        }

        // FECHADO (fluxo normal) ou IMPORTADO órfão (sem utilização válida).
        if (! in_array($orcamento->status, [Orcamento::STATUS_FECHADO, Orcamento::STATUS_IMPORTADO], true)) {
            throw new RuntimeException('Orçamento indisponível para importação.');
        }

        if ($orcamento->status !== Orcamento::STATUS_IMPORTADO) {
            $orcamento->update(['status' => Orcamento::STATUS_IMPORTADO]);
        }
    }

    /**
     * Após cancelar OS: devolve FECHADO se não houver outro uso válido.
     * Mantém orcamento_id na OS cancelada (auditoria).
     */
    public function devolverAposCancelarOs(int $orcamentoId, int $osIdCancelada): void
    {
        if ($orcamentoId <= 0 || $osIdCancelada <= 0) {
            return;
        }

        $this->devolverParaFechadoSeLivre($orcamentoId, $osIdCancelada);
    }

    /**
     * Reabertura de OS que já possui orcamento_id: restaura IMPORTADO sem nova elegibilidade.
     * (Cancelamento pode ter devolvido FECHADO; o vínculo histórico permanece.)
     */
    public function garantirImportadoAoReabrirOs(int $orcamentoId): void
    {
        if ($orcamentoId <= 0) {
            return;
        }

        $orcamento = Orcamento::query()->whereKey($orcamentoId)->lockForUpdate()->first();

        if (! $orcamento || $orcamento->status !== Orcamento::STATUS_FECHADO) {
            return;
        }

        $orcamento->update(['status' => Orcamento::STATUS_IMPORTADO]);
    }

    /**
     * Liberação quando a OS deixa de apontar para o orçamento (troca/remoção).
     */
    public function devolverSeLivre(int $orcamentoId, ?int $ignorarOsId = null): void
    {
        if ($orcamentoId <= 0) {
            return;
        }

        $this->devolverParaFechadoSeLivre($orcamentoId, $ignorarOsId);
    }

    private function devolverParaFechadoSeLivre(int $orcamentoId, ?int $ignorarOsId): void
    {
        $orcamento = Orcamento::query()->whereKey($orcamentoId)->lockForUpdate()->first();

        if (! $orcamento || $orcamento->status !== Orcamento::STATUS_IMPORTADO) {
            return;
        }

        if ($this->temUtilizacaoValida($orcamentoId, $ignorarOsId)) {
            return;
        }

        $orcamento->update(['status' => Orcamento::STATUS_FECHADO]);
    }

    /**
     * DAV FV ativo, PDV, outra OS não cancelada ou NF-e faturada.
     */
    private function temUtilizacaoValida(int $orcamentoId, ?int $ignorarOsId): bool
    {
        $fvAtivo = ForcaVendasOrder::query()
            ->where('orcamento_id', $orcamentoId)
            ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO)
            ->where('situacao', '!=', ForcaVendasOrder::SITUACAO_CANCELADO)
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
                ->when(
                    $ignorarOsId !== null && $ignorarOsId > 0,
                    fn ($q) => $q->where('id', '!=', $ignorarOsId)
                )
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
