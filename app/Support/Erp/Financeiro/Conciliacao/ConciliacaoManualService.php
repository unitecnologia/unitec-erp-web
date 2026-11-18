<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

use App\Models\CaixaLancamento;
use App\Models\ExtratoConciliacao;
use App\Models\ExtratoLancamento;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\Tef\TefLiquidacaoLoteOfxVinculoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Conciliação manual 1:1 (C3).
 * Não altera caixa_lancamentos, CR/CP, baixas, PIX, TEF nem saldos.
 */
final class ConciliacaoManualService
{
    /**
     * @return array{
     *   conciliacao_id: int,
     *   extrato_id: int,
     *   caixa_lancamento_id: int
     * }
     */
    public function conciliar(
        ExtratoLancamento $extrato,
        CaixaLancamento $caixa,
        int $userId,
        ?int $empresaEsperada = null,
        bool $aceitarIdentificacaoFraca = false,
    ): array {
        if ($userId <= 0) {
            throw new ConciliacaoManualException('Usuário inválido para conciliar.');
        }

        if (! Schema::hasTable('extrato_conciliacoes')) {
            throw new ConciliacaoManualException('Estrutura de conciliação não disponível.');
        }

        try {
            return DB::transaction(function () use ($extrato, $caixa, $userId, $empresaEsperada, $aceitarIdentificacaoFraca): array {
                /** @var ExtratoLancamento|null $extratoLock */
                $extratoLock = ExtratoLancamento::query()
                    ->whereKey((int) $extrato->id)
                    ->lockForUpdate()
                    ->first();

                if (! $extratoLock) {
                    throw new ConciliacaoManualException('Lançamento de extrato não encontrado.');
                }

                /** @var CaixaLancamento|null $caixaLock */
                $caixaLock = CaixaLancamento::query()
                    ->whereKey((int) $caixa->id)
                    ->lockForUpdate()
                    ->first();

                if (! $caixaLock) {
                    throw new ConciliacaoManualException('Lançamento do Livro Caixa não encontrado.');
                }

                $this->assertElegivelParaConciliar($extratoLock, $caixaLock, $empresaEsperada, $aceitarIdentificacaoFraca);

                $valorExtrato = round((float) $extratoLock->valor, 2);
                $valorCaixa = $this->valorCaixaNoSentido($caixaLock, (string) $extratoLock->tipo);
                $match = PixIdentificadorOfx::classificarMatch($extratoLock, $caixaLock);

                $agora = now();
                $payload = [
                    'empresa_id' => (int) $extratoLock->empresa_id,
                    'caixa_conta_id' => (int) $extratoLock->caixa_conta_id,
                    'extrato_lancamento_id' => (int) $extratoLock->id,
                    'caixa_lancamento_id' => (int) $caixaLock->id,
                    'status' => ExtratoConciliacao::STATUS_ATIVA,
                    'tipo_extrato' => (string) $extratoLock->tipo,
                    'valor_extrato' => $valorExtrato,
                    'valor_caixa' => $valorCaixa,
                    'data_movimento_extrato' => $extratoLock->data_movimento,
                    'data_emissao_caixa' => $caixaLock->emissao,
                    'historico_extrato' => $this->truncate($extratoLock->historico, 500),
                    'fitid' => $this->truncate($extratoLock->fitid, 120),
                    'documento_caixa' => $this->truncate($caixaLock->documento ?? null, 40),
                    'historico_caixa' => $this->truncate($caixaLock->historico ?? null, 500),
                    'conciliado_por_user_id' => $userId,
                    'conciliado_em' => $agora,
                    'lock_extrato' => (string) $extratoLock->id,
                    'lock_caixa' => (string) $caixaLock->id,
                ];

                if (Schema::hasColumn('extrato_conciliacoes', 'identificacao')) {
                    $payload['identificacao'] = $match;
                }
                if (Schema::hasColumn('extrato_conciliacoes', 'observacao')
                    && $match === PixIdentificadorOfx::MATCH_FRACA
                ) {
                    $payload['observacao'] = $this->truncate(
                        'Identificação não comprovada (sem txId/endToEndId). FITID ignorado. Usuário '.$userId
                        .' em '.$agora->format('d/m/Y H:i:s').'.',
                        255,
                    );
                }

                $conciliacao = ExtratoConciliacao::query()->create($payload);

                $extratoLock->forceFill([
                    'situacao' => ExtratoLancamento::SITUACAO_CONCILIADO,
                    'caixa_lancamento_id' => (int) $caixaLock->id,
                    'origem_tipo' => 'caixa_lancamento',
                    'origem_id' => (int) $caixaLock->id,
                    'conciliado_em' => $agora,
                    'conciliado_por_user_id' => $userId,
                ])->save();

                // Garante que o Livro Caixa não foi tocado nesta transação.
                $caixaLock->refresh();

                $resultado = [
                    'conciliacao_id' => (int) $conciliacao->id,
                    'extrato_id' => (int) $extratoLock->id,
                    'caixa_lancamento_id' => (int) $caixaLock->id,
                    'identificacao' => $match,
                ];

                if ($match === PixIdentificadorOfx::MATCH_FRACA) {
                    $this->auditarPixFraca($extratoLock, $caixaLock, $userId, $agora, (int) $conciliacao->id);
                }

                return $resultado;
            });
        } catch (ConciliacaoManualException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($this->isUniqueConstraint($e)) {
                throw new ConciliacaoManualException(
                    'Conciliação concorrente: extrato ou lançamento do caixa já vinculado.',
                );
            }

            throw $e;
        }
    }

