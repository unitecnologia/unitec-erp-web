<?php

namespace App\Support\Erp\Tef;

use App\Models\ExtratoConciliacao;
use App\Models\ExtratoLancamento;
use App\Models\TefLiquidacaoLote;
use App\Models\TefLiquidacaoLoteExtrato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * C5C.2 — Vínculo manual de controle lote TEF ↔ crédito OFX.
 * Não cria caixa/CR/baixas; não altera status SuperTEF; sem auto-conciliação.
 */
final class TefLiquidacaoLoteOfxVinculoService
{
    public const MAX_CANDIDATOS = 50;

    /**
     * Créditos OFX elegíveis para vincular ao lote (paginável).
     *
     * @param  array{data_de?: string|null, data_ate?: string|null, q?: string|null}  $filtros
     * @return Builder<ExtratoLancamento>
     */
    public function queryCreditosCandidatos(TefLiquidacaoLote $lote, array $filtros = []): Builder
    {
        $empresaId = (int) $lote->empresa_id;
        $contaId = (int) $lote->caixa_conta_id;

        $query = ExtratoLancamento::query()
            ->where('empresa_id', $empresaId)
            ->where('caixa_conta_id', $contaId)
            ->where('tipo', ExtratoLancamento::TIPO_CREDITO)
            ->where('situacao', ExtratoLancamento::SITUACAO_PENDENTE)
            ->whereNull('caixa_lancamento_id')
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('extrato_conciliacoes as ec')
                    ->whereColumn('ec.extrato_lancamento_id', 'extrato_lancamentos.id')
                    ->where('ec.status', ExtratoConciliacao::STATUS_ATIVA);
            });

        if (Schema::hasTable('tef_liquidacao_lote_extratos')) {
            // Parcial OK; exclui integral (vínculos + sobra encerrada).
            $query->whereRaw(
                '(SELECT COALESCE(SUM(v.valor_vinculado + v.diferenca), 0) FROM tef_liquidacao_lote_extratos v
                  WHERE v.extrato_lancamento_id = extrato_lancamentos.id AND v.status = ?) < extrato_lancamentos.valor',
                [TefLiquidacaoLoteExtrato::STATUS_ATIVO]
            );
        }

        if (! empty($filtros['data_de'])) {
            $query->whereDate('data_movimento', '>=', $filtros['data_de']);
        }
        if (! empty($filtros['data_ate'])) {
            $query->whereDate('data_movimento', '<=', $filtros['data_ate']);
        }
        if (! empty($filtros['q'])) {
            $term = '%'.mb_strtoupper(trim((string) $filtros['q']), 'UTF-8').'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('historico', 'like', $term)
                    ->orWhere('memo', 'like', $term)
                    ->orWhere('fitid', 'like', $term);
            });
        }

        return $query
            ->orderByDesc('data_movimento')
            ->orderByDesc('id')
            ->limit(self::MAX_CANDIDATOS);
    }

    public function somaVinculadaLote(int $loteId, ?int $excetoVinculoId = null): string
    {
        $q = TefLiquidacaoLoteExtrato::query()
            ->where('lote_id', $loteId)
            ->where('status', TefLiquidacaoLoteExtrato::STATUS_ATIVO);
        if ($excetoVinculoId) {
            $q->where('id', '!=', $excetoVinculoId);
        }

        return TefMoney::normalize($q->sum('valor_vinculado'));
    }

    public function somaVinculadaExtrato(int $extratoId, ?int $excetoVinculoId = null): string
    {
        $q = TefLiquidacaoLoteExtrato::query()
            ->where('extrato_lancamento_id', $extratoId)
            ->where('status', TefLiquidacaoLoteExtrato::STATUS_ATIVO);
        if ($excetoVinculoId) {
            $q->where('id', '!=', $excetoVinculoId);
        }

        return TefMoney::normalize($q->sum('valor_vinculado'));
    }

    /** Sobra encerrada com justificativa (não alocável a outro lote). */
    public function somaSobraEncerradaExtrato(int $extratoId, ?int $excetoVinculoId = null): string
    {
        $q = TefLiquidacaoLoteExtrato::query()
            ->where('extrato_lancamento_id', $extratoId)
            ->where('status', TefLiquidacaoLoteExtrato::STATUS_ATIVO)
            ->where('diferenca', '>', 0);
        if ($excetoVinculoId) {
            $q->where('id', '!=', $excetoVinculoId);
        }

        return TefMoney::normalize($q->sum('diferenca'));
    }

    public function restanteLote(TefLiquidacaoLote $lote): string
    {
        return TefMoney::sub((string) $lote->valor_liquido, $this->somaVinculadaLote((int) $lote->id));
    }

    public function restanteExtrato(ExtratoLancamento $extrato): string
    {
        $ocupado = TefMoney::add(
            $this->somaVinculadaExtrato((int) $extrato->id),
            $this->somaSobraEncerradaExtrato((int) $extrato->id),
        );

        return TefMoney::sub((string) $extrato->valor, $ocupado);
    }

    /**
     * Extrato tem vínculo TEF ativo? (guard C3)
     */
    public function extratoTemVinculoTefAtivo(int $extratoId): bool
    {
        if (! Schema::hasTable('tef_liquidacao_lote_extratos')) {
            return false;
        }

        return TefLiquidacaoLoteExtrato::query()
            ->where('extrato_lancamento_id', $extratoId)
            ->where('status', TefLiquidacaoLoteExtrato::STATUS_ATIVO)
            ->exists();
    }

    /**
     * @return array{vinculo_id: int, lote_ofx_situacao: string, restante_lote: string, restante_extrato: string}
     */
    /**
     * @param  bool  $encerrarCreditoComSobra  true = sobra do OFX não terá outro lote (exige justificativa se diferenca≠0)
     */
    public function vincular(
        TefLiquidacaoLote $lote,
        ExtratoLancamento $extrato,
        mixed $valorVinculado,
        ?string $diferencaMotivo = null,
        ?int $userId = null,
        bool $encerrarCreditoComSobra = false,
    ): array {
        $userId = $userId ?: (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            throw new TefLiquidacaoLoteOfxVinculoException('Usuário inválido para vincular.');
        }
        if (! Schema::hasTable('tef_liquidacao_lote_extratos')) {
            throw new TefLiquidacaoLoteOfxVinculoException('Estrutura de vínculo OFX não disponível.');
        }

        $valor = TefMoney::normalize($valorVinculado);
        if (! TefMoney::isPositive($valor)) {
            throw new TefLiquidacaoLoteOfxVinculoException('Informe um valor de vínculo maior que zero.');
        }

        try {
            return DB::transaction(function () use ($lote, $extrato, $valor, $diferencaMotivo, $userId, $encerrarCreditoComSobra): array {
                /** @var TefLiquidacaoLote|null $loteLock */
                $loteLock = TefLiquidacaoLote::query()->whereKey((int) $lote->id)->lockForUpdate()->first();
                /** @var ExtratoLancamento|null $extratoLock */
                $extratoLock = ExtratoLancamento::query()->whereKey((int) $extrato->id)->lockForUpdate()->first();

                if (! $loteLock || ! $extratoLock) {
                    throw new TefLiquidacaoLoteOfxVinculoException('Lote ou extrato não encontrado.');
                }

                $this->assertLoteElegivel($loteLock);
                $this->assertExtratoElegivelParaTef($extratoLock, $loteLock);

                $restLote = $this->restanteLote($loteLock);
                // Inclui sobra já encerrada — impede reuso do crédito após encerrarCreditoComSobra.
                $restExtrato = $this->restanteExtrato($extratoLock);

                if (TefMoney::cmp($valor, $restLote) === 1) {
                    throw new TefLiquidacaoLoteOfxVinculoException(
                        'Valor excede o líquido restante do lote ('.$restLote.').'
                    );
                }
                if (TefMoney::cmp($valor, $restExtrato) === 1) {
                    throw new TefLiquidacaoLoteOfxVinculoException(
                        'Valor excede o crédito restante do OFX ('.$restExtrato.').'
                    );
                }

                $motivo = trim((string) ($diferencaMotivo ?? ''));
                $loteFecha = TefMoney::cmp($valor, $restLote) === 0;
                $extratoFecha = TefMoney::cmp($valor, $restExtrato) === 0;
                $diferenca = '0.00';

                // Cobertura exacta vs encerramento com sobra:
                // - N lotes × 1 crédito: diferenca=0 (sobra segue disponível a outros lotes).
                // - encerrarCreditoComSobra: grava diferenca auditável e remove a sobra do disponível.
                // Diferença NUNCA vira taxa financeira automática.
                if ($loteFecha && ! $extratoFecha && $encerrarCreditoComSobra) {
                    $diferenca = TefMoney::sub($restExtrato, $valor);
                    if (TefMoney::isZero($diferenca)) {
                        throw new TefLiquidacaoLoteOfxVinculoException(
                            'Não há sobra no crédito para encerrar — use vínculo de cobertura exacta.'
                        );
                    }
                    if ($motivo === '') {
                        throw new TefLiquidacaoLoteOfxVinculoException(
                            'Divergência de '.$diferenca.' no OFX exige justificativa — sem tolerância automática.'
                        );
                    }
                }

                $agora = now();
                $vinculo = TefLiquidacaoLoteExtrato::query()->create([
                    'empresa_id' => (int) $loteLock->empresa_id,
                    'caixa_conta_id' => (int) $loteLock->caixa_conta_id,
                    'lote_id' => (int) $loteLock->id,
                    'extrato_lancamento_id' => (int) $extratoLock->id,
                    'valor_vinculado' => $valor,
                    'valor_lote_liquido_ref' => TefMoney::normalize((string) $loteLock->valor_liquido),
                    'valor_extrato_ref' => TefMoney::normalize((string) $extratoLock->valor),
                    'diferenca' => $diferenca,
                    'diferenca_motivo' => $motivo !== '' ? $motivo : null,
                    'status' => TefLiquidacaoLoteExtrato::STATUS_ATIVO,
                    'vinculado_por_user_id' => $userId,
                    'vinculado_em' => $agora,
                    'lock_token' => 'L'.(int) $loteLock->id.'-E'.(int) $extratoLock->id.'-'.str_replace('.', '', uniqid('', true)),
                ]);

                $ofxSit = $this->recalcularOfxSituacaoLote($loteLock);

                return [
                    'vinculo_id' => (int) $vinculo->id,
                    'lote_ofx_situacao' => $ofxSit,
                    'restante_lote' => $this->restanteLote($loteLock->fresh()),
                    'restante_extrato' => $this->restanteExtrato($extratoLock->fresh()),
                ];
            });
        } catch (TefLiquidacaoLoteOfxVinculoException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($this->isUniqueConstraint($e)) {
                throw new TefLiquidacaoLoteOfxVinculoException(
                    'Concorrência: vínculo OFX já registrado por outro usuário.'
                );
            }
            throw $e;
        }
    }

    public function reverter(
        TefLiquidacaoLoteExtrato $vinculo,
        ?string $motivoReversao = null,
        ?int $userId = null,
    ): TefLiquidacaoLoteExtrato {
        $userId = $userId ?: (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            throw new TefLiquidacaoLoteOfxVinculoException('Usuário inválido para desfazer.');
        }

        return DB::transaction(function () use ($vinculo, $motivoReversao, $userId): TefLiquidacaoLoteExtrato {
            /** @var TefLiquidacaoLoteExtrato|null $lock */
            $lock = TefLiquidacaoLoteExtrato::query()->whereKey((int) $vinculo->id)->lockForUpdate()->first();
            if (! $lock) {
                throw new TefLiquidacaoLoteOfxVinculoException('Vínculo não encontrado.');
            }
            if ($lock->status !== TefLiquidacaoLoteExtrato::STATUS_ATIVO) {
                throw new TefLiquidacaoLoteOfxVinculoException('Vínculo já revertido.');
            }

            /** @var TefLiquidacaoLote|null $lote */
            $lote = TefLiquidacaoLote::query()->whereKey((int) $lock->lote_id)->lockForUpdate()->first();
            ExtratoLancamento::query()->whereKey((int) $lock->extrato_lancamento_id)->lockForUpdate()->first();

            $lock->status = TefLiquidacaoLoteExtrato::STATUS_REVERTIDO;
            $lock->revertido_por_user_id = $userId;
            $lock->revertido_em = now();
            $lock->motivo_reversao = ($m = trim((string) ($motivoReversao ?? ''))) !== '' ? $m : 'Reversão manual';
            $lock->lock_token = null;
            $lock->save();

            if ($lote) {
                $this->recalcularOfxSituacaoLote($lote);
            }

            return $lock->fresh();
        });
    }

    public function recalcularOfxSituacaoLote(TefLiquidacaoLote $lote): string
    {
        $soma = $this->somaVinculadaLote((int) $lote->id);
        $liquido = TefMoney::normalize((string) $lote->valor_liquido);

        if (TefMoney::isZero($soma)) {
            $sit = TefLiquidacaoLote::OFX_PENDENTE;
        } elseif (TefMoney::cmp($soma, $liquido) === 0) {
            $sit = TefLiquidacaoLote::OFX_INTEGRAL;
        } elseif (TefMoney::cmp($soma, $liquido) === 1) {
            // Não deveria ocorrer — proteção.
            throw new TefLiquidacaoLoteOfxVinculoException('Soma vinculada excede o líquido do lote.');
        } else {
            $sit = TefLiquidacaoLote::OFX_PARCIAL;
        }

        if (Schema::hasColumn('tef_liquidacao_lotes', 'ofx_situacao')) {
            $lote->ofx_situacao = $sit;
            $lote->save();
        }

        return $sit;
    }

    /**
     * Situação do crédito OFX sob ótica TEF (não altera coluna situacao C3).
     */
    public function situacaoExtratoTef(ExtratoLancamento $extrato): string
    {
        $soma = $this->somaVinculadaExtrato((int) $extrato->id);
        $sobra = $this->somaSobraEncerradaExtrato((int) $extrato->id);
        if (TefMoney::isZero($soma) && TefMoney::isZero($sobra)) {
            return TefLiquidacaoLote::OFX_PENDENTE;
        }
        // Integral = sem restante (vínculos + sobra encerrada cobrem o crédito).
        if (TefMoney::isZero($this->restanteExtrato($extrato))) {
            return TefLiquidacaoLote::OFX_INTEGRAL;
        }

        return TefLiquidacaoLote::OFX_PARCIAL;
    }

    private function assertLoteElegivel(TefLiquidacaoLote $lote): void
    {
        if ($lote->situacao === TefLiquidacaoLote::SIT_CANCELADO) {
            throw new TefLiquidacaoLoteOfxVinculoException('Lote cancelado não pode vincular OFX.');
        }
        if ($lote->situacao !== TefLiquidacaoLote::SIT_CONFERIDO) {
            throw new TefLiquidacaoLoteOfxVinculoException('Somente lote conferido pode vincular OFX.');
        }
    }

    private function assertExtratoElegivelParaTef(ExtratoLancamento $extrato, TefLiquidacaoLote $lote): void
    {
        if ((int) $extrato->empresa_id !== (int) $lote->empresa_id) {
            throw new TefLiquidacaoLoteOfxVinculoException('Extrato de outra empresa.');
        }
        if ((int) $extrato->caixa_conta_id !== (int) $lote->caixa_conta_id) {
            throw new TefLiquidacaoLoteOfxVinculoException('Conta BANCO do extrato diferente do destino do lote.');
        }
        if ((string) $extrato->tipo !== ExtratoLancamento::TIPO_CREDITO) {
            throw new TefLiquidacaoLoteOfxVinculoException('Somente crédito OFX pode vincular a lote TEF.');
        }
        if ((string) $extrato->situacao === ExtratoLancamento::SITUACAO_CONCILIADO
            || (string) $extrato->situacao === ExtratoLancamento::SITUACAO_IGNORADO
        ) {
            throw new TefLiquidacaoLoteOfxVinculoException('Extrato já conciliado (C3) ou ignorado.');
        }
        if ($extrato->caixa_lancamento_id) {
            throw new TefLiquidacaoLoteOfxVinculoException('Extrato já vinculado ao Livro Caixa (C3).');
        }

        $c3 = ExtratoConciliacao::query()
            ->where('extrato_lancamento_id', (int) $extrato->id)
            ->where('status', ExtratoConciliacao::STATUS_ATIVA)
            ->exists();
        if ($c3) {
            throw new TefLiquidacaoLoteOfxVinculoException('Extrato possui conciliação C3 ativa.');
        }
    }

    private function isUniqueConstraint(Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'Duplicate')
            || str_contains($msg, 'UNIQUE')
            || str_contains($msg, 'unique');
    }
}
