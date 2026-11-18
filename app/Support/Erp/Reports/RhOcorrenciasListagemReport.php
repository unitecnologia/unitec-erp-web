<?php

namespace App\Support\Erp\Reports;

use App\Models\RhOcorrencia;
use App\Support\Rh\RhOcorrenciaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class RhOcorrenciasListagemReport
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
            'ocorrido_em' => 'Data',
            'descricao' => 'Descrição',
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
            'ativas' => 'Ativas',
            'canceladas' => 'Canceladas',
            'todos' => 'Todos',
        ];
    }

    public static function cellValue(RhOcorrencia $ocorrencia, string $column, bool $podeVerRestrito): string
    {
        return match ($column) {
            'codigo' => (string) ($ocorrencia->codigo ?: $ocorrencia->getKey()),
            'funcionario' => trim(($ocorrencia->funcionario?->codigo ?? '').' '.($ocorrencia->funcionario?->nome ?? '')) ?: '—',
            'tipo' => $ocorrencia->tipoLabel(),
            'ocorrido_em' => $ocorrencia->ocorrido_em?->format('d/m/Y') ?? '—',
            'descricao' => $ocorrencia->descricaoResumo(80, $podeVerRestrito),
            'situacao' => $ocorrencia->situacaoLabel(),
            default => '—',
        };
    }

    public static function buildQuery(Request $request): Builder
    {
        $status = (string) $request->query('status', 'ativas');
        if (! in_array($status, ['ativas', 'canceladas', 'todos'], true)) {
            $status = 'ativas';
        }

        $tipo = (string) $request->query('tipo', 'todos');
        if ($tipo !== 'todos' && ! array_key_exists($tipo, RhOcorrencia::tipos())) {
            $tipo = 'todos';
        }

        $query = RhOcorrencia::query()->with(['funcionario:id,codigo,nome']);

        $query = match ($status) {
            'ativas' => $query->where('ativo', true)->where('situacao', '!=', RhOcorrencia::SIT_CANCELADA),
            'canceladas' => $query->where(function (Builder $q): void {
                $q->where('situacao', RhOcorrencia::SIT_CANCELADA)->orWhere('ativo', false);
            }),
            default => $query,
        };

        if ($tipo !== 'todos') {
            $query->where('tipo', $tipo);
        }

        $de = trim((string) $request->query('de', ''));
        $ate = trim((string) $request->query('ate', ''));
        if ($de !== '') {
            $query->whereDate('ocorrido_em', '>=', $de);
        }
        if ($ate !== '') {
            $query->whereDate('ocorrido_em', '<=', $ate);
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
            } elseif ($campo === 'descricao') {
                $query->where('descricao', 'like', '%'.$term.'%');
            } else {
                $like = '%'.$term.'%';
                $query->whereHas('funcionario', function (Builder $q) use ($like): void {
                    $q->where('nome', 'like', $like)->orWhere('codigo', 'like', $like);
                });
            }
        }

        return $query->orderByDesc('id')->limit(self::MAX_ROWS);
    }

    public static function podeVerRestritoNoRelatorio(): bool
    {
        return RhOcorrenciaService::podeVerConteudoRestrito();
    }
}
