<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Nfe;
use App\Models\Orcamento;
use App\Models\OrdemServico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regra única de "orçamento já faturado" para impedir faturamento duplicado entre
 * PDV (F3), NF-e (importar orçamento) e OS.
 *
 * Faturamento válido:
 * - status importado (venda PDV / Tela de Venda) ou cancelado;
 * - venda PDV finalizada e não estornada (situacao != 'C') com o orçamento;
 * - OS não cancelada gerada a partir dele;
 * - NF-e vinculada (nfe_orcamentos) transmitida, em contingência ou duplicidade.
 *   NF-e aberta (rascunho ou rejeitada), cancelada, denegada ou inutilizada não bloqueia.
 */
final class OrcamentoFaturamentoGuard
{
    public const STATUS_BLOQUEADOS = [Orcamento::STATUS_IMPORTADO, Orcamento::STATUS_CANCELADO];

    public const NFE_STATUS_FATURADO = [Nfe::STATUS_TRANSMITIDA, Nfe::STATUS_CONTINGENCIA, Nfe::STATUS_DUPLICIDADE];

    /**
     * @param  Builder<Orcamento>  $query
     * @return Builder<Orcamento>
     */
    public static function aplicarNaoFaturados(Builder $query, ?int $ignorarNfeId = null): Builder
    {
        $query->where(function (Builder $q): void {
            $q->whereNull('orcamentos.status')->orWhereNotIn('orcamentos.status', self::STATUS_BLOQUEADOS);
        });

        if (Schema::hasColumn('pdv_vendas', 'orcamento_id')) {
            $query->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('pdv_vendas')
                    ->whereColumn('pdv_vendas.orcamento_id', 'orcamentos.id')
                    ->where(function ($s): void {
                        $s->whereNull('pdv_vendas.situacao')->orWhere('pdv_vendas.situacao', '!=', 'C');
                    });
            });
        }

        if (Schema::hasColumn('ordens_servico', 'orcamento_id')) {
            $query->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('ordens_servico')
                    ->whereColumn('ordens_servico.orcamento_id', 'orcamentos.id')
                    ->where(function ($s): void {
                        $s->whereNull('ordens_servico.situacao')
                            ->orWhere('ordens_servico.situacao', '!=', OrdemServico::SITUACAO_CANCELADA);
                    });
            });
        }

        if (self::temVinculoNfe()) {
            $query->whereNotExists(function ($sub) use ($ignorarNfeId): void {
                $sub->selectRaw('1')
                    ->from('nfe_orcamentos')
                    ->join('nfes', 'nfes.id', '=', 'nfe_orcamentos.nfe_id')
                    ->whereColumn('nfe_orcamentos.orcamento_id', 'orcamentos.id')
                    ->whereIn('nfes.status', self::NFE_STATUS_FATURADO);

                if ($ignorarNfeId) {
                    $sub->where('nfes.id', '!=', $ignorarNfeId);
                }
            });
        }

        return $query;
    }

    /**
     * Motivo legível quando o orçamento já foi faturado; null se ainda pode ser faturado.
     */
    public static function motivoFaturado(int $orcamentoId, ?int $ignorarNfeId = null): ?string
    {
        $orcamento = Orcamento::query()->find($orcamentoId);

        if (! $orcamento) {
            return 'Orçamento não encontrado.';
        }

        $rotulo = 'Orçamento Nº '.$orcamento->numero;

        if ($orcamento->status === Orcamento::STATUS_CANCELADO) {
            return $rotulo.' está cancelado.';
        }

        if ($orcamento->status === Orcamento::STATUS_IMPORTADO) {
            return $rotulo.' já foi faturado (importado em venda).';
        }

        if (Schema::hasColumn('pdv_vendas', 'orcamento_id')) {
            $pdv = DB::table('pdv_vendas')
                ->where('orcamento_id', $orcamentoId)
                ->where(fn ($q) => $q->whereNull('situacao')->orWhere('situacao', '!=', 'C'))
                ->value('numero');

            if ($pdv !== null) {
                return $rotulo.' já foi faturado no PDV (venda Nº '.$pdv.').';
            }
        }

        if (Schema::hasColumn('ordens_servico', 'orcamento_id')) {
            $os = DB::table('ordens_servico')
                ->where('orcamento_id', $orcamentoId)
                ->where(fn ($q) => $q->whereNull('situacao')->orWhere('situacao', '!=', OrdemServico::SITUACAO_CANCELADA))
                ->value('numero');

            if ($os !== null) {
                return $rotulo.' já foi convertido na OS Nº '.$os.'.';
            }
        }

        if (self::temVinculoNfe()) {
            $nfe = DB::table('nfe_orcamentos')
                ->join('nfes', 'nfes.id', '=', 'nfe_orcamentos.nfe_id')
                ->where('nfe_orcamentos.orcamento_id', $orcamentoId)
                ->whereIn('nfes.status', self::NFE_STATUS_FATURADO)
                ->when($ignorarNfeId, fn ($q) => $q->where('nfes.id', '!=', $ignorarNfeId))
                ->value('nfes.numero');

            if ($nfe !== null) {
                return $rotulo.' já foi faturado na NF-e Nº '.$nfe.'.';
            }
        }

        return null;
    }

    public static function temVinculoNfe(): bool
    {
        return Schema::hasTable('nfe_orcamentos');
    }

    /**
     * Após mudança de status da NF-e: emissão válida marca os orçamentos vinculados como
     * faturados (importado); cancelamento/denegação/inutilização devolve o status anterior,
     * se não houver outro faturamento (PDV, OS ou outra NF-e válida).
     */
    public static function sincronizarComNfe(Nfe $nfe): void
    {
        if (! $nfe->wasChanged('status') || ! self::temVinculoNfe()) {
            return;
        }

        $valida = in_array($nfe->status, self::NFE_STATUS_FATURADO, true);
        $liberar = in_array($nfe->status, [Nfe::STATUS_CANCELADA, Nfe::STATUS_DENEGADA, Nfe::STATUS_INUTILIZADA], true);

        if (! $valida && ! $liberar) {
            return;
        }

        $orcamentoIds = DB::table('nfe_orcamentos')->where('nfe_id', $nfe->id)->pluck('orcamento_id');

        foreach ($orcamentoIds as $orcamentoId) {
            DB::transaction(function () use ($nfe, $orcamentoId, $valida): void {
                $orcamento = Orcamento::query()->whereKey($orcamentoId)->lockForUpdate()->first();

                if (! $orcamento) {
                    return;
                }

                $vinculo = DB::table('nfe_orcamentos')
                    ->where('nfe_id', $nfe->id)
                    ->where('orcamento_id', $orcamento->id);

                if ($valida) {
                    if ($orcamento->status !== Orcamento::STATUS_IMPORTADO) {
                        $vinculo->update(['status_anterior' => $orcamento->status ?: Orcamento::STATUS_FECHADO, 'updated_at' => now()]);
                        $orcamento->update(['status' => Orcamento::STATUS_IMPORTADO]);
                    }

                    return;
                }

                $statusAnterior = $vinculo->value('status_anterior');

                if ($statusAnterior === null || $orcamento->status !== Orcamento::STATUS_IMPORTADO) {
                    return;
                }

                if (self::possuiOutroFaturamento((int) $orcamento->id, (int) $nfe->id)) {
                    return;
                }

                $orcamento->update(['status' => $statusAnterior]);
                $vinculo->update(['status_anterior' => null, 'updated_at' => now()]);
            });
        }
    }

    /**
     * Faturamento por PDV, OS ou outra NF-e válida (ignora o status do orçamento).
     */
    private static function possuiOutroFaturamento(int $orcamentoId, int $ignorarNfeId): bool
    {
        if (Schema::hasColumn('pdv_vendas', 'orcamento_id')
            && DB::table('pdv_vendas')
                ->where('orcamento_id', $orcamentoId)
                ->where(fn ($q) => $q->whereNull('situacao')->orWhere('situacao', '!=', 'C'))
                ->exists()) {
            return true;
        }

        if (Schema::hasColumn('ordens_servico', 'orcamento_id')
            && DB::table('ordens_servico')
                ->where('orcamento_id', $orcamentoId)
                ->where(fn ($q) => $q->whereNull('situacao')->orWhere('situacao', '!=', OrdemServico::SITUACAO_CANCELADA))
                ->exists()) {
            return true;
        }

        return DB::table('nfe_orcamentos')
            ->join('nfes', 'nfes.id', '=', 'nfe_orcamentos.nfe_id')
            ->where('nfe_orcamentos.orcamento_id', $orcamentoId)
            ->where('nfes.id', '!=', $ignorarNfeId)
            ->whereIn('nfes.status', self::NFE_STATUS_FATURADO)
            ->exists();
    }
}
