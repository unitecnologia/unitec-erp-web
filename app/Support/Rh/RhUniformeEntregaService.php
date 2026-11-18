<?php

namespace App\Support\Rh;

use App\Models\RhFuncionario;
use App\Models\RhUniforme;
use App\Models\RhUniformeEntrega;
use App\Models\RhUniformeEntregaLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RhUniformeEntregaService
{
    /**
     * @param  array{
     *   funcionario_id: int,
     *   uniforme_id: int,
     *   quantidade?: int,
     *   entregue_em?: string|null,
     *   observacao?: string|null,
     *   substitui_id?: int|null,
     *   motivo_movimentacao?: string|null,
     *   modo_movimentacao?: string|null
     * }  $data
     */
    public function entregar(array $data): RhUniformeEntrega
    {
        $funcionario = RhFuncionario::query()->find((int) $data['funcionario_id']);
        $uniforme = RhUniforme::query()->find((int) $data['uniforme_id']);

        if (! $funcionario || ! $uniforme) {
            throw new InvalidArgumentException('Funcionário ou uniforme inválido.');
        }

        if (! $uniforme->ativo) {
            throw new InvalidArgumentException('Uniforme inativo. Reative o item antes de entregar.');
        }

        $qtd = max(1, (int) ($data['quantidade'] ?? 1));
        $entregueEm = $this->parseDate($data['entregue_em'] ?? null) ?? Carbon::today()->toDateString();

        return DB::transaction(function () use ($data, $funcionario, $uniforme, $qtd, $entregueEm): RhUniformeEntrega {
            $substituiId = filled($data['substitui_id'] ?? null) ? (int) $data['substitui_id'] : null;
            $anterior = null;
            $modo = (string) ($data['modo_movimentacao'] ?? 'substituir');

            if ($substituiId) {
                $anterior = RhUniformeEntrega::query()->lockForUpdate()->find($substituiId);
                if (! $anterior || $anterior->situacao !== RhUniformeEntrega::SIT_ENTREGUE) {
                    throw new InvalidArgumentException('Entrega a movimentar inválida.');
                }
            }

            $entrega = RhUniformeEntrega::query()->create([
                'codigo' => RhUniformeEntrega::nextCodigo(),
                'funcionario_id' => $funcionario->getKey(),
                'uniforme_id' => $uniforme->getKey(),
                'quantidade' => $qtd,
                'entregue_em' => $entregueEm,
                'situacao' => RhUniformeEntrega::SIT_ENTREGUE,
                'observacao' => $this->blankToNull($data['observacao'] ?? null),
                'motivo_movimentacao' => $this->blankToNull($data['motivo_movimentacao'] ?? null),
                'substitui_id' => $substituiId,
                'user_id' => Auth::id(),
                'ativo' => true,
            ]);

            if ($anterior) {
                $antes = $this->snapshot($anterior);
                $novaSituacao = $modo === 'trocar'
                    ? RhUniformeEntrega::SIT_TROCADO
                    : RhUniformeEntrega::SIT_SUBSTITUIDO;
                $acao = $modo === 'trocar' ? 'trocar' : 'substituir';

                $anterior->update([
                    'situacao' => $novaSituacao,
                    'substituido_por_id' => $entrega->getKey(),
                    'motivo_movimentacao' => $this->blankToNull($data['motivo_movimentacao'] ?? null)
                        ?? $anterior->motivo_movimentacao,
                ]);
                $this->log($anterior, $acao, $antes, $this->snapshot($anterior->fresh()), $data['motivo_movimentacao'] ?? null);
            }

            $this->log($entrega, 'entregar', null, $this->snapshot($entrega), null);

            return $entrega;
        });
    }

    /**
     * @param  array{
     *   funcionario_id: int,
     *   entregue_em?: string|null,
     *   observacao?: string|null,
     *   itens: list<array{uniforme_id:int, quantidade?:int}>
     * }  $data
     * @return list<RhUniformeEntrega>
     */
    public function entregarLote(array $data): array
    {
        $itens = $data['itens'] ?? [];
        if (! is_array($itens) || $itens === []) {
            throw new InvalidArgumentException('Informe ao menos uma peça para entrega.');
        }

        if (count($itens) > 10) {
            throw new InvalidArgumentException('Máximo de 10 peças por entrega.');
        }

        return DB::transaction(function () use ($data, $itens): array {
            $criadas = [];
            foreach ($itens as $item) {
                $criadas[] = $this->entregar([
                    'funcionario_id' => (int) $data['funcionario_id'],
                    'uniforme_id' => (int) ($item['uniforme_id'] ?? 0),
                    'quantidade' => (int) ($item['quantidade'] ?? 1),
                    'entregue_em' => $data['entregue_em'] ?? null,
                    'observacao' => $data['observacao'] ?? null,
                ]);
            }

            return $criadas;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function corrigir(RhUniformeEntrega $entrega, array $data, ?string $motivo = null): RhUniformeEntrega
    {
        if ($entrega->situacao === RhUniformeEntrega::SIT_CANCELADO) {
            throw new InvalidArgumentException('Entrega cancelada não pode ser corrigida.');
        }

        $antes = $this->snapshot($entrega);

        $entrega->fill([
            'quantidade' => max(1, (int) ($data['quantidade'] ?? $entrega->quantidade)),
            'entregue_em' => $this->parseDate($data['entregue_em'] ?? null) ?? $entrega->entregue_em?->toDateString(),
            'observacao' => array_key_exists('observacao', $data)
                ? $this->blankToNull($data['observacao'])
                : $entrega->observacao,
        ]);

        if (filled($data['funcionario_id'] ?? null)) {
            $entrega->funcionario_id = (int) $data['funcionario_id'];
        }

        if (filled($data['uniforme_id'] ?? null)) {
            $entrega->uniforme_id = (int) $data['uniforme_id'];
        }

        $entrega->save();
        $this->log($entrega, 'corrigir', $antes, $this->snapshot($entrega), $motivo);

        return $entrega;
    }

    public function devolver(RhUniformeEntrega $entrega, ?string $dataDevolucao = null, ?string $observacao = null): RhUniformeEntrega
    {
        if ($entrega->situacao !== RhUniformeEntrega::SIT_ENTREGUE) {
            throw new InvalidArgumentException('Somente entregas abertas podem ser devolvidas.');
        }

        $antes = $this->snapshot($entrega);
        $entrega->update([
            'situacao' => RhUniformeEntrega::SIT_DEVOLVIDO,
            'devolvido_em' => $this->parseDate($dataDevolucao) ?? Carbon::today()->toDateString(),
            'observacao' => $this->blankToNull($observacao) ?? $entrega->observacao,
        ]);
        $this->log($entrega, 'devolver', $antes, $this->snapshot($entrega->fresh()), $observacao);

        return $entrega->fresh();
    }

    /**
     * Troca de tamanho/peça: marca a anterior como trocada e cria nova entrega vinculada.
     *
     * @param  array{
     *   uniforme_id: int,
     *   quantidade?: int,
     *   entregue_em?: string|null,
     *   observacao?: string|null,
     *   motivo_movimentacao?: string|null
     * }  $data
     */
    public function trocar(RhUniformeEntrega $entrega, array $data): RhUniformeEntrega
    {
        if ($entrega->situacao !== RhUniformeEntrega::SIT_ENTREGUE) {
            throw new InvalidArgumentException('Somente entregas abertas podem ser trocadas.');
        }

        return $this->entregar([
            'funcionario_id' => (int) $entrega->funcionario_id,
            'uniforme_id' => (int) $data['uniforme_id'],
            'quantidade' => (int) ($data['quantidade'] ?? $entrega->quantidade),
            'entregue_em' => $data['entregue_em'] ?? now('America/Sao_Paulo')->toDateString(),
            'observacao' => $data['observacao'] ?? $entrega->observacao,
            'substitui_id' => (int) $entrega->getKey(),
            'motivo_movimentacao' => $data['motivo_movimentacao'] ?? null,
            'modo_movimentacao' => 'trocar',
        ]);
    }

    /**
     * Substituição por desgaste/dano: marca a anterior como substituída.
     *
     * @param  array{
     *   uniforme_id: int,
     *   quantidade?: int,
     *   entregue_em?: string|null,
     *   observacao?: string|null,
     *   motivo_movimentacao?: string|null
     * }  $data
     */
    public function substituir(RhUniformeEntrega $entrega, array $data): RhUniformeEntrega
    {
        if ($entrega->situacao !== RhUniformeEntrega::SIT_ENTREGUE) {
            throw new InvalidArgumentException('Somente entregas abertas podem ser substituídas.');
        }

        return $this->entregar([
            'funcionario_id' => (int) $entrega->funcionario_id,
            'uniforme_id' => (int) $data['uniforme_id'],
            'quantidade' => (int) ($data['quantidade'] ?? $entrega->quantidade),
            'entregue_em' => $data['entregue_em'] ?? now('America/Sao_Paulo')->toDateString(),
            'observacao' => $data['observacao'] ?? $entrega->observacao,
            'substitui_id' => (int) $entrega->getKey(),
            'motivo_movimentacao' => $data['motivo_movimentacao'] ?? null,
            'modo_movimentacao' => 'substituir',
        ]);
    }

    public function cancelar(RhUniformeEntrega $entrega, ?string $motivo = null): RhUniformeEntrega
    {
        if ($entrega->situacao === RhUniformeEntrega::SIT_CANCELADO) {
            return $entrega;
        }

        $antes = $this->snapshot($entrega);
        $entrega->update([
            'situacao' => RhUniformeEntrega::SIT_CANCELADO,
            'ativo' => false,
            'observacao' => trim(($entrega->observacao ? $entrega->observacao.' | ' : '').'CANCELADO: '.($motivo ?: 'sem motivo')),
        ]);
        $this->log($entrega, 'cancelar', $antes, $this->snapshot($entrega->fresh()), $motivo);

        return $entrega->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(RhUniformeEntrega $entrega): array
    {
        return [
            'codigo' => $entrega->codigo,
            'funcionario_id' => $entrega->funcionario_id,
            'uniforme_id' => $entrega->uniforme_id,
            'quantidade' => $entrega->quantidade,
            'entregue_em' => $entrega->entregue_em?->toDateString(),
            'situacao' => $entrega->situacao,
            'observacao' => $entrega->observacao,
            'ativo' => $entrega->ativo,
        ];
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
    private function log(RhUniformeEntrega $entrega, string $acao, ?array $antes, ?array $depois, ?string $motivo): void
    {
        RhUniformeEntregaLog::query()->create([
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
