<?php

namespace App\Support\Rh;

use App\Models\RhTreinamento;
use App\Models\RhTreinamentoParticipante;
use App\Models\RhTreinamentoRealizacao;
use App\Models\RhTreinamentoRealizacaoLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RhTreinamentoService
{
    /**
     * @param  array{
     *   treinamento_id: int,
     *   realizado_em?: string|null,
     *   funcionario_ids: list<int>,
     *   vencimento_em?: string|null,
     *   observacao?: string|null
     * }  $data
     */
    public function realizar(array $data): RhTreinamentoRealizacao
    {
        $treinamento = RhTreinamento::query()->find((int) ($data['treinamento_id'] ?? 0));
        if (! $treinamento) {
            throw new InvalidArgumentException('Treinamento inválido.');
        }

        if (! $treinamento->ativo) {
            throw new InvalidArgumentException('Treinamento inativo. Reative antes de registrar a realização.');
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $data['funcionario_ids'] ?? []),
            fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            throw new InvalidArgumentException('Selecione ao menos um funcionário.');
        }

        $realizadoEm = $this->parseDate($data['realizado_em'] ?? null) ?? Carbon::today()->toDateString();
        $vencimento = $this->resolveVencimento($treinamento, $realizadoEm, $data['vencimento_em'] ?? null);

        return DB::transaction(function () use ($data, $treinamento, $ids, $realizadoEm, $vencimento): RhTreinamentoRealizacao {
            $realizacao = RhTreinamentoRealizacao::query()->create([
                'codigo' => RhTreinamentoRealizacao::nextCodigo(),
                'treinamento_id' => $treinamento->getKey(),
                'realizado_em' => $realizadoEm,
                'vencimento_em' => $vencimento,
                'observacao' => $this->blankToNull($data['observacao'] ?? null),
                'user_id' => Auth::id(),
                'ativo' => true,
            ]);

            $criados = 0;
            foreach ($ids as $funcionarioId) {
                $exists = RhTreinamentoParticipante::query()
                    ->where('realizacao_id', $realizacao->getKey())
                    ->where('funcionario_id', $funcionarioId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                RhTreinamentoParticipante::query()->create([
                    'codigo' => RhTreinamentoParticipante::nextCodigo(),
                    'realizacao_id' => $realizacao->getKey(),
                    'funcionario_id' => $funcionarioId,
                    'vencimento_em' => $vencimento,
                    'situacao' => RhTreinamentoParticipante::SIT_ATIVO,
                    'observacao' => null,
                    'ativo' => true,
                ]);
                $criados++;
            }

            if ($criados === 0) {
                throw new InvalidArgumentException('Nenhum participante novo para registrar.');
            }

            $this->log($realizacao, 'realizar', null, $this->snapshotRealizacao($realizacao->fresh(['participantes'])), null);

            return $realizacao->fresh(['participantes', 'treinamento']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function corrigirParticipante(RhTreinamentoParticipante $participante, array $data, ?string $motivo = null): RhTreinamentoParticipante
    {
        if ($participante->situacao === RhTreinamentoParticipante::SIT_CANCELADO) {
            throw new InvalidArgumentException('Participante cancelado não pode ser corrigido.');
        }

        $realizacao = $participante->realizacao;
        if (! $realizacao) {
            throw new InvalidArgumentException('Realização não encontrada.');
        }

        $antes = $this->snapshotParticipante($participante);

        if (array_key_exists('vencimento_em', $data)) {
            $participante->vencimento_em = $this->parseDate($data['vencimento_em']);
        }

        if (array_key_exists('observacao', $data)) {
            $participante->observacao = $this->blankToNull($data['observacao']);
        }

        if (filled($data['funcionario_id'] ?? null)) {
            $participante->funcionario_id = (int) $data['funcionario_id'];
        }

        $participante->save();

        $this->log(
            $realizacao,
            'corrigir_participante',
            $antes,
            $this->snapshotParticipante($participante->fresh()),
            $motivo
        );

        return $participante;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function corrigirRealizacao(RhTreinamentoRealizacao $realizacao, array $data, ?string $motivo = null): RhTreinamentoRealizacao
    {
        if (! $realizacao->ativo) {
            throw new InvalidArgumentException('Realização inativa não pode ser corrigida.');
        }

        $antes = $this->snapshotRealizacao($realizacao);

        if (array_key_exists('realizado_em', $data)) {
            $parsed = $this->parseDate($data['realizado_em']);
            if ($parsed !== null) {
                $realizacao->realizado_em = $parsed;
            }
        }

        if (array_key_exists('vencimento_em', $data)) {
            $realizacao->vencimento_em = $this->parseDate($data['vencimento_em']);
        }

        if (array_key_exists('observacao', $data)) {
            $realizacao->observacao = $this->blankToNull($data['observacao']);
        }

        if (filled($data['treinamento_id'] ?? null)) {
            $realizacao->treinamento_id = (int) $data['treinamento_id'];
        }

        $realizacao->save();

        if (array_key_exists('vencimento_em', $data)) {
            $novoVenc = $realizacao->vencimento_em?->toDateString();
            RhTreinamentoParticipante::query()
                ->where('realizacao_id', $realizacao->getKey())
                ->where('situacao', RhTreinamentoParticipante::SIT_ATIVO)
                ->update(['vencimento_em' => $novoVenc]);
        }

        $this->log(
            $realizacao,
            'corrigir_realizacao',
            $antes,
            $this->snapshotRealizacao($realizacao->fresh()),
            $motivo
        );

        return $realizacao;
    }

    public function cancelarParticipante(RhTreinamentoParticipante $participante, ?string $motivo = null): RhTreinamentoParticipante
    {
        if ($participante->situacao === RhTreinamentoParticipante::SIT_CANCELADO) {
            return $participante;
        }

        $realizacao = $participante->realizacao;
        if (! $realizacao) {
            throw new InvalidArgumentException('Realização não encontrada.');
        }

        $antes = $this->snapshotParticipante($participante);
        $participante->update([
            'situacao' => RhTreinamentoParticipante::SIT_CANCELADO,
            'ativo' => false,
            'observacao' => trim(($participante->observacao ? $participante->observacao.' | ' : '').'CANCELADO: '.($motivo ?: 'sem motivo')),
        ]);
        $this->log(
            $realizacao,
            'cancelar_participante',
            $antes,
            $this->snapshotParticipante($participante->fresh()),
            $motivo
        );

        return $participante->fresh();
    }

    public function cancelarRealizacao(RhTreinamentoRealizacao $realizacao, ?string $motivo = null): RhTreinamentoRealizacao
    {
        return DB::transaction(function () use ($realizacao, $motivo): RhTreinamentoRealizacao {
            $realizacao = RhTreinamentoRealizacao::query()->lockForUpdate()->find($realizacao->getKey());
            if (! $realizacao) {
                throw new InvalidArgumentException('Realização não encontrada.');
            }

            $antes = $this->snapshotRealizacao($realizacao);

            $participantes = RhTreinamentoParticipante::query()
                ->where('realizacao_id', $realizacao->getKey())
                ->where('situacao', '!=', RhTreinamentoParticipante::SIT_CANCELADO)
                ->lockForUpdate()
                ->get();

            foreach ($participantes as $participante) {
                $this->cancelarParticipante($participante, $motivo);
            }

            $realizacao->update(['ativo' => false]);
            $this->log(
                $realizacao,
                'cancelar_realizacao',
                $antes,
                $this->snapshotRealizacao($realizacao->fresh()),
                $motivo
            );

            return $realizacao->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotParticipante(RhTreinamentoParticipante $participante): array
    {
        return [
            'codigo' => $participante->codigo,
            'realizacao_id' => $participante->realizacao_id,
            'funcionario_id' => $participante->funcionario_id,
            'vencimento_em' => $participante->vencimento_em?->toDateString(),
            'situacao' => $participante->situacao,
            'observacao' => $participante->observacao,
            'ativo' => $participante->ativo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotRealizacao(RhTreinamentoRealizacao $realizacao): array
    {
        return [
            'codigo' => $realizacao->codigo,
            'treinamento_id' => $realizacao->treinamento_id,
            'realizado_em' => $realizacao->realizado_em?->toDateString(),
            'vencimento_em' => $realizacao->vencimento_em?->toDateString(),
            'observacao' => $realizacao->observacao,
            'ativo' => $realizacao->ativo,
            'participantes' => $realizacao->relationLoaded('participantes')
                ? $realizacao->participantes->map(fn (RhTreinamentoParticipante $p): array => $this->snapshotParticipante($p))->values()->all()
                : null,
        ];
    }

    private function resolveVencimento(RhTreinamento $treinamento, string $realizadoEm, mixed $informada): ?string
    {
        $parsed = $this->parseDate($informada);
        if ($parsed !== null) {
            return $parsed;
        }

        if ($treinamento->periodicidade_dias) {
            return Carbon::parse($realizadoEm)->addDays((int) $treinamento->periodicidade_dias)->toDateString();
        }

        return null;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    private function log(RhTreinamentoRealizacao $realizacao, string $acao, ?array $antes, ?array $depois, ?string $motivo): void
    {
        RhTreinamentoRealizacaoLog::query()->create([
            'realizacao_id' => $realizacao->getKey(),
            'user_id' => Auth::id(),
            'acao' => $acao,
            'antes' => $antes,
            'depois' => $depois,
            'motivo' => $this->blankToNull($motivo),
            'created_at' => now(),
        ]);
    }
}
