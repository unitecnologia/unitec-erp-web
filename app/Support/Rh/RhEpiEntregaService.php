<?php

namespace App\Support\Rh;

use App\Models\RhEpi;
use App\Models\RhEpiEntrega;
use App\Models\RhEpiEntregaLog;
use App\Models\RhFuncionario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RhEpiEntregaService
{
    /**
     * @param  array{
     *   funcionario_id: int,
     *   epi_id: int,
     *   quantidade?: int,
     *   entregue_em?: string|null,
     *   previsao_troca?: string|null,
     *   observacao?: string|null,
     *   substitui_id?: int|null,
     *   motivo_substituicao?: string|null
     * }  $data
     */
    public function entregar(array $data): RhEpiEntrega
    {
        $funcionario = RhFuncionario::query()->find((int) $data['funcionario_id']);
        $epi = RhEpi::query()->find((int) $data['epi_id']);

        if (! $funcionario || ! $epi) {
            throw new InvalidArgumentException('Funcionário ou EPI inválido.');
        }

        if (! $epi->ativo) {
            throw new InvalidArgumentException('EPI inativo. Reative o equipamento antes de entregar.');
        }

        $qtd = max(1, (int) ($data['quantidade'] ?? 1));
        $entregueEm = $this->parseDate($data['entregue_em'] ?? null) ?? Carbon::today()->toDateString();
        $previsao = $this->resolvePrevisaoTroca($epi, $entregueEm, $data['previsao_troca'] ?? null);

        return DB::transaction(function () use ($data, $funcionario, $epi, $qtd, $entregueEm, $previsao): RhEpiEntrega {
            $substituiId = filled($data['substitui_id'] ?? null) ? (int) $data['substitui_id'] : null;
            $anterior = null;

            if ($substituiId) {
                $anterior = RhEpiEntrega::query()->lockForUpdate()->find($substituiId);
                if (! $anterior || $anterior->situacao !== RhEpiEntrega::SIT_ENTREGUE) {
                    throw new InvalidArgumentException('Entrega a substituir inválida.');
                }
            }

            $entrega = RhEpiEntrega::query()->create([
                'codigo' => RhEpiEntrega::nextCodigo(),
                'funcionario_id' => $funcionario->getKey(),
                'epi_id' => $epi->getKey(),
                'quantidade' => $qtd,
                'entregue_em' => $entregueEm,
                'previsao_troca' => $previsao,
                'situacao' => RhEpiEntrega::SIT_ENTREGUE,
                'observacao' => $this->blankToNull($data['observacao'] ?? null),
                'motivo_substituicao' => $this->blankToNull($data['motivo_substituicao'] ?? null),
                'substitui_id' => $substituiId,
                'user_id' => Auth::id(),
                'ativo' => true,
            ]);

            if ($anterior) {
                $antes = $this->snapshot($anterior);
                $anterior->update([
                    'situacao' => RhEpiEntrega::SIT_SUBSTITUIDO,
                    'substituido_por_id' => $entrega->getKey(),
                    'motivo_substituicao' => $this->blankToNull($data['motivo_substituicao'] ?? null) ?? $anterior->motivo_substituicao,
                ]);
                $this->log($anterior, 'substituir', $antes, $this->snapshot($anterior->fresh()), $data['motivo_substituicao'] ?? null);
            }

            $this->log($entrega, 'entregar', null, $this->snapshot($entrega), null);

            return $entrega;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function corrigir(RhEpiEntrega $entrega, array $data, ?string $motivo = null): RhEpiEntrega
    {
        if ($entrega->situacao === RhEpiEntrega::SIT_CANCELADO) {
            throw new InvalidArgumentException('Entrega cancelada não pode ser corrigida.');
        }

        $antes = $this->snapshot($entrega);

        $entrega->fill([
            'quantidade' => max(1, (int) ($data['quantidade'] ?? $entrega->quantidade)),
            'entregue_em' => $this->parseDate($data['entregue_em'] ?? null) ?? $entrega->entregue_em?->toDateString(),
            'previsao_troca' => array_key_exists('previsao_troca', $data)
                ? $this->parseDate($data['previsao_troca'])
                : $entrega->previsao_troca?->toDateString(),
            'observacao' => array_key_exists('observacao', $data)
                ? $this->blankToNull($data['observacao'])
                : $entrega->observacao,
        ]);

        if (filled($data['funcionario_id'] ?? null)) {
            $entrega->funcionario_id = (int) $data['funcionario_id'];
        }

        if (filled($data['epi_id'] ?? null)) {
            $entrega->epi_id = (int) $data['epi_id'];
        }

        $entrega->save();
        $this->log($entrega, 'corrigir', $antes, $this->snapshot($entrega), $motivo);

        return $entrega;
    }

    public function devolver(RhEpiEntrega $entrega, ?string $dataDevolucao = null, ?string $observacao = null): RhEpiEntrega
    {
        if ($entrega->situacao !== RhEpiEntrega::SIT_ENTREGUE) {
            throw new InvalidArgumentException('Somente entregas abertas podem ser devolvidas.');
        }

        $antes = $this->snapshot($entrega);
        $entrega->update([
            'situacao' => RhEpiEntrega::SIT_DEVOLVIDO,
            'devolvido_em' => $this->parseDate($dataDevolucao) ?? Carbon::today()->toDateString(),
            'observacao' => $this->blankToNull($observacao) ?? $entrega->observacao,
        ]);
        $this->log($entrega, 'devolver', $antes, $this->snapshot($entrega->fresh()), $observacao);

        return $entrega->fresh();
    }

    public function cancelar(RhEpiEntrega $entrega, ?string $motivo = null): RhEpiEntrega
    {
        if ($entrega->situacao === RhEpiEntrega::SIT_CANCELADO) {
            return $entrega;
        }

        $antes = $this->snapshot($entrega);
        $entrega->update([
            'situacao' => RhEpiEntrega::SIT_CANCELADO,
            'ativo' => false,
            'observacao' => trim(($entrega->observacao ? $entrega->observacao.' | ' : '').'CANCELADO: '.($motivo ?: 'sem motivo')),
        ]);
        $this->log($entrega, 'cancelar', $antes, $this->snapshot($entrega->fresh()), $motivo);

        return $entrega->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(RhEpiEntrega $entrega): array
    {
        return [
            'codigo' => $entrega->codigo,
            'funcionario_id' => $entrega->funcionario_id,
            'epi_id' => $entrega->epi_id,
            'quantidade' => $entrega->quantidade,
            'entregue_em' => $entrega->entregue_em?->toDateString(),
            'previsao_troca' => $entrega->previsao_troca?->toDateString(),
            'situacao' => $entrega->situacao,
            'observacao' => $entrega->observacao,
            'ativo' => $entrega->ativo,
        ];
    }

    private function resolvePrevisaoTroca(RhEpi $epi, string $entregueEm, mixed $informada): ?string
    {
        $parsed = $this->parseDate($informada);
        if ($parsed !== null) {
            return $parsed;
        }

        if ($epi->intervalo_troca_dias) {
            return Carbon::parse($entregueEm)->addDays((int) $epi->intervalo_troca_dias)->toDateString();
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
    private function log(RhEpiEntrega $entrega, string $acao, ?array $antes, ?array $depois, ?string $motivo): void
    {
        RhEpiEntregaLog::query()->create([
            'entrega_id' => $entrega->getKey(),
            'user_id' => Auth::id(),
            'acao' => $acao,
            'antes' => $antes,
            'depois' => $depois,
            'motivo' => $this->blankToNull($motivo),
            'created_at' => now(),
        ]);
    }
}
