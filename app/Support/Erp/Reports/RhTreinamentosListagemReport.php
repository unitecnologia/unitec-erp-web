<?php

namespace App\Support\Erp\Reports;

use App\Models\RhTreinamentoParticipante;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class RhTreinamentosListagemReport
{
    public const MAX_ROWS = 500;

    /**
     * @return array<string, string>
     */
    public static function columnDefinitions(): array
    {
        return [
            'codigo' => 'Cód.',
            'funcionario' => 'Funcionário',
            'treinamento' => 'Treinamento',
            'realizado_em' => 'Data',
            'carga_horaria' => 'Carga h.',
            'vencimento_em' => 'Vencimento',
            'situacao' => 'Situação',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return array_keys(self::columnDefinitions());
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'ativos' => 'Ativos',
            'cancelados' => 'Cancelados',
            'todos' => 'Todos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validadeLabels(): array
    {
        return [
            'todos' => 'Todas',
            'em_dia' => 'Em dia',
            'proximos' => 'Próximos (30 dias)',
            'vencidos' => 'Vencidos',
            'sem' => 'Sem validade',
        ];
    }

    public static function cellValue(RhTreinamentoParticipante $participante, string $column): string
    {
        return match ($column) {
            'codigo' => (string) ($participante->codigo ?: $participante->getKey()),
            'funcionario' => trim(($participante->funcionario?->codigo ?? '').' '.($participante->funcionario?->nome ?? '')) ?: '—',
            'treinamento' => (string) ($participante->realizacao?->treinamento?->nome ?: '—'),
            'realizado_em' => $participante->realizacao?->realizado_em?->format('d/m/Y') ?? '—',
            'carga_horaria' => $participante->realizacao?->treinamento?->carga_horaria !== null
                ? number_format((float) $participante->realizacao->treinamento->carga_horaria, 2, ',', '.')
                : '—',
            'vencimento_em' => $participante->vencimento_em?->format('d/m/Y') ?? '—',
            'situacao' => $participante->situacaoLabel(),
            default => '—',
        };
    }

    public static function buildQuery(Request $request): Builder
    {
        $status = (string) $request->query('status', 'ativos');
        if (! in_array($status, ['ativos', 'cancelados', 'todos'], true)) {
            $status = 'ativos';
        }

        $validade = (string) $request->query('validade', 'todos');
        if (! in_array($validade, ['todos', 'em_dia', 'proximos', 'vencidos', 'sem'], true)) {
            $validade = 'todos';
        }

        $query = RhTreinamentoParticipante::query()
            ->with([
                'funcionario:id,codigo,nome',
                'realizacao:id,codigo,treinamento_id,realizado_em',
                'realizacao.treinamento:id,codigo,nome,carga_horaria',
            ]);

        $query = match ($status) {
            'ativos' => $query->where('situacao', RhTreinamentoParticipante::SIT_ATIVO)->where('ativo', true),
            'cancelados' => $query->where('situacao', RhTreinamentoParticipante::SIT_CANCELADO),
            default => $query,
        };

        if ($validade !== 'todos' && in_array($status, ['ativos', 'todos'], true)) {
            $query->where('situacao', RhTreinamentoParticipante::SIT_ATIVO)->where('ativo', true);

            $hoje = Carbon::today()->toDateString();
            $limite = Carbon::today()->addDays(30)->toDateString();

            match ($validade) {
                'sem' => $query->whereNull('vencimento_em'),
                'vencidos' => $query->whereNotNull('vencimento_em')->whereDate('vencimento_em', '<', $hoje),
                'proximos' => $query->whereNotNull('vencimento_em')
                    ->whereDate('vencimento_em', '>=', $hoje)
                    ->whereDate('vencimento_em', '<=', $limite),
                'em_dia' => $query->whereNotNull('vencimento_em')->whereDate('vencimento_em', '>', $limite),
                default => null,
            };
        }

        $campo = (string) $request->query('campo', 'funcionario');
        $term = mb_strtoupper(trim((string) $request->query('q', '')), 'UTF-8');

        if ($term !== '') {
            if ($campo === 'codigo') {
                if (is_numeric($term)) {
                    $query->where(function (Builder $q) use ($term): void {
                        $q->whereKey((int) $term)->orWhere('codigo', $term);
                    });
                } else {
                    $query->where('codigo', 'like', '%'.$term.'%');
                }
            } elseif ($campo === 'treinamento') {
                $query->whereHas('realizacao.treinamento', function (Builder $q) use ($term): void {
                    $q->where('nome', 'like', '%'.$term.'%')
                        ->orWhere('codigo', 'like', '%'.$term.'%');
                });
            } else {
                $like = '%'.$term.'%';
                $query->whereHas('funcionario', function (Builder $q) use ($like): void {
                    $q->where('nome', 'like', $like)->orWhere('codigo', 'like', $like);
                });
            }
        }

        return $query->orderByDesc('id')->limit(self::MAX_ROWS);
    }
}
