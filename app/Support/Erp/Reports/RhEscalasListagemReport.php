<?php

namespace App\Support\Erp\Reports;

use App\Models\RhEscalaVinculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class RhEscalasListagemReport
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
            'escala' => 'Escala',
            'tipo' => 'Tipo',
            'inicio_em' => 'Início',
            'termino_em' => 'Término',
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
            'encerrados' => 'Encerrados',
            'todos' => 'Todos',
        ];
    }

    public static function cellValue(RhEscalaVinculo $vinculo, string $column): string
    {
        return match ($column) {
            'codigo' => (string) ($vinculo->codigo ?: $vinculo->getKey()),
            'funcionario' => trim(($vinculo->funcionario?->codigo ?? '').' '.($vinculo->funcionario?->nome ?? '')) ?: '—',
            'escala' => (string) ($vinculo->escala?->nome ?? '—'),
            'tipo' => $vinculo->escala?->tipoLabel() ?? '—',
            'inicio_em' => $vinculo->inicio_em?->format('d/m/Y') ?? '—',
            'termino_em' => $vinculo->termino_em?->format('d/m/Y') ?? '—',
            'situacao' => $vinculo->ativo ? 'Ativo' : 'Encerrado',
            default => '—',
        };
    }

    public static function buildQuery(Request $request): Builder
    {
        $status = (string) $request->query('status', 'ativos');
        if (! in_array($status, ['ativos', 'encerrados', 'todos'], true)) {
            $status = 'ativos';
        }

        $query = RhEscalaVinculo::query()
            ->with([
                'funcionario:id,codigo,nome',
                'escala:id,codigo,nome,tipo',
            ]);

        $query = match ($status) {
            'ativos' => $query->where('ativo', true),
            'encerrados' => $query->where('ativo', false),
            default => $query,
        };

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
            } elseif ($campo === 'escala') {
                $like = '%'.$term.'%';
                $query->whereHas('escala', function (Builder $q) use ($like): void {
                    $q->where('nome', 'like', $like)->orWhere('codigo', 'like', $like);
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
