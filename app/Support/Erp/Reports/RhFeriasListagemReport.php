<?php

namespace App\Support\Erp\Reports;

use App\Models\RhFeriasGozo;
use App\Models\RhFeriasPeriodo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class RhFeriasListagemReport
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
            'aquisitivo' => 'Aquisitivo',
            'gozo_inicio' => 'Início',
            'gozo_fim' => 'Fim',
            'dias' => 'Dias',
            'situacao' => 'Situação',
            'aviso' => 'Aviso',
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
            'programadas' => 'Programadas',
            'em_andamento' => 'Em andamento',
            'concluidas' => 'Concluídas',
            'pendentes' => 'Pendentes',
            'todos' => 'Todos',
        ];
    }

    public static function cellValue(RhFeriasGozo $gozo, string $column, int $alertaDias = 30): string
    {
        return match ($column) {
            'codigo' => (string) ($gozo->codigo ?: $gozo->getKey()),
            'funcionario' => trim(($gozo->funcionario?->codigo ?? '').' '.($gozo->funcionario?->nome ?? '')) ?: '—',
            'aquisitivo' => $gozo->periodo?->aquisitivoResumo() ?? '—',
            'gozo_inicio' => $gozo->gozo_inicio?->format('d/m/Y') ?? '—',
            'gozo_fim' => $gozo->gozo_fim?->format('d/m/Y') ?? '—',
            'dias' => (string) $gozo->dias,
            'situacao' => $gozo->situacaoLabel(),
            'aviso' => $gozo->alertaAvisoLabel($alertaDias),
            default => '—',
        };
    }

    public static function buildQuery(Request $request): Builder
    {
        $status = (string) $request->query('status', 'programadas');
        if (! in_array($status, ['programadas', 'em_andamento', 'concluidas', 'pendentes', 'todos'], true)) {
            $status = 'programadas';
        }

        $alerta = (int) $request->query('alerta', 30);
        if (! in_array($alerta, [7, 15, 30], true)) {
            $alerta = 30;
        }

        $query = RhFeriasGozo::query()->with([
            'funcionario:id,codigo,nome',
            'periodo:id,aquisitivo_inicio,aquisitivo_fim,dias_saldo,concessivo_fim,situacao',
        ]);

        $hoje = now()->toDateString();
        $limiteConcessivo = now()->addDays(60)->toDateString();

        $query = match ($status) {
            'programadas' => $query->where('ativo', true)
                ->where('situacao', RhFeriasGozo::SIT_PROGRAMADA),
            'em_andamento' => $query->where('ativo', true)
                ->where('situacao', RhFeriasGozo::SIT_EM_ANDAMENTO),
            'concluidas' => $query->where('situacao', RhFeriasGozo::SIT_CONCLUIDA),
            'pendentes' => $query->where('ativo', true)
                ->where('situacao', '!=', RhFeriasGozo::SIT_CANCELADA)
                ->whereHas('periodo', function (Builder $q) use ($hoje, $limiteConcessivo): void {
                    $q->where('dias_saldo', '>', 0)
                        ->whereIn('situacao', [
                            RhFeriasPeriodo::SIT_ABERTO,
                            RhFeriasPeriodo::SIT_PARCIAL,
                            RhFeriasPeriodo::SIT_VENCIDO,
                        ])
                        ->whereDate('concessivo_fim', '>=', $hoje)
                        ->whereDate('concessivo_fim', '<=', $limiteConcessivo);
                }),
            default => $query,
        };

        $term = mb_strtoupper(trim((string) $request->query('q', '')), 'UTF-8');
        $campo = (string) $request->query('campo', 'funcionario');

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

        return $query->orderByDesc('gozo_inicio')->limit(self::MAX_ROWS);
    }
}