    /**
     * @return array{conciliacao_id: int, extrato_id: int}
     */
    public function reverter(
        ExtratoLancamento $extrato,
        int $userId,
        ?string $motivo = null,
        ?int $empresaEsperada = null,
    ): array {
        if ($userId <= 0) {
            throw new ConciliacaoManualException('Usuário inválido para desfazer.');
        }

        return DB::transaction(function () use ($extrato, $userId, $motivo, $empresaEsperada): array {
            /** @var ExtratoLancamento|null $extratoLock */
            $extratoLock = ExtratoLancamento::query()
                ->whereKey((int) $extrato->id)
                ->lockForUpdate()
                ->first();

            if (! $extratoLock) {
                throw new ConciliacaoManualException('Lançamento de extrato não encontrado.');
            }

            if ($empresaEsperada !== null && (int) $extratoLock->empresa_id !== (int) $empresaEsperada) {
                throw new ConciliacaoManualException('Extrato de outra empresa.');
            }

            if ($extratoLock->situacao !== ExtratoLancamento::SITUACAO_CONCILIADO) {
                throw new ConciliacaoManualException('Somente lançamentos conciliados podem ser desfeitos.');
            }

            /** @var ExtratoConciliacao|null $ativa */
            $ativa = ExtratoConciliacao::query()
                ->where('extrato_lancamento_id', (int) $extratoLock->id)
                ->where('status', ExtratoConciliacao::STATUS_ATIVA)
                ->lockForUpdate()
                ->first();

            if (! $ativa) {
                throw new ConciliacaoManualException('Vínculo ativo de conciliação não encontrado.');
            }

            $caixaId = (int) $ativa->caixa_lancamento_id;
            $caixaAntes = $caixaId > 0
                ? CaixaLancamento::query()->whereKey($caixaId)->first()?->getAttributes()
                : null;

            $ativa->forceFill([
                'status' => ExtratoConciliacao::STATUS_REVERTIDA,
                'lock_extrato' => null,
                'lock_caixa' => null,
                'revertido_por_user_id' => $userId,
                'revertido_em' => now(),
                'motivo_reversao' => $this->truncate($motivo ?: 'Reversão manual', 255),
            ])->save();

            $extratoLock->forceFill([
                'situacao' => ExtratoLancamento::SITUACAO_PENDENTE,
                'caixa_lancamento_id' => null,
                'origem_tipo' => 'ofx',
                'origem_id' => null,
                'conciliado_em' => null,
                'conciliado_por_user_id' => null,
            ])->save();

            if ($caixaAntes !== null) {
                $caixaDepois = CaixaLancamento::query()->whereKey($caixaId)->first()?->getAttributes();
                if ($caixaDepois !== null && $caixaAntes !== $caixaDepois) {
                    throw new ConciliacaoManualException(
                        'Abortado: a reversão não pode alterar o Livro Caixa.',
                    );
                }
            }

            return [
                'conciliacao_id' => (int) $ativa->id,
                'extrato_id' => (int) $extratoLock->id,
            ];
        });
    }

