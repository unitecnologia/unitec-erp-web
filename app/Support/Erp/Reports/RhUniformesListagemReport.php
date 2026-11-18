<?php

namespace App\Support\Erp\Reports;

use App\Models\RhUniformeEntrega;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class RhUniformesListagemReport
{
    public const MAX_ROWS = 500;

    /**
     * @return array<string, string>
     */
    public static function columnDefinitions(): array
    {
        return [
            'codigo' => 'Código',
            'funcionario' => 'Funcionário',
            'uniforme' => 'Uniforme',
            'tamanho' => 'Tamanho',
            'quantidade' => 'Qtd',
            'entregue_em' => 'Data',
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
            'abertas' => 'Abertas',
            'devolvidas' => 'Devolvidas',
            'trocadas' => 'Trocadas',
            'substituidas' => 'Substituídas',
            'canceladas' => 'Canceladas',
            'todos' => 'Todos',
        ];
    }

    public static function cellValue(RhUniformeEntrega $entrega, string $column): string
    {
        return match ($column) {
            'codigo' => (string) ($entrega->codigo ?: $entrega->getKey()),
            'funcionario' => trim(($entrega->funcionario?->codigo ?? '').' '.($entrega->funcionario?->nome ?? '')) ?: '—',
            'uniforme' => (string) ($entrega->uniforme?->descricao ?: '—'),
            'tamanho' => (string) ($entrega->uniforme?->tamanho ?: '—'),
            'quantidade' => (string) $entrega->quantidade,
            'entregue_em' => $entrega->entregue_em?->format('d/m/Y') ?? '—',
            'situacao' => $entrega->situacaoLabel(),
            default => '—',
        };
    }

    public static function buildQuery(Request $request): Builder
    {
        $status = (string) $request->query('status', 'abertas');
        if (! in_array($status, ['abertas', 'devolvidas', 'trocadas', 'substituidas', 'canceladas', 'todos'], true)) {
            $status = 'abertas';
        }

        $query = RhUniformeEntrega::query()
            ->with([
                'funcionario:id,codigo,nome',
                'uniforme:id,codigo,descricao,tipo,tamanho,cor',
            ]);

        $query = match ($status) {
            'abertas' => $query->where('situacao', RhUniformeEntrega::SIT_ENTREGUE)->where('ativo', true),
            'devolvidas' => $query->where('situacao', RhUniformeEntrega::SIT_DEVOLVIDO),
            'trocadas' => $query->where('situacao', RhUniformeEntrega::SIT_TROCADO),
            'substituidas' => $query->where('situacao', RhUniformeEntrega::SIT_SUBSTITUIDO),
            'canceladas' => $query->where('situacao', RhUniformeEntrega::SIT_CANCELADO),
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
            } elseif ($campo === 'tamanho') {
                $query->whereHas('uniforme', fn (Builder $q) => $q->where('tamanho', 'like', '%'.$term.'%'));
            } elseif ($campo === 'uniforme') {
                $query->whereHas('uniforme', function (Builder $q) use ($term): void {
                    $q->where('descricao', 'like', '%'.$term.'%')
                        ->orWhere('codigo', 'like', '%'.$term.'%')
                        ->orWhere('tipo', 'like', '%'.$term.'%')
                        ->orWhere('cor', 'like', '%'.$term.'%');
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
