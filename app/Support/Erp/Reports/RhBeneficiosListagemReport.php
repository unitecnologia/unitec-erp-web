<?php

namespace App\Support\Erp\Reports;

use App\Models\RhBeneficioVinculo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class RhBeneficiosListagemReport
{
    public const MAX_ROWS = 500;

    /**
     * @return array<string, string>
     */
    public static function columnDefinitions(): array
    {
        return [
            'funcionario' => 'Funcionário',
            'beneficio' => 'Benefício',
            'tipo' => 'Tipo',
            'valor' => 'Valor',
            'participacao' => 'Part. funcionário',
            'inicio' => 'Início',
            'termino' => 'Término',
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

    public static function cellValue(RhBeneficioVinculo $vinculo, string $column): string
    {
        return match ($column) {
            'funcionario' => trim(($vinculo->funcionario?->codigo ?? '').' '.($vinculo->funcionario?->nome ?? '')) ?: '—',
            'beneficio' => (string) ($vinculo->beneficio?->descricao ?: '—'),
            'tipo' => $vinculo->beneficio?->tipoLabel() ?? '—',
            'valor' => self::formatMoney($vinculo->valor),
            'participacao' => $vinculo->participacao_funcionario !== null
                ? self::formatMoney($vinculo->participacao_funcionario)
                : '—',
            'inicio' => $vinculo->inicio_em?->format('d/m/Y') ?? '—',
            'termino' => $vinculo->termino_em?->format('d/m/Y') ?? '—',
            'situacao' => $vinculo->situacaoLabel(),
            default => '—',
        };
    }

    public static function buildQuery(Request $request): Builder
    {
        $status = (string) $request->query('status', 'ativos');
        if (! in_array($status, ['ativos', 'cancelados', 'todos'], true)) {
            $status = 'ativos';
        }

        $query = RhBeneficioVinculo::query()
            ->with([
                'funcionario:id,codigo,nome',
                'beneficio:id,codigo,descricao,tipo',
            ]);

        $query = match ($status) {
            'ativos' => $query->where('situacao', RhBeneficioVinculo::SIT_ATIVO)->where('ativo', true),
            'cancelados' => $query->where('situacao', RhBeneficioVinculo::SIT_CANCELADO),
            default => $query,
        };

        $campo = (string) $request->query('campo', 'funcionario');
        $term = mb_strtoupper(trim((string) $request->query('q', '')), 'UTF-8');

        if ($term !== '') {
            if ($campo === 'beneficio') {
                $query->whereHas('beneficio', function (Builder $q) use ($term): void {
                    $q->where('descricao', 'like', '%'.$term.'%')
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

    /**
     * Soma valores de vínculos ativos vigentes no mês de referência (totalização administrativa).
     *
     * @param  Collection<int, RhBeneficioVinculo>  $vinculos
     */
    public static function monthlyAdministrativeTotal(Collection $vinculos, ?Carbon $reference = null): float
    {
        $ref = $reference ?? now('America/Sao_Paulo');
        $start = $ref->copy()->startOfMonth()->toDateString();
        $end = $ref->copy()->endOfMonth()->toDateString();

        $total = 0.0;

        foreach ($vinculos as $vinculo) {
            if ($vinculo->situacao !== RhBeneficioVinculo::SIT_ATIVO || ! $vinculo->ativo) {
                continue;
            }

            $inicio = $vinculo->inicio_em?->toDateString();
            if ($inicio !== null && $inicio > $end) {
                continue;
            }

            $termino = $vinculo->termino_em?->toDateString();
            if ($termino !== null && $termino < $start) {
                continue;
            }

            $total += (float) $vinculo->valor;
        }

        return round($total, 2);
    }

    private static function formatMoney(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, 2, ',', '.');
    }
}
