<?php

namespace App\Support\Erp\Reports;

use App\Models\RhHolerite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class RhHoleritesListagemReport
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
            'competencia' => 'Competência',
            'pago_em' => 'Pago em',
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
            'inativos' => 'Inativos',
            'todos' => 'Todos',
        ];
    }

    public static function cellValue(RhHolerite $holerite, string $column): string
    {
        return match ($column) {
            'codigo' => (string) ($holerite->codigo ?: $holerite->getKey()),
            'funcionario' => trim(($holerite->funcionario?->codigo ?? '').' '.($holerite->funcionario?->nome ?? '')) ?: '—',
            'competencia' => $holerite->competenciaLabel(),
            'pago_em' => $holerite->pago_em?->format('d/m/Y') ?? '—',
            'situacao' => $holerite->situacaoLabel(),
            default => '—',
        };
    }

    public static function buildQuery(Request $request): Builder
    {
        $status = (string) $request->query('status', 'ativos');
        if (! in_array($status, ['ativos', 'inativos', 'todos'], true)) {
            $status = 'ativos';
        }

        $query = RhHolerite::query()->with(['funcionario:id,codigo,nome']);

        $query = match ($status) {
            'ativos' => $query->where('ativo', true),
            'inativos' => $query->where('ativo', false),
            default => $query,
        };

        $ano = $request->query('ano');
        $mes = $request->query('mes');
        $anoInt = is_numeric($ano) ? (int) $ano : null;
        $mesInt = is_numeric($mes) ? (int) $mes : null;
        $query->competencia(
            $anoInt !== null && $anoInt >= 2000 ? $anoInt : null,
            $mesInt !== null && $mesInt >= 1 && $mesInt <= 12 ? $mesInt : null,
        );

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
