<?php

namespace App\Support\Erp\Reports;

use App\Models\RhEpiEntrega;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class RhEpisListagemReport
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
            'epi' => 'EPI',
            'ca' => 'CA',
            'entregue_em' => 'Entrega',
            'previsao_troca' => 'Próx. troca',
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
            'substituidas' => 'Substituídas',
            'canceladas' => 'Canceladas',
            'todos' => 'Todos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function trocaLabels(): array
    {
        return [
            'todos' => 'Todas',
            'proximas' => 'Próximas (30 dias)',
            'vencidas' => 'Vencidas',
        ];
    }

    public static function cellValue(RhEpiEntrega $entrega, string $column): string
    {
        return match ($column) {
            'codigo' => (string) ($entrega->codigo ?: $entrega->getKey()),
            'funcionario' => trim(($entrega->funcionario?->codigo ?? '').' '.($entrega->funcionario?->nome ?? '')) ?: '—',
            'epi' => (string) ($entrega->epi?->descricao ?: '—'),
            'ca' => (string) ($entrega->epi?->ca_numero ?: '—'),
            'entregue_em' => $entrega->entregue_em?->format('d/m/Y') ?? '—',
            'previsao_troca' => $entrega->previsao_troca?->format('d/m/Y') ?? '—',
            'situacao' => $entrega->situacaoLabel(),
            default => '—',
        };
    }

    public static function buildQuery(Request $request): Builder
    {
        $status = (string) $request->query('status', 'abertas');
        if (! in_array($status, ['abertas', 'devolvidas', 'substituidas', 'canceladas', 'todos'], true)) {
            $status = 'abertas';
        }

        $troca = (string) $request->query('troca', 'todos');
        if (! in_array($troca, ['todos', 'proximas', 'vencidas'], true)) {
            $troca = 'todos';
        }

        $query = RhEpiEntrega::query()
            ->with([
                'funcionario:id,codigo,nome',
                'epi:id,codigo,descricao,ca_numero',
            ]);

        $query = match ($status) {
            'abertas' => $query->where('situacao', RhEpiEntrega::SIT_ENTREGUE)->where('ativo', true),
            'devolvidas' => $query->where('situacao', RhEpiEntrega::SIT_DEVOLVIDO),
            'substituidas' => $query->where('situacao', RhEpiEntrega::SIT_SUBSTITUIDO),
            'canceladas' => $query->where('situacao', RhEpiEntrega::SIT_CANCELADO),
            default => $query,
        };

        if ($troca === 'proximas') {
            $hoje = now()->toDateString();
            $limite = now()->addDays(30)->toDateString();
            $query->where('situacao', RhEpiEntrega::SIT_ENTREGUE)
                ->whereNotNull('previsao_troca')
                ->whereDate('previsao_troca', '>=', $hoje)
                ->whereDate('previsao_troca', '<=', $limite);
        } elseif ($troca === 'vencidas') {
            $query->where('situacao', RhEpiEntrega::SIT_ENTREGUE)
                ->whereNotNull('previsao_troca')
                ->whereDate('previsao_troca', '<', now()->toDateString());
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
            } elseif ($campo === 'ca') {
                $query->whereHas('epi', fn (Builder $q) => $q->where('ca_numero', 'like', '%'.$term.'%'));
            } elseif ($campo === 'epi') {
                $query->whereHas('epi', function (Builder $q) use ($term): void {
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
}
