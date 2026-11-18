<?php

namespace App\Support\Erp\Tef;

use App\Models\PdvVenda;
use App\Models\TefIntencao;
use App\Models\TefLiquidacaoDestino;
use App\Models\TefLiquidacaoLote;
use App\Models\TefLiquidacaoLoteItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * C5B.2 — Montagem e manutenção de lotes de liquidação SuperTEF (somente controle).
 * Não cria CR, caixa, depósito nem altera status das intenções.
 */
final class TefLiquidacaoLoteService
{
    public const MAX_ITENS_POR_LOTE = 200;

    public function __construct(
        private readonly TefLiquidacaoDestinoResolver $destinoResolver,
    ) {
    }

    /**
     * Intenções elegíveis para compor lote (paginável pelo chamador).
     *
     * @param  array{
     *   terminal_id: int,
     *   pago_de?: string|null,
     *   pago_ate?: string|null,
     *   acquirer_cnpj?: string|null,
     *   somente_sem_acquirer?: bool
     * }  $filtros
     * @return Builder<TefIntencao>
     */
    public function queryCandidatas(int $empresaId, array $filtros): Builder
    {
        $terminalId = (int) ($filtros['terminal_id'] ?? 0);
        if ($empresaId <= 0 || $terminalId <= 0) {
            return TefIntencao::query()->whereRaw('1 = 0');
        }

        $cnpjFiltro = TefLiquidacaoDestino::normalizeAcquirerCnpj($filtros['acquirer_cnpj'] ?? null);
        $somenteSem = (bool) ($filtros['somente_sem_acquirer'] ?? false);

        $query = TefIntencao::query()
            ->where('empresa_id', $empresaId)
            ->where('terminal_id', $terminalId)
            ->where('status', TefIntencao::STATUS_PAGA)
            ->whereNotNull('pdv_venda_id')
            ->where('pdv_venda_id', '>', 0)
            ->where(function (Builder $q): void {
                $q->whereNull('requer_reconciliacao')
                    ->orWhere('requer_reconciliacao', false);
            })
            ->whereHas('pdvVenda', function (Builder $q): void {
                // F = fechada; C = cancelada/estornada no PDV.
                $q->where('situacao', '!=', 'C');
            })
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('tef_liquidacao_lote_itens as i')
                    ->join('tef_liquidacao_lotes as l', 'l.id', '=', 'i.lote_id')
                    ->whereColumn('i.tef_intencao_id', 'tef_intencoes.id')
                    ->whereIn('l.situacao', [
                        TefLiquidacaoLote::SIT_PREPARACAO,
                        TefLiquidacaoLote::SIT_AGUARDANDO,
                        TefLiquidacaoLote::SIT_CONFERIDO,
                    ]);
            });

        if (! empty($filtros['pago_de'])) {
            $query->whereDate('pago_em', '>=', $filtros['pago_de']);
        }
        if (! empty($filtros['pago_ate'])) {
            $query->whereDate('pago_em', '<=', $filtros['pago_ate']);
        }

        if ($somenteSem) {
            $query->where(function (Builder $q): void {
                $q->whereNull('acquirer_cnpj')->orWhere('acquirer_cnpj', '');
            });
        } elseif ($cnpjFiltro !== '') {
            $query->where('acquirer_cnpj', $cnpjFiltro);
        }

        return $query
            ->orderBy('pago_em')
            ->orderBy('id');
    }

    /**
     * @param  list<array{tef_intencao_id: int, parcela_numero?: int}>  $selecao
     * @param  array{
     *   terminal_id: int,
     *   acquirer_cnpj?: string|null,
     *   valor_taxas?: float|string|null,
     *   taxa_origem?: string|null,
     *   data_prevista_liquidacao?: string|null,
     *   identificador_externo?: string|null,
     *   observacao?: string|null,
     *   situacao?: string|null,
     *   periodo_de?: string|null,
     *   periodo_ate?: string|null
     * }  $meta
     */
    public function criarLote(int $empresaId, array $selecao, array $meta): TefLiquidacaoLote
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa inválida.');
        }
        if (! Schema::hasTable('tef_liquidacao_lotes') || ! Schema::hasTable('tef_liquidacao_lote_itens')) {
            throw new InvalidArgumentException('Estrutura de lotes não disponível.');
        }

        $terminalId = (int) ($meta['terminal_id'] ?? 0);
        if ($terminalId <= 0) {
            throw new InvalidArgumentException('Terminal obrigatório.');
        }
        if ($selecao === []) {
            throw new InvalidArgumentException('Selecione ao menos uma transação.');
        }
        if (count($selecao) > self::MAX_ITENS_POR_LOTE) {
            throw new InvalidArgumentException('Limite de '.self::MAX_ITENS_POR_LOTE.' itens por lote.');
        }

        $acquirer = TefLiquidacaoDestino::normalizeAcquirerCnpj($meta['acquirer_cnpj'] ?? null);
        // C5B.2 ainda não persiste provedor no lote — resolve como SuperTEF (único operacional).
        $destino = $this->destinoResolver->resolve(
            $empresaId,
            $terminalId,
            $acquirer !== '' ? $acquirer : null,
            TefProviderId::SUPERTEF,
        );
        if (! $destino->isOk()) {
            throw new InvalidArgumentException(
                'Destino bancário '.$destino->status.': '.($destino->motivo ?? 'configure em Destinos SuperTEF.')
            );
        }

        $taxaInformada = $this->money($meta['valor_taxas'] ?? null);
        $taxaOrigem = (string) ($meta['taxa_origem'] ?? '');
        if ($taxaInformada > 0) {
            $taxaOrigem = TefLiquidacaoLote::TAXA_INFORMADA;
        } elseif ($taxaOrigem !== TefLiquidacaoLote::TAXA_INFORMADA) {
            $taxaOrigem = TefLiquidacaoLote::TAXA_NAO_INFORMADA;
            $taxaInformada = 0.0;
        }

        $situacao = (string) ($meta['situacao'] ?? TefLiquidacaoLote::SIT_AGUARDANDO);
        if (! in_array($situacao, [
            TefLiquidacaoLote::SIT_PREPARACAO,
            TefLiquidacaoLote::SIT_AGUARDANDO,
            TefLiquidacaoLote::SIT_CONFERIDO,
        ], true)) {
            $situacao = TefLiquidacaoLote::SIT_AGUARDANDO;
        }

        return DB::transaction(function () use (
            $empresaId,
            $terminalId,
            $acquirer,
            $destino,
            $selecao,
            $meta,
            $taxaInformada,
            $taxaOrigem,
            $situacao,
        ): TefLiquidacaoLote {
            $itensPayload = $this->montarItens($empresaId, $terminalId, $acquirer, $selecao);

            $bruto = 0.0;
            foreach ($itensPayload as $row) {
                $bruto = round($bruto + (float) $row['valor_bruto'], 2);
            }
            $taxas = round($taxaInformada, 2);
            if ($taxas > $bruto) {
                throw new InvalidArgumentException('Taxa informada maior que o valor bruto.');
            }
            $liquido = round($bruto - $taxas, 2);

            $pagoDates = array_values(array_filter(array_map(
                static fn (array $r): ?string => isset($r['pago_em']) && $r['pago_em']
                    ? substr((string) $r['pago_em'], 0, 10)
                    : null,
                $itensPayload
            )));
            sort($pagoDates);

            $lote = TefLiquidacaoLote::query()->create([
                'empresa_id' => $empresaId,
                'terminal_id' => $terminalId,
                'acquirer_cnpj' => $acquirer,
                'caixa_conta_id' => (int) $destino->destino->caixa_conta_id,
                'periodo_de' => $meta['periodo_de'] ?? ($pagoDates[0] ?? null),
                'periodo_ate' => $meta['periodo_ate'] ?? ($pagoDates[count($pagoDates) - 1] ?? null),
                'data_prevista_liquidacao' => $meta['data_prevista_liquidacao'] ?? null,
                'valor_bruto' => $bruto,
                'valor_taxas' => $taxas,
                'taxa_origem' => $taxaOrigem,
                'valor_liquido' => $liquido,
                'situacao' => $situacao,
                'identificador_externo' => $this->blankToNull($meta['identificador_externo'] ?? null),
                'origem' => 'manual',
                'observacao' => $this->blankToNull($meta['observacao'] ?? null),
                'created_by_user_id' => Auth::id(),
                'updated_by_user_id' => Auth::id(),
            ]);

            foreach ($itensPayload as $row) {
                $row['lote_id'] = $lote->id;
                // Rateio proporcional da taxa informada (controle; não é prova bancária).
                if ($taxas > 0 && $bruto > 0) {
                    $row['valor_taxa'] = round(((float) $row['valor_bruto'] / $bruto) * $taxas, 2);
                } else {
                    $row['valor_taxa'] = 0.0;
                }
                $row['valor_liquido'] = round((float) $row['valor_bruto'] - (float) $row['valor_taxa'], 2);
                TefLiquidacaoLoteItem::query()->create($row);
            }

            // Ajuste de centavos na taxa do último item.
            if ($taxas > 0) {
                $somaTaxa = (float) $lote->itens()->sum('valor_taxa');
                $diff = round($taxas - $somaTaxa, 2);
                if (abs($diff) >= 0.01) {
                    /** @var TefLiquidacaoLoteItem|null $ultimo */
                    $ultimo = $lote->itens()->orderByDesc('id')->first();
                    if ($ultimo) {
                        $ultimo->valor_taxa = round((float) $ultimo->valor_taxa + $diff, 2);
                        $ultimo->valor_liquido = round((float) $ultimo->valor_bruto - (float) $ultimo->valor_taxa, 2);
                        $ultimo->save();
                    }
                }
            }

            return $lote->fresh(['itens']);
        });
    }

    public function alterarSituacao(TefLiquidacaoLote $lote, string $novaSituacao): TefLiquidacaoLote
    {
        $permitidas = [
            TefLiquidacaoLote::SIT_PREPARACAO,
            TefLiquidacaoLote::SIT_AGUARDANDO,
            TefLiquidacaoLote::SIT_CONFERIDO,
            TefLiquidacaoLote::SIT_CANCELADO,
        ];
        if (! in_array($novaSituacao, $permitidas, true)) {
            throw new InvalidArgumentException('Situação inválida.');
        }
        if ($lote->situacao === TefLiquidacaoLote::SIT_CANCELADO && $novaSituacao !== TefLiquidacaoLote::SIT_CANCELADO) {
            throw new InvalidArgumentException('Lote cancelado não pode ser reaberto nesta etapa.');
        }

        $lote->situacao = $novaSituacao;
        $lote->updated_by_user_id = Auth::id();
        $lote->save();

        return $lote;
    }

    public function cancelarLote(TefLiquidacaoLote $lote): TefLiquidacaoLote
    {
        return $this->alterarSituacao($lote, TefLiquidacaoLote::SIT_CANCELADO);
    }

    /**
     * Valor da parcela com ajuste de arredondamento na última.
     *
     * @return array{valor: float, parcela: int, total: int}
     */
    public function valorParcela(float $amount, int $installmentCount, int $parcelaNumero): array
    {
        $total = max(1, $installmentCount);
        $parcela = max(1, min($parcelaNumero, $total));
        $base = round($amount / $total, 2);
        if ($parcela < $total) {
            return ['valor' => $base, 'parcela' => $parcela, 'total' => $total];
        }
        $anteriores = round($base * ($total - 1), 2);
        $ultima = round($amount - $anteriores, 2);

        return ['valor' => $ultima, 'parcela' => $parcela, 'total' => $total];
    }

    /**
     * @param  list<array{tef_intencao_id: int, parcela_numero?: int}>  $selecao
     * @return list<array<string, mixed>>
     */
    private function montarItens(int $empresaId, int $terminalId, string $acquirerLote, array $selecao): array
    {
        $ids = [];
        $parcelasPorIntencao = [];
        foreach ($selecao as $row) {
            $id = (int) ($row['tef_intencao_id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException('Intenção inválida na seleção.');
            }
            $parcela = max(1, (int) ($row['parcela_numero'] ?? 1));
            $ids[] = $id;
            $parcelasPorIntencao[$id][] = $parcela;
        }
        $ids = array_values(array_unique($ids));

        /** @var Collection<int, TefIntencao> $intencoes */
        $intencoes = TefIntencao::query()
            ->with(['pdvVenda:id,situacao'])
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($intencoes->count() !== count($ids)) {
            throw new InvalidArgumentException('Uma ou mais intenções não pertencem à empresa.');
        }

        $ocupadas = TefLiquidacaoLoteItem::query()
            ->whereIn('tef_intencao_id', $ids)
            ->whereHas('lote', function (Builder $q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId)
                    ->whereIn('situacao', [
                        TefLiquidacaoLote::SIT_PREPARACAO,
                        TefLiquidacaoLote::SIT_AGUARDANDO,
                        TefLiquidacaoLote::SIT_CONFERIDO,
                    ]);
            })
            ->get(['tef_intencao_id', 'parcela_numero']);

        $ocupacao = [];
        foreach ($ocupadas as $oc) {
            $ocupacao[(int) $oc->tef_intencao_id][(int) $oc->parcela_numero] = true;
        }

        $payload = [];
        foreach ($parcelasPorIntencao as $intencaoId => $parcelas) {
            /** @var TefIntencao $intencao */
            $intencao = $intencoes->get($intencaoId);
            $this->assertElegivel($intencao, $empresaId, $terminalId, $acquirerLote);

            $parcelas = array_values(array_unique($parcelas));
            foreach ($parcelas as $parcelaNumero) {
                if (isset($ocupacao[$intencaoId][$parcelaNumero])) {
                    throw new InvalidArgumentException(
                        "Intenção #{$intencaoId} parcela {$parcelaNumero} já está em lote ativo."
                    );
                }

                $calc = $this->valorParcela(
                    (float) $intencao->amount,
                    (int) ($intencao->installment_count ?: 1),
                    $parcelaNumero
                );

                if ($parcelaNumero > $calc['total']) {
                    throw new InvalidArgumentException("Parcela inválida para intenção #{$intencaoId}.");
                }

                $cnpj = TefLiquidacaoDestino::normalizeAcquirerCnpj(
                    $intencao->acquirerCnpjFiscal() ?? $intencao->acquirer_cnpj
                );

                $payload[] = [
                    'tef_intencao_id' => $intencaoId,
                    'pdv_venda_id' => (int) $intencao->pdv_venda_id,
                    'valor_bruto' => $calc['valor'],
                    'valor_taxa' => 0,
                    'valor_liquido' => $calc['valor'],
                    'nsu' => $intencao->nsu,
                    'autorizacao' => $intencao->autorizacao,
                    'bandeira' => $intencao->bandeira,
                    'transaction_type' => (string) $intencao->transaction_type,
                    'installment_count' => $calc['total'],
                    'parcela_numero' => $calc['parcela'],
                    'acquirer_cnpj' => $cnpj !== '' ? $cnpj : null,
                    'pago_em' => $intencao->pago_em,
                    'data_prevista_parcela' => null,
                ];
            }
        }

        return $payload;
    }

    private function assertElegivel(
        TefIntencao $intencao,
        int $empresaId,
        int $terminalId,
        string $acquirerLote,
    ): void {
        if ((int) $intencao->empresa_id !== $empresaId) {
            throw new InvalidArgumentException("Intenção #{$intencao->id} de outra empresa.");
        }
        if ((int) $intencao->terminal_id !== $terminalId) {
            throw new InvalidArgumentException("Intenção #{$intencao->id} de outro terminal.");
        }
        if ($intencao->status === TefIntencao::STATUS_CANCELADA
            || $intencao->status === TefIntencao::STATUS_ESTORNADA
            || $intencao->status === TefIntencao::STATUS_FALHA) {
            throw new InvalidArgumentException("Intenção #{$intencao->id} cancelada/estornada/falha.");
        }
        if ($intencao->status !== TefIntencao::STATUS_PAGA) {
            throw new InvalidArgumentException("Intenção #{$intencao->id} não está paga.");
        }
        if (! filled($intencao->pdv_venda_id)) {
            throw new InvalidArgumentException("Intenção #{$intencao->id} órfã (sem venda) — análise específica.");
        }
        if ($intencao->requerReconciliacao()) {
            throw new InvalidArgumentException("Intenção #{$intencao->id} requer reconciliação.");
        }

        $venda = $intencao->pdvVenda;
        if (! $venda) {
            $venda = PdvVenda::query()->find((int) $intencao->pdv_venda_id);
        }
        if (! $venda || (string) $venda->situacao === 'C') {
            throw new InvalidArgumentException("Venda da intenção #{$intencao->id} inválida ou cancelada.");
        }

        $cnpjInt = TefLiquidacaoDestino::normalizeAcquirerCnpj(
            $intencao->acquirerCnpjFiscal() ?? $intencao->acquirer_cnpj
        );
        // Lote e intenções devem coincidir no adquirente (inclui lote sem CNPJ).
        if ($acquirerLote !== '') {
            if ($cnpjInt === '') {
                throw new InvalidArgumentException(
                    "Intenção #{$intencao->id} sem CNPJ de adquirente; não entra em lote de adquirente específico."
                );
            }
            if ($cnpjInt !== $acquirerLote) {
                throw new InvalidArgumentException("Intenção #{$intencao->id} de outro adquirente.");
            }
        } elseif ($cnpjInt !== '') {
            throw new InvalidArgumentException(
                "Intenção #{$intencao->id} tem adquirente; use lote com o mesmo CNPJ."
            );
        }
    }

    private function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_string($value)) {
            $value = str_replace(['.', ' '], ['', ''], $value);
            $value = str_replace(',', '.', $value);
        }

        return round((float) $value, 2);
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = trim((string) $value);

        return $t === '' ? null : $t;
    }
}