    private function assertElegivelParaConciliar(
        ExtratoLancamento $extrato,
        CaixaLancamento $caixa,
        ?int $empresaEsperada,
        bool $aceitarIdentificacaoFraca = false,
    ): void {
        if ($empresaEsperada !== null && (int) $extrato->empresa_id !== (int) $empresaEsperada) {
            throw new ConciliacaoManualException('Extrato de outra empresa.');
        }

        if ($extrato->situacao !== ExtratoLancamento::SITUACAO_PENDENTE) {
            throw new ConciliacaoManualException('Lançamento de extrato já conciliado ou não elegível.');
        }

        if ($extrato->caixa_lancamento_id) {
            throw new ConciliacaoManualException('Extrato já possui vínculo com o Livro Caixa.');
        }

        // Exclusão mútua C3 × TEF (C5C.2): crédito com vínculo TEF ativo não entra no C3.
        if (app(TefLiquidacaoLoteOfxVinculoService::class)->extratoTemVinculoTefAtivo((int) $extrato->id)) {
            throw new ConciliacaoManualException(
                'Extrato possui vínculo ativo com lote TEF — conciliação C3 não permitida.'
            );
        }

        $empresaCaixa = (int) ($caixa->empresa_id ?? 0);
        if ($empresaCaixa > 0 && $empresaCaixa !== (int) $extrato->empresa_id) {
            throw new ConciliacaoManualException('Lançamento financeiro de outra empresa.');
        }

        if ((int) $caixa->caixa_conta_id !== (int) $extrato->caixa_conta_id) {
            throw new ConciliacaoManualException('Conta bancária diferente — conciliação exata não permitida.');
        }

        $tipo = (string) $extrato->tipo;
        if (! in_array($tipo, [ExtratoLancamento::TIPO_CREDITO, ExtratoLancamento::TIPO_DEBITO], true)) {
            throw new ConciliacaoManualException('Tipo de movimento do extrato não suportado nesta etapa.');
        }

        $valorExtrato = round((float) $extrato->valor, 2);
        $valorCaixa = $this->valorCaixaNoSentido($caixa, $tipo);

        if ($valorCaixa === null) {
            throw new ConciliacaoManualException('Sentido financeiro incompatível (entrada × saída).');
        }

        if (round($valorCaixa, 2) !== $valorExtrato) {
            throw new ConciliacaoManualException('Valor divergente — conciliação exata não permitida.');
        }

        if (PixIdentificadorOfx::classificarMatch($extrato, $caixa) === PixIdentificadorOfx::MATCH_FRACA
            && ! $aceitarIdentificacaoFraca
        ) {
            throw new ConciliacaoManualException(
                'Correspondência PIX fraca: sem txId/endToEndId comum. Confirme a identificação não comprovada.',
            );
        }

        $jaVinculado = ExtratoLancamento::query()
            ->where('caixa_lancamento_id', (int) $caixa->id)
            ->exists();
        if ($jaVinculado) {
            throw new ConciliacaoManualException('Lançamento do Livro Caixa já conciliado com outro extrato.');
        }

        $ativaExistente = ExtratoConciliacao::query()
            ->where(function ($q) use ($extrato, $caixa): void {
                $q->where('extrato_lancamento_id', (int) $extrato->id)
                    ->orWhere('caixa_lancamento_id', (int) $caixa->id);
            })
            ->where('status', ExtratoConciliacao::STATUS_ATIVA)
            ->exists();
        if ($ativaExistente) {
            throw new ConciliacaoManualException('Já existe vínculo ativo para este extrato ou lançamento.');
        }

        // Data compatível (mesma janela da busca).
        $janela = (int) config('unitec.conciliacao.c3_date_window_days', 3);
        $dataExtrato = $extrato->data_movimento instanceof Carbon
            ? $extrato->data_movimento->copy()->startOfDay()
            : Carbon::parse((string) $extrato->data_movimento)->startOfDay();
        $dataCaixa = $caixa->emissao instanceof Carbon
            ? $caixa->emissao->copy()->startOfDay()
            : Carbon::parse((string) $caixa->emissao)->startOfDay();

        if (abs($dataExtrato->diffInDays($dataCaixa)) > $janela) {
            throw new ConciliacaoManualException('Data fora da janela compatível — conciliação exata não permitida.');
        }
    }

    private function valorCaixaNoSentido(CaixaLancamento $caixa, string $tipoExtrato): ?float
    {
        $entrada = round((float) $caixa->entrada, 2);
        $saida = round((float) $caixa->saida, 2);

        if ($tipoExtrato === ExtratoLancamento::TIPO_CREDITO) {
            if ($entrada > 0 && $saida == 0.0) {
                return $entrada;
            }

            return null;
        }

        if ($tipoExtrato === ExtratoLancamento::TIPO_DEBITO) {
            if ($saida > 0 && $entrada == 0.0) {
                return $saida;
            }

            return null;
        }

        return null;
    }

    private function auditarPixFraca(
        ExtratoLancamento $extrato,
        CaixaLancamento $caixa,
        int $userId,
        Carbon $quando,
        int $conciliacaoId,
    ): void {
        try {
            app(ErpOperacaoLogService::class)->registrar(
                operacao: 'CONCILIAR_EXTRATO_PIX_FRACA',
                resumo: 'Conciliação PIX assistida sem txId/endToEndId comum. Identificação não comprovada.',
                origem: 'conciliacao_bancaria',
                documentoTipo: 'extrato_conciliacao',
                documentoId: $conciliacaoId,
                detalhes: [
                    'extrato_id' => (int) $extrato->id,
                    'caixa_lancamento_id' => (int) $caixa->id,
                    'fitid' => (string) ($extrato->fitid ?? ''),
                    'valor' => (string) $extrato->valor,
                    'user_id' => $userId,
                    'user_nome' => Auth::user()?->name,
                    'conciliado_em' => $quando->toDateTimeString(),
                ],
                empresaId: (int) $extrato->empresa_id,
            );
        } catch (Throwable) {
            // Auditoria complementar: o vínculo 1:1 já gravou usuário/data.
        }
    }

    private function truncate(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $max, 'UTF-8');
    }

    private function isUniqueConstraint(Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'unique')
            || str_contains($msg, 'duplicate')
            || str_contains($msg, 'integrity constraint');
    }
}
