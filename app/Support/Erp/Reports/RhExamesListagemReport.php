<?php

namespace App\Support\Erp\Reports;

use App\Models\RhExame;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class RhExamesListagemReport
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
            'tipo' => 'Tipo',
            'realizado_em' => 'Data exame',
            'proximo_em' => 'Próximo',
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
            'realizados' => 'Realizados',
            'proximos' => 'Próximos',
            'pendentes' => 'Pendentes',
            'todos' => 'Todos',
        ];
    }

    public static function cellValue(RhExame $exame, string $column, int $alertaDias = 30): string
    {
        return match ($column) {
            'codigo' => (string) ($exame->codigo ?: $exame->getKey()),
            'funcionario' => trim(($exame->funcionario?->codigo ?? '').' '.($exame->funcionario?->nome ?? '')) ?: '—',
            'tipo' => $exame->tipoLabel(),
            'realizado_em' => $exame->realizado_em?->format('d/m/Y') ?? '—',
            'proximo_em' => $exame->proximo_em?->format('d/m/Y') ?? '—',
            'situacao' => $exame->situacaoPrazoLabel($alertaDias),
            default => '—',
        };
    }

    public static function buildQuery(Request $request): Builder
    {
        $status = (string) $request->query('status', 'realizados');
        if (! in_array($status, ['realizados', 'proximos', 'pendentes', 'todos'], true)) {
            $status = 'realizados';
        }

        $alerta = (int) $request->query('alerta', 30);
        if (! in_array($alerta, [7, 15, 30], true)) {
            $alerta = 30;
        }

        $query = RhExame::query()->with(['funcionario:id,codigo,nome']);

        $hoje = now()->toDateString();
        $limite = now()->addDays($alerta)->toDateString();

        $query = match ($status) {
            'realizados' => $query->where('ativo', true),
            'proximos' => $query->where('ativo', true)
                ->whereNotNull('proximo_em')
                ->whereDate('proximo_em', '>=', $hoje)
                ->whereDate('proximo_em', '<=', $limite),
            'pendentes' => $query->where('ativo', true)
                ->whereNotNull('proximo_em')
                ->whereDate('proximo_em', '<', $hoje),
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
            } elseif ($campo === 'tipo') {
                $tipos = collect(RhExame::tipos())
                    ->filter(fn (string $label, string $key): bool => str_contains(mb_strtoupper($label, 'UTF-8'), $term) || str_contains(mb_strtoupper($key, 'UTF-8'), $term))
                    ->keys()
                    ->all();
                $query->whereIn('tipo', $tipos !== [] ? $tipos : ['__none__']);
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
